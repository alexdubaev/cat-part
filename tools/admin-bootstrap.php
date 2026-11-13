<?php
declare(strict_types=1);
// Local-only schema bootstrap. Values arrive on stdin; never print credentials.
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
require_once $input['extension'].'/system/autoload.php';
$db=['host'=>'127.0.0.1','port'=>3307,'name'=>$input['database']];
\Catpart\Factory::assertLocal($db,$input['database']);
$root=new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name=$input['database'];
$root->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$root->exec("USE `$name`");$root->exec("SET time_zone='+00:00'");
$tables=['brands','categories','products','attributes','product_attr_values','product_images','product_compat','product_cross','blog_posts'];
$counts=[];
foreach($tables as $table){
    $root->exec("CREATE TABLE IF NOT EXISTS `$table` LIKE `local_catpart`.`$table`");
    $count=(int)$root->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    if($count===0)$root->exec("INSERT INTO `$table` SELECT * FROM `local_catpart`.`$table`");
    $counts[$table]=(int)$root->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}
// Use upstream's own schema metadata without invoking the destructive installer.
require_once $input['core'].'/system/helper/db_schema.php';
foreach(oc_db_schema() as $table){
    $sql='CREATE TABLE IF NOT EXISTS `oc_'.$table['name'].'` (';$fields=[];
    foreach($table['field'] as $field){
        $fields[]='`'.$field['name'].'` '.$field['type'].(!empty($field['not_null'])?' NOT NULL':'')
            .(isset($field['default'])?' DEFAULT '.$root->quote((string)$field['default']):'')
            .(!empty($field['auto_increment'])?' AUTO_INCREMENT':'');
    }
    if(isset($table['primary']))$fields[]='PRIMARY KEY (`'.implode('`,`',$table['primary']).'`)';
    foreach($table['index']??[] as $index)$fields[]='KEY `'.$index['name'].'` (`'.implode('`,`',$index['key']).'`)';
    $root->exec($sql.implode(',',$fields).') ENGINE='.$table['engine'].' CHARACTER SET '.$table['charset'].' COLLATE '.$table['collate'].' ROW_FORMAT=DYNAMIC');
}
// Only default system settings and reference dictionaries; never sample catalog, users or customers.
$seedTables=['setting','language','currency','country','zone','geo_zone','zone_to_geo_zone','length_class','length_class_description','weight_class','weight_class_description','order_status','stock_status','customer_group','customer_group_description'];
$file=$input['core'].'/install/opencart-en-gb.sql';
$sql='';$capturing=false;
foreach(file($file,FILE_IGNORE_NEW_LINES) as $line){
    if(str_starts_with($line,'INSERT INTO `oc_')){$sql='';$capturing=false;
        if(preg_match('/^INSERT INTO `oc_([a-z_]+)`/',$line,$m) && in_array($m[1],$seedTables,true)){
            $capturing=(int)$root->query('SELECT COUNT(*) FROM `oc_'.$m[1].'`')->fetchColumn()===0;
        }
    }
    if($capturing)$sql.=$line."\n";
    if($capturing && str_ends_with(trim($line),');')){$root->exec($sql);$capturing=false;}
}
$root->exec("SET SESSION sql_mode=''");
$settings=['config_name'=>'CatPart','config_store_name'=>'CatPart','config_url'=>$input['origin'].'/','config_language_admin'=>'ru-ru','config_language_catalog'=>'en-gb','config_timezone'=>'UTC','config_error_display'=>'0','config_error_log'=>'1','config_error_filename'=>'error.log','config_mail_engine'=>'','config_mail_smtp_hostname'=>'','config_mail_smtp_password'=>'','config_mail_parameter'=>'','config_session_expire'=>'3600','config_session_samesite'=>'Strict','config_icon'=>'','config_secure'=>'0','config_2fa'=>'0'];
$q=$root->prepare('DELETE FROM oc_setting WHERE store_id=0 AND `key`=?');
$ins=$root->prepare('INSERT INTO oc_setting(store_id,code,`key`,value,serialized) VALUES(0,\'config\',?,?,0)');
foreach($settings as $key=>$value){$q->execute([$key]);$ins->execute([$key,$value]);}
if(!(int)$root->query("SELECT COUNT(*) FROM oc_language WHERE code='ru-ru'")->fetchColumn()){
    $root->exec("INSERT INTO oc_language(name,code,extension,locale,status,sort_order) VALUES('Русский','ru-ru','catpart','ru_RU.UTF-8,ru_RU,ru',1,0)");
}
$areas=['foundation','dashboard','catalog','imports','content','settings','orders','leads','reports','maintenance'];
$common=['common/dashboard','common/login','common/logout','common/language','extension/catpart/module/foundation'];
$permissions=[
    'administrator'=>['access'=>array_merge($common,array_map(fn($v)=>'extension/catpart/module/'.$v,$areas),['user/user','user/user_permission','user/profile','tool/filemanager']), 'modify'=>array_merge(array_map(fn($v)=>'extension/catpart/module/'.$v,$areas),['user/user','user/user_permission','user/profile','tool/filemanager'])],
    'editor'=>['access'=>array_merge($common,['extension/catpart/module/catalog','extension/catpart/module/imports','extension/catpart/module/content']), 'modify'=>['extension/catpart/module/catalog','extension/catpart/module/imports','extension/catpart/module/content']],
    'manager'=>['access'=>array_merge($common,['extension/catpart/module/dashboard','extension/catpart/module/orders','extension/catpart/module/leads','extension/catpart/module/reports']), 'modify'=>['extension/catpart/module/orders','extension/catpart/module/leads']],
    'reader'=>['access'=>array_merge($common,['extension/catpart/module/content']), 'modify'=>[]],
];
$roleNames=['administrator'=>'Администратор','editor'=>'Редактор','manager'=>'Менеджер','reader'=>'Только просмотр'];
foreach($permissions as $role=>$perm){
    $id=array_search($role,array_keys($permissions),true)+1;
    $q=$root->prepare('INSERT INTO oc_user_group(user_group_id,name,permission) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),permission=VALUES(permission)');
    $q->execute([$id,$roleNames[$role],json_encode($perm,JSON_UNESCAPED_UNICODE)]);
    // Repair only a positively identified synthetic bootstrap account from an older local run.
    $old=$root->prepare('SELECT user_id,password FROM oc_user WHERE username=?');$old->execute([substr('catpart-'.$role,0,20)]);
    $oldRow=$old->fetch(PDO::FETCH_ASSOC);
    if($oldRow && password_verify($input['accounts'][$role]['password'],$oldRow['password'])){
        $rename=$root->prepare('UPDATE oc_user SET username=? WHERE user_id=?');$rename->execute([$input['accounts'][$role]['username'],$oldRow['user_id']]);
    }
    $q=$root->prepare('SELECT user_id FROM oc_user WHERE username=?');$q->execute([$input['accounts'][$role]['username']]);
    if(!$q->fetchColumn()){
        $q=$root->prepare("INSERT INTO oc_user(user_group_id,username,password,firstname,lastname,email,image,status,date_added) VALUES(?,?,?,?,?,'local-test@example.invalid','',1,UTC_TIMESTAMP())");
        $q->execute([$id,$input['accounts'][$role]['username'],password_hash($input['accounts'][$role]['password'],PASSWORD_DEFAULT),$roleNames[$role],'CatPart']);
    }
}
if(!(int)$root->query("SELECT COUNT(*) FROM oc_extension_install WHERE code='catpart'")->fetchColumn()){
    $root->exec("INSERT INTO oc_extension_install(extension_id,extension_download_id,name,code,version,author,link,status,date_added) VALUES(0,0,'CatPart','catpart','1.0.0','CatPart','https://github.com/alexdubaev/cat-part',1,UTC_TIMESTAMP())");
}
$events=[
 ['catpart_route','admin/controller/*/before','extension/catpart/event/shell.route',-1000],
 ['catpart_menu','admin/view/common/column_left/before','extension/catpart/event/shell.menu',1000],
 ['catpart_header','admin/view/common/header/after','extension/catpart/event/shell.header',1000],
];
foreach($events as [$code,$trigger,$action,$sort]){
    $q=$root->prepare('SELECT event_id FROM oc_event WHERE code=?');$q->execute([$code]);
    if(!$q->fetchColumn()){$q=$root->prepare('INSERT INTO oc_event(code,description,`trigger`,action,status,sort_order) VALUES(?,\'CatPart\',?,?,1,?)');$q->execute([$code,$trigger,$action,$sort]);}
}
\Catpart\Factory::migrate($root);
// Dedicated account has privileges only on this isolated schema.
$account='cp_'.substr($name,-12);$password=$input['db_password'];
$root->exec("CREATE USER IF NOT EXISTS ".$root->quote($account)."@'127.0.0.1' IDENTIFIED BY ".$root->quote($password));
$root->exec("GRANT ALL PRIVILEGES ON `$name`.* TO ".$root->quote($account)."@'127.0.0.1'");
echo json_encode(['database'=>$name,'counts'=>$counts,'native_products'=>(int)$root->query('SELECT COUNT(*) FROM oc_product')->fetchColumn()],JSON_THROW_ON_ERROR);
