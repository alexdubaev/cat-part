<?php
declare(strict_types=1);
$root=dirname(__DIR__);$runtime=getenv('CATPART_ADMIN_RUNTIME')?:$root.'/local-dev/admin-runtime';
$state=json_decode(file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
require_once $root.'/opencart/extension/catpart/system/autoload.php';require $runtime.'/core/admin/config.php';
\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],$state['database']);
$pdo=\Catpart\Factory::pdo();$catalog=new \Catpart\Catalog($pdo);$mode=$argv[1]??'prepare';
$file=getenv('CATPART_ACCEPTANCE_FIXTURE');if(!$file || dirname(str_replace('\\','/',$file))!==rtrim(str_replace('\\','/',$runtime),'/').'/private' || !preg_match('/^acceptance-[a-f0-9]{16}\.json$/D',basename($file)))throw new RuntimeException('Unique private fixture required');
if($mode==='prepare'){
 $f=['tag'=>'acceptance-'.bin2hex(random_bytes(8)),'actor'=>random_int(1800000000,1999999999),'records'=>[],'jobs'=>[],'settings'=>[]];
 foreach(['page_seo'] as $section)$f['settings'][$section]=\Catpart\Factory::settings($pdo,$section);
 $f['counts']=[];foreach(['products','categories','product_images','product_cross','product_attr_values','cp_image_caption','cp_product_category','cp_product_relation','cp_catalog_meta','cp_import','cp_import_row','cp_audit'] as $table)$f['counts'][$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
 foreach(['category','product','product'] as $i=>$type){$id=$catalog->save($type,['name'=>$f['tag'].' '.$i,'sku'=>'00'.$f['tag'].'-'.$i,'slug'=>$f['tag'].'-'.$i,'status'=>'published','image_url'=>'/assets/img/contacts.webp','body_html'=>'<p>Synthetic acceptance description</p>','cross'=>$type==='product'?[['cross_sku'=>'00.CROSS-'.$f['tag'],'kind'=>'oem']]:[]],$f['actor']);$f['records'][]=[$type,$id];}
 $f['audit_start']=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM cp_audit')->fetchColumn();$f['content']=[];foreach($pdo->query("SELECT content_key,body FROM cp_content WHERE content_key LIKE 'product.%'") as $r)$f['content'][$r['content_key']]=$r['body'];
 file_put_contents($file,json_encode($f,JSON_THROW_ON_ERROR));echo "PASS prepared unique acceptance fixtures\n";exit;
}
$f=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if($mode==='cleanup'){
 // Remove only audit rows for this fixture's exact IDs and the settings/keys exercised here.
 $allowed=[['settings','page_seo']];foreach($f['records'] as $r)$allowed[]=$r;foreach(array_keys($f['content']) as $key)$allowed[]=['content',$key];foreach($allowed as [$entity,$id])$pdo->prepare('DELETE FROM cp_audit WHERE id>? AND entity=? AND entity_id=?')->execute([$f['audit_start'],$entity,$id]);
 foreach($f['settings'] as $section=>$data)\Catpart\Factory::saveSettings($pdo,$section,$data,$f['actor']);
 if($f['content'])(new \Catpart\Content($pdo))->saveFields($f['content'],$f['actor']);
 foreach(array_reverse($f['records']) as [$type,$id]){if($type==='product'){$pdo->prepare('DELETE FROM cp_image_caption WHERE image_id IN(SELECT id FROM product_images WHERE product_id=?)')->execute([$id]);foreach(['product_images','product_cross','product_attr_values','cp_product_category'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE product_id=?')->execute([$id]);$pdo->prepare('DELETE FROM cp_product_relation WHERE product_id=? OR related_id=?')->execute([$id,$id]);}$pdo->prepare('DELETE FROM cp_catalog_meta WHERE entity=? AND entity_id=?')->execute([$type,$id]);$pdo->prepare('DELETE FROM '.($type==='product'?'products':'categories').' WHERE id=?')->execute([$id]);}
 foreach($pdo->query('SELECT id,file_path FROM cp_import WHERE actor_id='.(int)$f['actor']) as $r){$pdo->prepare('DELETE FROM cp_import_row WHERE import_id=?')->execute([$r['id']]);$pdo->prepare('DELETE FROM cp_import WHERE id=?')->execute([$r['id']]);if(is_file($r['file_path']))unlink($r['file_path']);}
 $pdo->prepare('DELETE FROM cp_audit WHERE actor_id=?')->execute([$f['actor']]);foreach($f['counts'] as $table=>$count)if((int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()!==$count)throw new RuntimeException('Fixture count not restored: '.$table);unlink($file);echo "PASS exact acceptance fixture cleanup\n";exit;
}
if($mode==='cross'){
 $r=$catalog->listing('product','00 cross '.$f['tag']);if($r['total']!==2)throw new RuntimeException('Admin normalized cross lookup missed two canonical candidates');
 $path=$runtime.'/private/'.$f['tag'].'.csv';file_put_contents($path,"sku,name\n00.CROSS-".$f['tag'].",Synthetic import\n");
 try{$import=new \Catpart\CatalogImport($pdo);$job=$import->stageFile($path,'acceptance.csv',$f['actor']);$job=$import->preview($job['id'],[0=>'sku',1=>'name'],$f['actor']);$rows=$import->report($job['id'],$f['actor']);if($job['summary']['error']!==1 || !str_contains($rows[0]['message'],'ID'))throw new RuntimeException('Cross import silently proposed new SKU');}finally{unlink($path);}
 echo "PASS normalized admin cross candidates and ambiguous import rejection\n";
}
