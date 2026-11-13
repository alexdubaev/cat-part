<?php
declare(strict_types=1);
$runtime=getenv('CATPART_ADMIN_RUNTIME')?:dirname(__DIR__).'/local-dev/admin-runtime';
$state=json_decode(file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^catpart_admin_[a-f0-9]{12}$/D',$state['database']))throw new RuntimeException('Only isolated task DB allowed.');
require $runtime.'/core/admin/config.php';
\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],$state['database']);
$pdo=\Catpart\Factory::pdo();\Catpart\Catalog::migrate($pdo);$catalog=new \Catpart\Catalog($pdo);
$tag='cpcheck-'.bin2hex(random_bytes(5));$actor=987654321;$passed=0;$products=[];$categories=[];$brands=[];$attrs=[];$jobs=[];$files=[];
$private=$runtime.'/private/catalog-test-'.$tag;mkdir($private,0700,true);$import=new \Catpart\CatalogImport($pdo,$private);
function check(bool $ok,string $name):void{global $passed;if(!$ok)throw new RuntimeException('FAIL '.$name);$passed++;echo 'PASS '.$name.PHP_EOL;}
function rejects(callable $run,string $contains,string $name):void{try{$run();}catch(InvalidArgumentException $e){check(str_contains($e->getMessage(),$contains),$name);return;}throw new RuntimeException('FAIL expected validation: '.$name);}
function scalar(PDO $pdo,string $sql,array $args=[]):mixed{$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchColumn();}
function previewAll(\Catpart\CatalogImport $import,array $job,array $mapping,int $actor):array{$job=$import->preview($job['id'],$mapping,$actor);$steps=0;while($job['status']==='previewing'){if(++$steps>20)throw new RuntimeException('Preview did not progress.');$job=$import->preview($job['id'],[],$actor,true);}return $job;}
try{
 $brand=$catalog->save('brand',['name'=>'Test brand '.$tag,'slug'=>$tag.'-brand','status'=>'published','logo_url'=>'/assets/img/logo.webp','intro_html'=>'<p>Brand description</p>'], $actor);$brands[]=$brand;
 $parent=$catalog->save('category',['name'=>'Test parent '.$tag,'slug'=>$tag.'-parent','status'=>'published'], $actor);$categories[]=$parent;
 $child=$catalog->save('category',['name'=>'Test child '.$tag,'slug'=>$tag.'-child','parent_id'=>$parent,'status'=>'published'], $actor);$categories[]=$child;
 $extra=$catalog->save('category',['name'=>'Test extra '.$tag,'slug'=>$tag.'-extra','status'=>'published'], $actor);$categories[]=$extra;
 $attribute=$catalog->save('attribute',['name'=>'Test dimension '.$tag,'code'=>$tag,'unit'=>'mm','type'=>'num'], $actor);$attrs[]=$attribute;
 $sku='000-'.$tag.'-A';
 $id=$catalog->save('product',['name'=>'Test part '.$tag,'sku'=>$sku,'slug'=>$tag.'-part','status'=>'published','brand_id'=>$brand,'category_id'=>$child,'category_ids'=>[$extra],'price_mode'=>'fixed','price'=>'123.45','currency'=>'RUB','in_stock'=>1,'delivery_terms'=>'Conditions at request','short_description'=>'Part summary','body_html'=>'<h2>Details</h2><p class="MsoNormal" style="color:red">Safe<script>alert(1)</script><img src="/assets/img/logo.webp" alt="Test alt" onerror="alert(1)"></p>','images'=>[['url'=>'/assets/img/logo.webp','alt'=>'Main alt','caption'=>'Main caption'],['url'=>'/assets/img/logo.webp','alt'=>'Second alt','caption'=>'Second caption']],'primary_image'=>'1','cross'=>[['cross_sku'=>'000-OEM-'.$tag,'kind'=>'oem']],'attributes'=>[['attribute_id'=>$attribute,'value_str'=>'12.5']]],$actor);$products[]=$id;
 $r=$catalog->record('product',$id);
 check($r['sku']===$sku && $r['sku_norm']===\Catpart\Catalog::normalizeSku($sku),'string SKU preserves leading zeros and common normalization');
 check($r['category_id']==$child && $r['category_ids']==[$extra] && $r['brand_id']==$brand,'primary/additional category and brand share canonical record');
 check($r['price']==='123.45' && $r['price_mode']==='fixed' && $r['delivery_terms']==='Conditions at request','price currency availability and delivery metadata persist');
 check($r['images'][0]['alt']==='Second alt' && $r['images'][0]['caption']==='Second caption' && $r['images'][0]['is_primary']==1,'gallery main selection alt and caption persist');
 check(!str_contains($r['body_html'],'script')&&!str_contains($r['body_html'],'onerror')&&!str_contains($r['body_html'],'MsoNormal')&&str_contains($r['body_html'],'alt="Test alt"'),'server HTML sanitizer removes scripts Word formatting and unsafe attributes');
 check(count($r['cross'])===1 && $r['cross'][0]['cross_sku']==='000-OEM-'.$tag && $r['attributes'][0]['value_str']==='12.5','cross numbers and attribute values persist');
 check(scalar($pdo,'SELECT products_count FROM categories WHERE id=?',[$parent])==1 && scalar($pdo,'SELECT products_count FROM categories WHERE id=?',[$extra])==1,'counts include descendants and additional categories');
 rejects(fn()=>$catalog->save('category',['id'=>$parent,'parent_id'=>$child],$actor),'цикл','category cycle rejected without destroying relations');
 rejects(fn()=>$catalog->save('product',['id'=>$id,'slug'=>$tag.'-new'],$actor),'подтвердите','slug change needs explicit confirmation');
 $catalog->save('product',['id'=>$id,'slug'=>$tag.'-new'],$actor,true);$catalog->save('product',['id'=>$id,'slug'=>$tag.'-last'],$actor,true);
 check(scalar($pdo,'SELECT new_path FROM cp_redirect WHERE old_path=?',['/tovar/'.$tag.'-part'])==='/tovar/'.$tag.'-last','oldest redirect flattened directly to current slug');
 $pdo->beginTransaction();rejects(fn()=>$catalog->save('product',['id'=>$id,'name'=>'Must rollback','images'=>[['url'=>'javascript:alert(1)']]],$actor),'Изображение','invalid media does not partly overwrite product inside caller transaction');$pdo->commit();
 check($catalog->record('product',$id)['name']==='Test part '.$tag,'nested savepoint rolls back failed partial write');
 $catalog->save('product',['id'=>$id,'price_mode'=>'request'], $actor);check(scalar($pdo,'SELECT price FROM products WHERE id=?',[$id])===null,'request-price mode has no fabricated zero price');
 $catalog->save('category',['id'=>$child,'parent_id'=>$extra], $actor);check(scalar($pdo,'SELECT path FROM categories WHERE id=?',[$child])==='/'.$extra.'/'.$child.'/' && scalar($pdo,'SELECT category_id FROM products WHERE id=?',[$id])==$child,'category move updates path without losing product relation');
 // CSV: 126 rows exercises >100, chunked apply, existing field preservation, and replay.
 $csv=$private.'/source.csv';$files[]=$csv;$f=fopen($csv,'wb');fputcsv($f,['sku','name','price'],',','"','');fputcsv($f,[$sku,'Updated imported part','25.90'],',','"','');for($i=0;$i<125;$i++)fputcsv($f,['000-'.$tag.'-I'.$i,'Imported '.$i,'12.50'],',','"','');fclose($f);
 $job=$import->stageFile($csv,'catalog.csv',$actor);$jobs[]=$job['id'];$before=(int)scalar($pdo,'SELECT COUNT(*) FROM products');$job=previewAll($import,$job,[0=>'sku',1=>'name',2=>'price'],$actor);
 check($job['status']==='previewed'&&$job['summary']['ready']===126&&(int)scalar($pdo,'SELECT COUNT(*) FROM products')===$before,'CSV mapping and preview do not mutate catalog and handle more than100 rows');
 rejects(fn()=>$import->apply($job['id'],$actor,false),'Подтвердите','import cannot apply without explicit confirmation');
 $steps=0;while($job['status']!=='done'){if(++$steps>10)throw new RuntimeException('Apply did not progress.');$job=$import->apply($job['id'],$actor,true);}
 $q=$pdo->prepare('SELECT target_id FROM cp_import_row WHERE import_id=?');$q->execute([$job['id']]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $pid)$products[]=(int)$pid;
 check($job['summary']['done']===126 && (int)scalar($pdo,'SELECT COUNT(*) FROM products')===$before+125 && $steps>=2,'resumable apply creates125 and updates1 canonical products');
 $again=$import->apply($job['id'],$actor,true);check($again['summary']['done']===126&&(int)scalar($pdo,'SELECT COUNT(*) FROM products')===$before+125,'repeat apply is idempotent');
 $r=$catalog->record('product',$id);check($r['name']==='Updated imported part'&&$r['delivery_terms']==='Conditions at request'&&count($r['images'])===2&&count($r['attributes'])===1,'import preserves omitted delivery gallery and attributes');
 $second=$import->stageFile($csv,'catalog.csv',$actor);$jobs[]=$second['id'];$second=previewAll($import,$second,[0=>'sku',1=>'name',2=>'price'],$actor);while($second['status']!=='done')$second=$import->apply($second['id'],$actor,true);
 check((int)scalar($pdo,'SELECT COUNT(*) FROM products')===$before+125,'uploading same CSV again does not duplicate existing products');
 rejects(fn()=>$import->job($job['id'],$actor-1),'недоступен','private import belongs to its authorized creator');
 $bad=$private.'/bad.csv';$files[]=$bad;file_put_contents($bad,"sku,name,price\n000-".$tag."-BAD,Bad row,-7\n");$badJob=$import->stageFile($bad,'bad.csv',$actor);$jobs[]=$badJob['id'];$badJob=previewAll($import,$badJob,[0=>'sku',1=>'name',2=>'price'],$actor);$report=$import->report($badJob['id'],$actor);
 check($badJob['summary']['error']===1&&$report[0]['line_number']==2&&str_contains($report[0]['message'],'Цена'),'preview reports exact bad row and reason');
 rejects(fn()=>$import->apply($badJob['id'],$actor,true),'ошибки','errored preview cannot silently apply valid subset');
 $book=$private.'/source.xlsx';$files[]=$book;$z=new ZipArchive();$z->open($book,ZipArchive::CREATE);$z->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');$z->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');$z->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>SKU</t></is></c><c r="B1" t="inlineStr"><is><t>Name</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>000-'.$tag.'-XL</t></is></c><c r="B2" t="inlineStr"><is><t>XLSX part</t></is></c></row></sheetData></worksheet>');$z->close();
 $xjob=$import->stageFile($book,'source.xlsx',$actor);$jobs[]=$xjob['id'];$xjob=previewAll($import,$xjob,[0=>'sku',1=>'name'],$actor);$xjob=$import->apply($xjob['id'],$actor,true);$xrow=$import->report($xjob['id'],$actor)[0];$products[]=(int)$xrow['target_id'];check($xjob['summary']['done']===1&&$catalog->record('product',(int)$xrow['target_id'])['sku']==='000-'.$tag.'-XL','server XLSX parsing preserves textual leading zero SKU');
 $malicious=$private.'/unsafe.xlsx';$files[]=$malicious;$z=new ZipArchive();$z->open($malicious,ZipArchive::CREATE);$z->addFromString('[Content_Types].xml','<Types/>');$z->addFromString('xl/workbook.xml','<!DOCTYPE workbook [<!ENTITY x SYSTEM "file:///unavailable">]><workbook>&x;</workbook>');$z->close();rejects(fn()=>$import->stageFile($malicious,'unsafe.xlsx',$actor),'XML','XLSX external entity rejected before parsing');

 $rename=$private.'/rename.csv';$files[]=$rename;$f=fopen($rename,'wb');fputcsv($f,['sku','name','slug'],',','"','');fputcsv($f,[$sku,'Renamed imported product',$tag.'-import-renamed'],',','"','');fclose($f);
 $renameJob=$import->stageFile($rename,'rename.csv',$actor);$jobs[]=$renameJob['id'];$renameJob=previewAll($import,$renameJob,[0=>'sku',1=>'name',2=>'slug'],$actor);$change=$import->report($renameJob['id'],$actor)[0];check($renameJob['summary']['ready']===1&&str_contains($change['message'],'Смена адреса'),'import preview identifies changed URL before explicit apply');$import->apply($renameJob['id'],$actor,true);check(scalar($pdo,'SELECT new_path FROM cp_redirect WHERE old_path=?',['/tovar/'.$tag.'-part'])==='/tovar/'.$tag.'-import-renamed','confirmed import slug change creates flattened redirect');
 $broken=$private.'/broken.xlsx';$files[]=$broken;$z=new ZipArchive();$z->open($broken,ZipArchive::CREATE);$z->addFromString('[Content_Types].xml','<Types/>');$z->addFromString('xl/workbook.xml','<workbook/>');$z->addFromString('xl/worksheets/sheet1.xml','<worksheet><sheetData><row r="1"><c></row>');$z->close();rejects(fn()=>$import->stageFile($broken,'broken.xlsx',$actor),'XML','malformed XLSX XML cannot silently truncate rows');
 $catalog->archive('product',$id,$catalog->record('product',$id)['name'],$actor);check(scalar($pdo,'SELECT is_active FROM products WHERE id=?',[$id])==0 && scalar($pdo,'SELECT category_id FROM products WHERE id=?',[$id])==$child,'archive hides product and keeps all relationships');
 \Catpart\Catalog::migrate($pdo);check(scalar($pdo,'SELECT id FROM products WHERE id=?',[$id])==$id && scalar($pdo,'SELECT COUNT(*) FROM oc_product')==0,'repeat migration preserves canonical IDs and leaves native demo catalog empty');
 echo $passed." passed, 0 failed\n";
}finally{
 if($pdo->inTransaction())$pdo->rollBack();
 $products=array_unique($products);foreach($products as $id){$q=$pdo->prepare('SELECT id FROM product_images WHERE product_id=?');$q->execute([$id]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $image)$pdo->prepare('DELETE FROM cp_image_caption WHERE image_id=?')->execute([$image]);foreach(['product_images','product_cross','product_attr_values','cp_product_category','cp_product_relation'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE product_id=?')->execute([$id]);$pdo->prepare("DELETE FROM cp_catalog_meta WHERE entity='product' AND entity_id=?")->execute([$id]);$pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);}
 foreach(array_reverse($categories) as $id){$pdo->prepare("DELETE FROM cp_catalog_meta WHERE entity='category' AND entity_id=?")->execute([$id]);$pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);}
 foreach($brands as $id){$pdo->prepare("DELETE FROM cp_catalog_meta WHERE entity='brand' AND entity_id=?")->execute([$id]);$pdo->prepare('DELETE FROM brands WHERE id=?')->execute([$id]);}foreach($attrs as $id)$pdo->prepare('DELETE FROM attributes WHERE id=?')->execute([$id]);
 foreach($jobs as $id){$job=$import->job($id,$actor);if(is_file($job['file_path'])&&str_starts_with(str_replace('\\','/',$job['file_path']),str_replace('\\','/',$private).'/'))unlink($job['file_path']);$pdo->prepare('DELETE FROM cp_import_row WHERE import_id=?')->execute([$id]);$pdo->prepare('DELETE FROM cp_import WHERE id=?')->execute([$id]);}
 $pdo->prepare('DELETE FROM cp_redirect WHERE old_path LIKE ?')->execute(['%'.$tag.'%']);$pdo->prepare('DELETE FROM cp_audit WHERE actor_id=?')->execute([$actor]);foreach($files as $file)if(is_file($file))unlink($file);if(is_dir($private))rmdir($private);
}
