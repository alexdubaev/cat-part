<?php
declare(strict_types=1);
require dirname(__DIR__).'/tools/admin-data.php';
$phase=$argv[1]??'all';$file=$runtime.'/private/package-fixture.json';$checks=0;
function checkPackage(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function fixtureRead(string $file):array {return json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);}
function verifyFixture(array $f,PDO $pdo):void {
    $requests=new \Catpart\Requests($pdo);$product=(new \Catpart\Catalog($pdo))->record('product',$f['product']);
    checkPackage($product['name']===$f['name']&&$product['price']==='17.25'&&$product['sku']===$f['sku'],'update preserves product edits and identity');
    checkPackage(count($product['images'])===1&&$product['images'][0]['alt']==='Package alt'&&$product['images'][0]['caption']==='Package caption','update preserves image metadata');
    checkPackage(\Catpart\Factory::settings($pdo,$f['section'])===['retained'=>$f['tag']],'update preserves unknown settings sentinel');
    checkPackage((\Catpart\Factory::settings($pdo,'site')['phones'][0]['label']??'')==='Package phone fixture','update preserves edited public contact setting');
    checkPackage($pdo->query("SELECT value FROM oc_setting WHERE store_id=0 AND `key`='config_name' LIMIT 1")->fetchColumn()==='Package native name','update preserves edited native config_name');
    checkPackage($pdo->query('SELECT value FROM oc_setting WHERE setting_id='.(int)$f['native_setting'])->fetchColumn()===$f['tag'],'update preserves native settings');
    checkPackage($pdo->query('SELECT value FROM '.identifier($f['table']))->fetchColumn()===$f['tag'],'update preserves unknown table sentinel');
    foreach($f['orders'] as $id)checkPackage($requests->order($id)['snapshot'][0]['product_id']===$f['product']&&$requests->order($id)['snapshot'][0]['quantity']===150,'new native order and immutable identity snapshot survive');
    foreach($f['leads'] as $id)checkPackage($requests->lead($id)['message']===$f['tag'],'new lead survives update/code rollback');
}
function createRequests(array &$f,PDO $pdo):void {
    $requests=new \Catpart\Requests($pdo);$input=['name'=>'Package synthetic fixture','phone'=>'70000000000','email'=>'package@example.test','comment'=>$f['tag'],'consent'=>'1'];
    $order=$requests->submitOrder($f['owner'],bin2hex(random_bytes(32)),$input,[$f['product']=>150]);$f['orders'][]=$order['id'];
    $lead=$requests->submitLead($f['owner'],bin2hex(random_bytes(32)),$input,'Package test','/kontakty');$f['leads'][]=$lead['id'];
}
if($phase==='prepare'){
    if(is_file($file))throw new RuntimeException('Prior package test fixture exists; clean it explicitly before continuing.');
    $tag='package-'.bin2hex(random_bytes(6));$f=['tag'=>$tag,'table'=>'cp_package_'.bin2hex(random_bytes(6)),'section'=>$tag,'owner'=>bin2hex(random_bytes(32)),'name'=>'Package edit '.$tag,'sku'=>'000-'.$tag,'orders'=>[],'leads'=>[]];
    $catalog=new \Catpart\Catalog($pdo);$f['product']=$catalog->save('product',['name'=>$f['name'],'slug'=>$tag,'sku'=>$f['sku'],'status'=>'published','price_mode'=>'fixed','price'=>'17.25','currency'=>'RUB','in_stock'=>1,'images'=>[['url'=>'/assets/img/logo.webp','alt'=>'Package alt','caption'=>'Package caption']]],987654320);
    \Catpart\Factory::saveSettings($pdo,$f['section'],['retained'=>$tag],987654320);
    $f['site_original']=\Catpart\Factory::settings($pdo,'site');$editedSite=$f['site_original'];$editedSite['phones']=[['label'=>'Package phone fixture','tel'=>'+70000000000']];\Catpart\Factory::saveSettings($pdo,'site',$editedSite,987654320);
    $f['native_original']=$pdo->query("SELECT value FROM oc_setting WHERE store_id=0 AND `key`='config_name' LIMIT 1")->fetchColumn();$pdo->exec("UPDATE oc_setting SET value='Package native name' WHERE store_id=0 AND `key`='config_name'");
    $q=$pdo->prepare("INSERT INTO oc_setting(store_id,code,`key`,value,serialized) VALUES(0,'package_test',?,?,0)");$q->execute([$tag,$tag]);$f['native_setting']=(int)$pdo->lastInsertId();
    $pdo->exec('CREATE TABLE '.identifier($f['table']).' (value VARCHAR(100) PRIMARY KEY) ENGINE=InnoDB');$q=$pdo->prepare('INSERT INTO '.identifier($f['table']).' VALUES(?)');$q->execute([$tag]);
    $f['legacy_products']=$pdo->query('SELECT id,sku,slug FROM products WHERE is_active=1 AND id<>'.(int)$f['product'].' ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
    createRequests($f,$pdo);file_put_contents($file,json_encode($f,JSON_THROW_ON_ERROR));checkPackage(true,'persistent fixture with edit/native setting/unknown data/order/lead created');
}elseif($phase==='updated'){
    $f=fixtureRead($file);verifyFixture($f,$pdo);createRequests($f,$pdo);file_put_contents($file,json_encode($f,JSON_THROW_ON_ERROR));checkPackage(true,'new requests persisted after update before code rollback');
}elseif($phase==='restore'){
    $f=fixtureRead($file);verifyFixture($f,$pdo);$backup=backup($pdo,$runtime,DB_DATABASE);
    $restored=restoreNew($runtime,$backup);$restoreDb=$restored['database'];
    try{
        checkPackage(databaseDigest($restored['pdo'])===$restored['expected'],'private consistent streaming backup restores every table and row into fresh own schema');
        verifyFixture($f,$restored['pdo']);
        checkPackage((int)$restored['pdo']->query('SELECT COUNT(*) FROM oc_order')->fetchColumn()===(int)$pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn(),'restored native order count equals current stand');
    }finally{
        if(!is_file($runtime.'/private/restore-owned-'.$restoreDb)||$restoreDb===DB_DATABASE||!preg_match('/^catpart_admin_[a-f0-9]{12}$/D',$restoreDb))throw new RuntimeException('Restore cleanup identity failure.');
        $restored['pdo']->exec('DROP DATABASE '.identifier($restoreDb));unlink($runtime.'/private/restore-owned-'.$restoreDb);
    }
}elseif($phase==='cleanup'){
    $f=fixtureRead($file);
    foreach(['order'=>$f['orders'],'lead'=>$f['leads']] as $kind=>$ids)foreach($ids as $id){$id=(int)$id;foreach(['cp_request_key','cp_outbox','cp_request_history'] as $table)$pdo->exec('DELETE FROM '.$table.' WHERE kind='.$pdo->quote($kind).' AND entity_id='.$id);$pdo->exec('DELETE FROM cp_audit WHERE entity='.$pdo->quote($kind).' AND entity_id='.$pdo->quote((string)$id));if($kind==='order'){foreach(['oc_order_history','oc_order_product','cp_order','oc_order'] as $table)$pdo->exec('DELETE FROM '.$table.' WHERE order_id='.$id);}else $pdo->exec('DELETE FROM cp_lead WHERE id='.$id);}
    $id=(int)$f['product'];$imageIds=$pdo->query('SELECT id FROM product_images WHERE product_id='.$id)->fetchAll(PDO::FETCH_COLUMN);foreach($imageIds as $imageId)$pdo->exec('DELETE FROM cp_image_caption WHERE image_id='.(int)$imageId);foreach(['product_images','product_cross','product_attr_values','product_compat','cp_product_category','cp_product_relation'] as $table)$pdo->exec('DELETE FROM '.$table.' WHERE product_id='.$id);
    $pdo->exec('DELETE FROM products WHERE id='.$id);$pdo->exec("DELETE FROM cp_catalog_meta WHERE entity='product' AND entity_id=".$id);$pdo->exec('DELETE FROM cp_settings WHERE section='.$pdo->quote($f['section']));$pdo->exec('DELETE FROM oc_setting WHERE setting_id='.(int)$f['native_setting']);$pdo->exec('DROP TABLE '.identifier($f['table']));
    \Catpart\Factory::saveSettings($pdo,'site',$f['site_original'],987654320);$q=$pdo->prepare("UPDATE oc_setting SET value=? WHERE store_id=0 AND `key`='config_name'");$q->execute([$f['native_original']]);
    $pdo->exec("DELETE FROM cp_audit WHERE actor_id=987654320 AND ((entity='product' AND entity_id=".$pdo->quote((string)$id).") OR (entity='settings' AND entity_id IN ('site',".$pdo->quote($f['section']).')))');unlink($file);checkPackage(true,'only scoped package fixtures removed');
}else throw new RuntimeException('Run through test-admin-integration.py shipping group.');
echo $checks." package checks passed\n";
