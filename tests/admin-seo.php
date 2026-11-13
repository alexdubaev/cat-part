<?php
declare(strict_types=1);
// Only synthetic records on the selected loopback stand; no migration or source data writes.
$root=dirname(__DIR__);$runtime=getenv('CATPART_ADMIN_RUNTIME')?:$root.'/local-dev/admin-runtime';
$state=json_decode(file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
require_once $root.'/opencart/extension/catpart/system/autoload.php';
require $runtime.'/core/admin/config.php';
\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],$state['database']);
if(!preg_match('/^catpart_admin_[a-f0-9]{12}$/D',DB_DATABASE))throw new RuntimeException('Isolated task DB required');
$pdo=\Catpart\Factory::pdo();$catalog=new \Catpart\Catalog($pdo);$content=new \Catpart\Content($pdo);
$mode=$argv[1]??'aliases';$file=getenv('CATPART_SEO_FIXTURE');
if(!$file || dirname(str_replace('\\','/',$file))!==rtrim(str_replace('\\','/',$runtime),'/').'/private' || !preg_match('/^seo-fixture-[a-f0-9]{16}\.json$/D',basename($file)))throw new RuntimeException('Private unique SEO fixture path required');
$fixture=['tag'=>'seo-'.bin2hex(random_bytes(8)),'actor'=>random_int(1800000000,1999999999),'records'=>[],'redirects'=>[],'jobs'=>[],'files'=>[]];
function scalar(string $sql,array $args=[]):mixed{global $pdo;$q=$pdo->prepare($sql);$q->execute($args);return $q->fetchColumn();}
function remember():void{global $file,$fixture;file_put_contents($file,json_encode($fixture,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
function saveRecord(string $type,string $slug,int $id=0,array $extra=[]):int{
 global $catalog,$content,$fixture;
 $input=array_replace(['id'=>$id,'name'=>'SEO synthetic '.$fixture['tag'],'title'=>'SEO synthetic '.$fixture['tag'],'slug'=>$slug,'sku'=>$slug,'status'=>'published','confirm_slug'=>1],$extra);
 $new=$type==='article'?$content->saveArticle($input,$fixture['actor']):$catalog->save($type,$input,$fixture['actor'],true);
 if(!$id){$fixture['records'][]=[$type,$new];remember();}return $new;
}
function cleanup():void{
 global $fixture,$pdo,$file;
 $pdo->beginTransaction();
 // An intentionally red import probe can create a row; own exact input SKUs identify it.
 $q=$pdo->prepare('SELECT id FROM products WHERE sku IN (?,?)');$q->execute([$fixture['tag'].'-import',$fixture['tag'].'-late-import']);
 foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)$fixture['records'][]=['product',(int)$id];
 foreach(array_reverse($fixture['records']) as [$type,$id]){
  if($type==='article'){foreach(['cp_article_revision','cp_article'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE post_id=?')->execute([$id]);$table='blog_posts';}
  else{$pdo->prepare('DELETE FROM cp_catalog_meta WHERE entity=? AND entity_id=?')->execute([$type,$id]);$table=['product'=>'products','category'=>'categories','brand'=>'brands'][$type];}
  $pdo->prepare('DELETE FROM '.$table.' WHERE id=?')->execute([$id]);
 }
 foreach($fixture['redirects'] as $path)$pdo->prepare('DELETE FROM cp_redirect WHERE old_path=?')->execute([$path]);
 foreach($fixture['jobs'] as $job){$pdo->prepare('DELETE FROM cp_import_row WHERE import_id=?')->execute([$job]);$pdo->prepare('DELETE FROM cp_import WHERE id=?')->execute([$job]);}
 $pdo->prepare('DELETE FROM cp_audit WHERE actor_id=?')->execute([$fixture['actor']]);
 $pdo->commit();
 foreach($fixture['files'] as $path)if(is_file($path))unlink($path);
 if(is_file($file))unlink($file);
}
if($mode==='cleanup'){$fixture=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);cleanup();echo "PASS exact SEO fixture cleanup\n";exit;}
$failures=[];$checks=0;
function check(bool $ok,string $label):void{global $failures,$checks;++$checks;echo ($ok?'PASS ':'FAIL ').$label."\n";if(!$ok)$failures[]=$label;}
function denied(callable $run,string $label):void{$denied=false;try{$run();}catch(InvalidArgumentException){$denied=true;}check($denied,$label);}
remember();
try{
 if($mode==='aliases'){
  foreach(['product'=>'/tovar/','category'=>'/catalog/','brand'=>'/brand/','article'=>'/stati/'] as $type=>$prefix){
   $a=$fixture['tag'].'-'.$type.'-a';$b=$fixture['tag'].'-'.$type.'-b';$c=$fixture['tag'].'-'.$type.'-c';
   foreach([$a,$b,$c] as $slug)$fixture['redirects'][]=$prefix.$slug;remember();
   $id=saveRecord($type,$a);saveRecord($type,$b,$id);
   // Roll back each attempted takeover so subsequent probes see the original alias.
   $pdo->beginTransaction();denied(fn()=>saveRecord($type,$a),$type.' new record rejects historical alias');$pdo->rollBack();
   $other=saveRecord($type,$c);$pdo->beginTransaction();denied(fn()=>saveRecord($type,$a,$other),$type.' foreign rename rejects historical alias');$pdo->rollBack();
   saveRecord($type,$a,$id);saveRecord($type,$b,$id);
   check(scalar('SELECT new_path FROM cp_redirect WHERE old_path=?',[$prefix.$a])===$prefix.$b && !scalar('SELECT new_path FROM cp_redirect WHERE old_path=?',[$prefix.$b]),$type.' own rename-back reconciles direct redirects');
   if($type==='product'){
    $csv=dirname($file).'/'.$fixture['tag'].'.csv';file_put_contents($csv,"sku,name,slug\n".$fixture['tag']."-import,Synthetic,".$a."\n");$fixture['files'][]=$csv;remember();
    $import=new \Catpart\CatalogImport($pdo,dirname($file));$job=$import->stageFile($csv,'seo.csv',$fixture['actor']);$fixture['jobs'][]=$job['id'];$fixture['files'][]=$job['file_path'];remember();
    $job=$import->preview($job['id'],['sku','name','slug'],$fixture['actor']);
    check(($job['summary']['error']??0)===1,'import preview surfaces historical alias conflict');
    denied(fn()=>$import->apply($job['id'],$fixture['actor'],true),'import apply refuses historical alias conflict');
   }
  }
  // Preview was valid, then another record reserves its requested path before apply.
  $slug=$fixture['tag'].'-late';$csv=dirname($file).'/'.$fixture['tag'].'-late.csv';file_put_contents($csv,"sku,name,slug\n".$fixture['tag']."-late-import,Synthetic,".$slug."\n");$fixture['files'][]=$csv;remember();
  $import=new \Catpart\CatalogImport($pdo,dirname($file));$job=$import->stageFile($csv,'seo.csv',$fixture['actor']);$fixture['jobs'][]=$job['id'];$fixture['files'][]=$job['file_path'];remember();$job=$import->preview($job['id'],['sku','name','slug'],$fixture['actor']);
  check(empty($job['summary']['error']),'import initial preview valid');$id=saveRecord('product',$slug);$fixture['redirects'][]='/tovar/'.$slug;remember();saveRecord('product',$slug.'-current',$id);
  $job=$import->apply($job['id'],$fixture['actor'],true);check(($job['summary']['error']??0)===1,'import apply rechecks historical alias created after preview');
  if($failures)throw new RuntimeException(count($failures).' SEO alias regressions failed');
  echo $checks." SEO DB checks passed\n";
 }elseif($mode==='prepare'){
  $tag=$fixture['tag'];$fixture['public']=$tag.'-indexable';$fixture['hidden']=$tag.'-noindex';
  saveRecord('product',$fixture['public']);saveRecord('product',$fixture['hidden'],0,['noindex'=>1]);
  saveRecord('category',$fixture['public']);saveRecord('category',$fixture['hidden'],0,['noindex'=>1]);
  $fixture['old']='деталь-'.$tag;$fixture['current']=$tag.'-current';$id=saveRecord('product',$fixture['old']);$fixture['redirects'][]='/tovar/'.$fixture['old'];remember();saveRecord('product',$fixture['current'],$id);
  $fixture['current_paths']=[];$fixture['old_paths']=[];
  foreach(['product'=>'/tovar/','category'=>'/catalog/','brand'=>'/brand/','article'=>'/stati/'] as $type=>$prefix){
   $a=$tag.'-'.$type.'-a';$b=$tag.'-'.$type.'-b';$c=$tag.'-'.$type.'-c';$fixture['redirects'][]=$prefix.$a;$fixture['redirects'][]=$prefix.$b;remember();
   $id=saveRecord($type,$a);saveRecord($type,$b,$id);saveRecord($type,$a,$id);saveRecord($type,$c,$id);
   $fixture['current_paths'][]=$prefix.$c;$fixture['old_paths'][]=[$prefix.$a,$prefix.$c];$fixture['old_paths'][]=[$prefix.$b,$prefix.$c];
  }
  // Exactly one extra chunk of excluded fixtures exposes count/filter disagreement.
  $pdo->beginTransaction();$q=$pdo->prepare('INSERT INTO products(sku,sku_norm,name,slug,is_active) VALUES(?,?,?,?,1)');$m=$pdo->prepare("INSERT INTO cp_catalog_meta(entity,entity_id,data) VALUES('product',?,?)");
  for($i=0;$i<5000;$i++){$slug=$tag.'-bulk-'.$i;$q->execute([$slug,$slug,'SEO synthetic',$slug]);$id=(int)$pdo->lastInsertId();$fixture['records'][]=['product',$id];$m->execute([$id,'{"noindex":true}']);}
  remember();$pdo->commit();
  $fixture['active']=(int)scalar('SELECT COUNT(*) FROM products WHERE is_active=1');$indexable=0;
  foreach($pdo->query("SELECT m.data FROM products p LEFT JOIN cp_catalog_meta m ON m.entity='product' AND m.entity_id=p.id WHERE p.is_active=1") as $row){$meta=json_decode($row['data']??'{}',true);if(empty($meta['noindex']))++$indexable;}
  spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,'App\\')){$path=$root.'/local-dev/site/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($path))require_once $path;}});
  \App\Core\App::boot(['db'=>['host'=>DB_HOSTNAME,'port'=>(int)DB_PORT,'name'=>DB_DATABASE,'user'=>DB_USERNAME,'pass'=>DB_PASSWORD,'charset'=>'utf8mb4']]);
  check(\App\Models\Product::countActive()===$fixture['active'],'public active count retains noindex products');
  check(\App\Models\Product::countIndexable()===$indexable,'sitemap count matches independently decoded JSON metadata');
  if($failures)throw new RuntimeException('SEO model counts disagree');
  $fixture['chunks']=(int)ceil($indexable/5000);remember();echo "PASS synthetic SEO HTTP fixtures prepared\n";exit;
 }else throw new RuntimeException('Unknown SEO test mode');
}finally{if($pdo->inTransaction())$pdo->rollBack();if($mode!=='prepare')cleanup();}
