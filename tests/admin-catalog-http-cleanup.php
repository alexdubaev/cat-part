<?php
declare(strict_types=1);
$runtime=getenv('CATPART_ADMIN_RUNTIME')?:dirname(__DIR__).'/local-dev/admin-runtime';$state=json_decode(file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^catpart_admin_[a-f0-9]{12}$/D',$state['database']))throw new RuntimeException('Isolated DB required.');
require $runtime.'/core/admin/config.php';\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],$state['database']);$pdo=\Catpart\Factory::pdo();$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$tag=$input['tag']??'';
if(!preg_match('/^cp-http-[a-f0-9]{10}$/D',$tag))throw new RuntimeException('Fixture tag required.');
$pdo->beginTransaction();try{
 foreach(['product'=>'products','category'=>'categories','brand'=>'brands'] as $type=>$table){$q=$pdo->prepare('SELECT id FROM '.$table.' WHERE slug LIKE ?');$q->execute([$tag.'%']);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){
  if($type==='product'){$images=$pdo->prepare('SELECT id FROM product_images WHERE product_id=?');$images->execute([$id]);foreach($images->fetchAll(PDO::FETCH_COLUMN) as $image)$pdo->prepare('DELETE FROM cp_image_caption WHERE image_id=?')->execute([$image]);foreach(['product_images','product_attr_values','product_cross','cp_product_category','cp_product_relation'] as $related)$pdo->prepare('DELETE FROM '.$related.' WHERE product_id=?')->execute([$id]);}
  $pdo->prepare('DELETE FROM cp_catalog_meta WHERE entity=? AND entity_id=?')->execute([$type,$id]);$pdo->prepare('DELETE FROM cp_audit WHERE entity=? AND entity_id=?')->execute([$type,(string)$id]);$pdo->prepare('DELETE FROM '.$table.' WHERE id=?')->execute([$id]);
 }}
 $jobs=$pdo->prepare('SELECT id,file_path FROM cp_import WHERE filename=?');$jobs->execute([$tag.'.csv']);foreach($jobs as $job){if(str_starts_with(str_replace('\\','/',$job['file_path']),str_replace('\\','/',$runtime).'/'))@unlink($job['file_path']);$pdo->prepare('DELETE FROM cp_import_row WHERE import_id=?')->execute([$job['id']]);$pdo->prepare('DELETE FROM cp_import WHERE id=?')->execute([$job['id']]);$pdo->prepare("DELETE FROM cp_audit WHERE entity='import' AND entity_id=?")->execute([$job['id']]);}
 foreach($input['media']??[] as $file)if(preg_match('~^/media/catalog/[a-f0-9]{32}\\.(webp|png|jpg|gif)$~D',$file))@unlink(dirname(__DIR__).'/local-dev/site'.$file);
 $pdo->prepare('DELETE FROM cp_redirect WHERE old_path LIKE ?')->execute(['%'.$tag.'%']);$pdo->commit();echo "Fixture cleanup complete\n";
}catch(Throwable $e){$pdo->rollBack();throw $e;}
