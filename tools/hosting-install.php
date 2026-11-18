<?php
declare(strict_types=1);
require_once __DIR__.'/hosting-runtime.php';

/** Hosting installation is separate from every localhost bootstrap and guard. */
function catpart_hosting_config(string $site):array {
    $file=$site.'/app/Config/config.php';
    if(!is_file($file) || is_link($file))throw new RuntimeException('Existing site configuration required.');
    $config=(array)require $file;$local=$site.'/app/Config/config.local.php';
    if(is_file($local)){if(is_link($local))throw new RuntimeException('Configuration symlink refused.');$config=array_replace_recursive($config,(array)require $local);}
    return $config;
}
function catpart_hosting_public_sources(string $source):array {
    $files=[];$root=$source.'/local-dev/site';
    foreach(['app','assets'] as $directory){
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $entry){if($entry->isLink())throw new RuntimeException('Symlink in deployment source.');if(!$entry->isFile())continue;
            $relative=substr(str_replace('\\','/',$entry->getPathname()),strlen($root)+1);
            if(str_starts_with($relative,'app/Config/') || $relative==='app/bootstrap.php')continue;
            $files[$relative]=$entry->getPathname();
        }
    }
    foreach(['index.php','manifest.webmanifest'] as $name){if(!is_file($root.'/'.$name))throw new RuntimeException('Incomplete public source.');$files[$name]=$root.'/'.$name;}
    ksort($files);return $files;
}
function catpart_hosting_source_hash(string $source):string {
    $files=catpart_hosting_public_sources($source);
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source.'/opencart/extension/catpart',FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $entry){if($entry->isLink())throw new RuntimeException('Symlink in extension source.');if($entry->isFile())$files[substr(str_replace('\\','/',$entry->getPathname()),strlen($source)+1)]=$entry->getPathname();}
    foreach(['hosting-runtime.php','hosting-install.php','send-hosting-notifications.php'] as $name)$files['tools/'.$name]=$source.'/tools/'.$name;
    ksort($files);$hash=hash_init('sha256');foreach($files as $name=>$path){if(!is_file($path))throw new RuntimeException('Incomplete hosting source.');hash_update($hash,$name."\0".hash_file('sha256',$path)."\n");}return hash_final($hash);
}
function catpart_hosting_identity(array $options):array {
    foreach(['site','runtime','source','upstream','origin','database'] as $key)if(!is_string($options[$key]??null) || $options[$key]==='')throw new RuntimeException('Explicit hosting identity required.');
    [$site,$runtime]=catpart_hosting_paths($options['site'],$options['runtime']);$source=catpart_hosting_directory($options['source']);
    if(catpart_hosting_inside($source,$site) || catpart_hosting_inside($source,$runtime))throw new RuntimeException('Source must be separate from live and private runtime.');
    $origin=rtrim($options['origin'],'/');$url=parse_url($origin);
    if(!$url || ($url['scheme']??'')!=='https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !empty($url['path']) || in_array(strtolower($url['host']),['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Exact HTTPS site origin required.');
    if(!preg_match('/^[a-zA-Z0-9_]+$/D',$options['database']) || preg_match('/^(local_catpart|catpart_admin_[a-f0-9]{12})$/D',$options['database']))throw new RuntimeException('Production database identity required.');
    if(!is_file($options['upstream']) || !hash_equals(CATPART_HOSTING_CORE_SHA256,(string)hash_file('sha256',$options['upstream'])))throw new RuntimeException('Pinned upstream archive required.');
    if(is_link($site.'/app'))throw new RuntimeException('Live application symlink refused.');catpart_hosting_live_path($site,'app/Config/config.php');catpart_hosting_live_path($site,'app/Config/config.local.php');
    $config=catpart_hosting_config($site);
    if(rtrim((string)($config['app']['base_url']??''),'/')!==$origin || ($config['db']['name']??'')!==$options['database'])throw new RuntimeException('Site configuration does not match explicit identity.');
    $digest=catpart_hosting_source_hash($source);
    if(isset($options['source-sha256']) && !hash_equals($digest,(string)$options['source-sha256']))throw new RuntimeException('Deployment source digest mismatch.');
    return ['format'=>1,'site_root'=>$site,'runtime_root'=>$runtime,'source_root'=>$source,'origin'=>$origin,'database'=>$options['database'],'database_host'=>(string)$config['db']['host'],'database_port'=>(int)$config['db']['port'],'source_sha256'=>$digest,'upstream_sha256'=>CATPART_HOSTING_CORE_SHA256];
}
function catpart_hosting_pdo(array $identity):PDO {
    $db=catpart_hosting_config($identity['site_root'])['db'];
    $pdo=new PDO('mysql:host='.$db['host'].';port='.(int)$db['port'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone='+00:00'");
    if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$identity['database'])throw new RuntimeException('Connected database identity mismatch.');return $pdo;
}
function catpart_hosting_state(array $identity):?array {
    $path=$identity['runtime_root'].'/hosting-state.json';if(!is_file($path))return null;
    if(is_link($path))throw new RuntimeException('Manifest symlink refused.');$state=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    foreach($identity as $key=>$value)if(($state[$key]??null)!==$value)throw new RuntimeException('Existing installation identity mismatch.');return $state;
}
function catpart_hosting_save(array $state):void {catpart_hosting_put($state['runtime_root'],'hosting-state.json',json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");}
function catpart_hosting_lock(PDO $pdo,callable $operation):mixed {
    $lock='cp-hosting-'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,40);$q=$pdo->prepare('SELECT GET_LOCK(?,10)');$q->execute([$lock]);
    if((int)$q->fetchColumn()!==1)throw new RuntimeException('Another hosting operation is running.');
    try{return $operation();}finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
}
function catpart_hosting_tables(PDO $pdo):array {$rows=$pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);$out=[];foreach($rows as $row)$out[$row[0]]=$row[1];return $out;}
function catpart_hosting_dates(PDO $pdo):void {
    $q=$pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='blog_posts'");if(strtoupper((string)$q->fetchColumn())!=='INNODB')throw new RuntimeException('Article UTC conversion requires InnoDB.');
    $columns=$pdo->query('SHOW COLUMNS FROM blog_posts')->fetchAll();$types=[];foreach($columns as $column)$types[$column['Field']]=strtolower($column['Type']);
    foreach(['published_at','created_at'] as $column){if(!preg_match('/^(datetime|timestamp)(?:\([0-6]\))?$/D',$types[$column]??''))throw new RuntimeException('Article dates must be legacy DATETIME or TIMESTAMP columns.');
        if(str_starts_with($types[$column],'datetime') && (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE `$column` IS NOT NULL AND (`$column`<'1000-01-01 03:00:00' OR DATE_SUB(`$column`,INTERVAL 3 HOUR) IS NULL)")->fetchColumn())throw new RuntimeException('Invalid legacy article date prevents safe UTC conversion.');}
}
function catpart_hosting_schema_fingerprint(PDO $pdo):array {
    $fingerprints=[];foreach(catpart_hosting_tables($pdo) as $name=>$kind){if(!preg_match('/^(oc_|cp_)/D',$name))continue;if($kind!=='BASE TABLE')throw new RuntimeException('Unexpected extension view.');
        $columns=$pdo->query('SHOW COLUMNS FROM `'.$name.'`')->fetchAll();$indexes=$pdo->query('SHOW INDEX FROM `'.$name.'`')->fetchAll();
        foreach($indexes as &$index)unset($index['Cardinality']);unset($index);
        $q=$pdo->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$name]);
        $fingerprints[$name]=hash('sha256',json_encode([$columns,$indexes,$q->fetch()],JSON_THROW_ON_ERROR));}
    ksort($fingerprints);return $fingerprints;
}
function catpart_hosting_preflight(PDO $pdo,array $identity):array {
    $state=catpart_hosting_state($identity);$tables=catpart_hosting_tables($pdo);
    foreach(['brands','categories','products','attributes','product_attr_values','product_images','product_compat','product_cross','blog_posts'] as $table){if(($tables[$table]??'')!=='BASE TABLE')throw new RuntimeException('Existing canonical catalog schema required.');
        $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if(strtoupper((string)$q->fetchColumn())!=='INNODB')throw new RuntimeException('Transactional canonical catalog tables required.');}
    catpart_hosting_dates($pdo);
    foreach(['image','upload','media'] as $media)catpart_hosting_live_path($identity['site_root'],$media.'/.htaccess');
    if($state===null){foreach($tables as $name=>$kind)if(preg_match('/^(oc_|cp_)/D',$name))throw new RuntimeException('Unowned OpenCart/CatPart table collision.');
        foreach(['admin','extension/catpart','image'] as $relative)if(file_exists($identity['site_root'].'/'.$relative) || is_link($identity['site_root'].'/'.$relative))throw new RuntimeException('Unowned public admin path collision.');
        $entries=array_diff(scandir($identity['runtime_root']),['.','..']);if($entries)throw new RuntimeException('Initial private runtime must be empty.');
    }else{
        if(($state['phase']??'')==='preparing'){
            foreach($tables as $name=>$kind)if(preg_match('/^(oc_|cp_)/D',$name) && ($kind!=='BASE TABLE' || !isset($state['table_journal'][$name])))throw new RuntimeException('Unjournaled table appeared during preparation.');
        }else{
            if(!in_array($state['phase']??'',['prepared','active','disabled','activating','activation_failed'],true))throw new RuntimeException('Unknown installation phase.');
            if(($state['schema']??[])!==catpart_hosting_schema_fingerprint($pdo))throw new RuntimeException('Owned extension schema changed; inspect before updating.');
        }
    }
    return ['ready'=>true,'phase'=>$state['phase']??'new','source_sha256'=>$identity['source_sha256']];
}
function catpart_hosting_native_statements(PDO $pdo,string $core):array {
    require_once $core.'/system/helper/db_schema.php';
    $statements=[];
    foreach(oc_db_schema() as $table){$fields=[];
        foreach($table['field'] as $field)$fields[]='`'.$field['name'].'` '.$field['type'].(!empty($field['not_null'])?' NOT NULL':'').(isset($field['default'])?' DEFAULT '.$pdo->quote((string)$field['default']):'').(!empty($field['auto_increment'])?' AUTO_INCREMENT':'');
        if(isset($table['primary']))$fields[]='PRIMARY KEY (`'.implode('`,`',$table['primary']).'`)';
        foreach($table['index']??[] as $index)$fields[]='KEY `'.$index['name'].'` (`'.implode('`,`',$index['key']).'`)';
        $statements['oc_'.$table['name']]='CREATE TABLE `oc_'.$table['name'].'` ('.implode(',',$fields).') ENGINE='.$table['engine'].' CHARACTER SET '.$table['charset'].' COLLATE '.$table['collate'].' ROW_FORMAT=DYNAMIC';
    }
    return $statements;
}
/** These pinned extension migrations contain literal single-line CREATE statements only. */
function catpart_hosting_extension_statements(string $extension):array {
    $statements=[];foreach(['Factory','Catalog','CatalogImport','Content','Requests','Outbox'] as $class){$source=(string)file_get_contents($extension.'/catpart/system/'.$class.'.php');preg_match_all('/->exec\("(CREATE TABLE IF NOT EXISTS ([a-z_]+)(?=\s|\()[^"\r\n]+)"\)/',$source,$matches,PREG_SET_ORDER);
        foreach($matches as $match){if(!str_starts_with($match[2],'cp_') || str_contains($match[1],'$') || str_contains($match[1],'\\'))throw new RuntimeException('Unsupported extension schema declaration.');$statements[$match[2]]=str_replace('CREATE TABLE IF NOT EXISTS ','CREATE TABLE ',$match[1]);}}
    if(count($statements)!==17)throw new RuntimeException('Pinned extension schema declaration count changed.');return $statements;
}
function catpart_hosting_table_shape(PDO $pdo,string $name):array {
    $columns=$pdo->query('SHOW COLUMNS FROM `'.$name.'`')->fetchAll();$indexes=$pdo->query('SHOW INDEX FROM `'.$name.'`')->fetchAll();foreach($indexes as &$index){unset($index['Table'],$index['Cardinality']);}unset($index);
    $create=(string)$pdo->query('SHOW CREATE TABLE `'.$name.'`')->fetch(PDO::FETCH_NUM)[1];$create=preg_replace('/^CREATE(?: TEMPORARY)? TABLE `[^`]+`/','CREATE TABLE <owned>',$create);$create=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$create);
    return [$columns,$indexes,$create];
}
/** Reserve exact DDL before execution; a retry can only adopt this operation's matching table. */
function catpart_hosting_journal_table(PDO $pdo,array &$state,string $name,string $sql):void {
    $digest=hash('sha256',$sql);$tables=catpart_hosting_tables($pdo);
    if(isset($state['table_journal'][$name]) && !hash_equals($state['table_journal'][$name],$digest))throw new RuntimeException('Owned table declaration changed.');
    if(isset($tables[$name]) && (!isset($state['table_journal'][$name]) || $tables[$name]!=='BASE TABLE'))throw new RuntimeException('Unowned schema collision during preparation.');
    if(!isset($state['table_journal'][$name])){$state['table_journal'][$name]=$digest;catpart_hosting_save($state);}
    if(!isset($tables[$name]))$pdo->exec($sql);
    $temporary='cp_verify_'.bin2hex(random_bytes(6));$temporarySql=preg_replace('/^CREATE TABLE `?'.preg_quote($name,'/').'`?(?=\s|\()/','CREATE TEMPORARY TABLE `'.$temporary.'`',$sql,1,$changed);if($changed!==1)throw new RuntimeException('Cannot verify owned table declaration.');
    try{$pdo->exec($temporarySql);if(catpart_hosting_table_shape($pdo,$name)!==catpart_hosting_table_shape($pdo,$temporary))throw new RuntimeException('Owned schema mismatch during preparation.');}
    finally{$pdo->exec('DROP TEMPORARY TABLE IF EXISTS `'.$temporary.'`');}
    preg_match('/ENGINE=([a-zA-Z0-9_]+)/',$sql,$engine);$q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$name]);if(strcasecmp((string)$q->fetchColumn(),$engine[1]??'')!==0)throw new RuntimeException('Owned table engine changed.');
}
function catpart_hosting_native_dictionaries(PDO $pdo,string $core):void {
    $allow=['setting','currency','country','zone','geo_zone','zone_to_geo_zone','length_class','length_class_description','weight_class','weight_class_description','order_status','stock_status','customer_group','customer_group_description'];
    $sql='';$capture=false;foreach(file($core.'/install/opencart-en-gb.sql',FILE_IGNORE_NEW_LINES) as $line){
        if(str_starts_with($line,'INSERT INTO `oc_')){$sql='';$capture=preg_match('/^INSERT INTO `oc_([a-z_]+)`/',$line,$match) && in_array($match[1],$allow,true);}
        if($capture)$sql.=$line."\n";if($capture && str_ends_with(trim($line),');')){$pdo->exec($sql);$capture=false;}
    }
    // ID 1 is also used by Requests' native compatibility projection.
    $pdo->exec("INSERT INTO oc_language(language_id,name,code,extension,locale,status,sort_order) VALUES(1,'Русский','ru-ru','catpart','ru_RU.UTF-8,ru_RU,ru',1,0)");
}
function catpart_hosting_native_config(array $identity):void {
    $runtime=$identity['runtime_root'];$site=$identity['site_root'];$core=$runtime.'/core';$storage=$runtime.'/private/storage';
    $values=['APPLICATION'=>'Admin','HTTP_SERVER'=>$identity['origin'].'/admin/','HTTP_CATALOG'=>$identity['origin'].'/','DIR_OPENCART'=>$core.'/','DIR_APPLICATION'=>$core.'/admin/','DIR_EXTENSION'=>$runtime.'/extension/','DIR_SYSTEM'=>$core.'/system/','DIR_IMAGE'=>$site.'/image/','DIR_STORAGE'=>$storage.'/','DIR_LANGUAGE'=>$core.'/admin/language/','DIR_TEMPLATE'=>$core.'/admin/view/template/','DIR_CONFIG'=>$core.'/system/config/','DIR_CACHE'=>$storage.'/cache/','DIR_DOWNLOAD'=>$storage.'/download/','DIR_LOGS'=>$storage.'/logs/','DIR_SESSION'=>$storage.'/session/','DIR_UPLOAD'=>$storage.'/upload/','DB_DRIVER'=>'mysqli','DB_PREFIX'=>'oc_'];
    $config="<?php\ndeclare(strict_types=1);\n";foreach($values as $key=>$value)$config.='define('.var_export($key,true).','.var_export($value,true).");\n";
    $config.='$config=require '.var_export($site.'/app/Config/config.php',true).";\n".'$local='.var_export($site.'/app/Config/config.local.php',true).";\n".'if(is_file($local))$config=array_replace_recursive($config,(array)require $local);'."\n";
    foreach(['DB_HOSTNAME'=>'host','DB_USERNAME'=>'user','DB_PASSWORD'=>'pass','DB_DATABASE'=>'name','DB_PORT'=>'port'] as $constant=>$key)$config.='define('.var_export($constant,true).',$config[\'db\']['.var_export($key,true)."]);\n";
    $config.='unset($config,$local); require_once '.var_export($runtime.'/extension/catpart/system/autoload.php',true).";\n";
    catpart_hosting_put($runtime,'core/admin/config.php',$config);
}
function catpart_hosting_prepare(PDO $pdo,array $identity,string $archive,?callable $checkpoint=null):array {
    return catpart_hosting_lock($pdo,function()use($pdo,$identity,$archive,$checkpoint):array {
        $report=catpart_hosting_preflight($pdo,$identity);$existing=catpart_hosting_state($identity);if($existing!==null && $existing['phase']!=='preparing')return $report;
        $state=$existing??($identity+['phase'=>'preparing','created_at'=>gmdate('c'),'table_journal'=>[]]);catpart_hosting_save($state);
        $prepared=catpart_hosting_prepare_runtime($identity['site_root'],$identity['runtime_root'],$identity['source_root'],$archive);
        require_once $prepared['extension'].'/catpart/system/autoload.php';
        if($checkpoint)$checkpoint('before_ddl');$statements=catpart_hosting_native_statements($pdo,$prepared['core'])+catpart_hosting_extension_statements($prepared['extension']);
        foreach(array_keys($state['table_journal']) as $name)if(!isset($statements[$name]))throw new RuntimeException('Unknown owned table declaration.');
        $number=0;foreach($statements as $name=>$sql){catpart_hosting_journal_table($pdo,$state,$name,$sql);$number++;if($checkpoint && $number===5)$checkpoint('during_ddl');}
        $pdo->exec("SET SESSION sql_mode='NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
        $config=catpart_hosting_config($identity['site_root']);$brand=require $identity['site_root'].'/app/Config/branding.php';$email=(string)($config['mail']['from_email']??$brand['email']??'');if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Valid owner email required from existing site configuration.');
        $accountPath=$identity['runtime_root'].'/private/owner-account.json';
        if(is_file($accountPath)){if(is_link($accountPath))throw new RuntimeException('Owner credential symlink refused.');$account=json_decode((string)file_get_contents($accountPath),true,512,JSON_THROW_ON_ERROR);if(($account['username']??'')!=='cp-owner' || !preg_match('/^[a-f0-9]{48}$/D',$account['password']??''))throw new RuntimeException('Private owner credential mismatch.');}
        else{$account=['username'=>'cp-owner','password'=>bin2hex(random_bytes(24))];catpart_hosting_put($identity['runtime_root'],'private/owner-account.json',json_encode($account,JSON_THROW_ON_ERROR)."\n");}
        $seed=\Catpart\Factory::settings($pdo,'hosting_native_seed');
        if($seed===[]){$pdo->beginTransaction();try{
        catpart_hosting_native_dictionaries($pdo,$prepared['core']);
        $settings=['config_name'=>'CatPart','config_store_name'=>'CatPart','config_url'=>$identity['origin'].'/','config_language_admin'=>'ru-ru','config_language_catalog'=>'ru-ru','config_timezone'=>'UTC','config_error_display'=>'0','config_error_log'=>'1','config_error_filename'=>'error.log','config_mail_engine'=>'','config_session_expire'=>'3600','config_session_samesite'=>'Strict','config_secure'=>'1','config_2fa'=>'0'];
        $delete=$pdo->prepare('DELETE FROM oc_setting WHERE store_id=0 AND `key`=?');$insert=$pdo->prepare("INSERT INTO oc_setting(store_id,code,`key`,value,serialized) VALUES(0,'config',?,?,0)");foreach($settings as $key=>$value){$delete->execute([$key]);$insert->execute([$key,$value]);}
        $areas=['foundation','dashboard','catalog','imports','content','settings','orders','leads','reports','maintenance'];$routes=array_map(static fn($area)=>'extension/catpart/module/'.$area,$areas);
        $permissions=['access'=>array_merge(['common/dashboard','common/login','common/logout','common/language','user/user','user/user_permission','user/profile','tool/filemanager'],$routes),'modify'=>array_merge(['user/user','user/user_permission','user/profile','tool/filemanager'],$routes)];
        $q=$pdo->prepare('INSERT INTO oc_user_group(name,permission) VALUES(?,?)');$q->execute(['Администратор CatPart',json_encode($permissions,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$group=(int)$pdo->lastInsertId();
        if((int)$pdo->query('SELECT COUNT(*) FROM oc_user')->fetchColumn()!==0)throw new RuntimeException('Unseeded native account collision.');
        $q=$pdo->prepare("INSERT INTO oc_user(user_group_id,username,password,firstname,lastname,email,image,status,date_added) VALUES(?,?,?,'Владелец','CatPart',?,'',1,UTC_TIMESTAMP())");$q->execute([$group,$account['username'],password_hash($account['password'],PASSWORD_DEFAULT),$email]);$owner=(int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO oc_extension_install(extension_id,extension_download_id,name,code,version,author,link,status,date_added) VALUES(0,0,'CatPart','catpart','1.0.0','CatPart','https://github.com/alexdubaev/cat-part',1,UTC_TIMESTAMP())");
        $q=$pdo->prepare("INSERT INTO oc_event(code,description,`trigger`,action,status,sort_order) VALUES(?,'CatPart',?,?,1,?)");foreach([['catpart_route','admin/controller/*/before','extension/catpart/event/shell.route',-1000],['catpart_menu','admin/view/common/column_left/before','extension/catpart/event/shell.menu',1000],['catpart_header','admin/view/common/header/after','extension/catpart/event/shell.header',1000]] as $event)$q->execute($event);
        $seed=['source_sha256'=>$identity['source_sha256'],'owner_id'=>$owner,'owner_group_id'=>$group];\Catpart\Factory::saveSettings($pdo,'hosting_native_seed',$seed,0);$pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}}
        if(($seed['source_sha256']??'')!==$identity['source_sha256'])throw new RuntimeException('Native seed ownership mismatch.');$owner=(int)$seed['owner_id'];$group=(int)$seed['owner_group_id'];
        $q=$pdo->prepare('SELECT username,password,user_group_id FROM oc_user WHERE user_id=?');$q->execute([$owner]);$ownerRow=$q->fetch();if(!$ownerRow || $ownerRow['username']!==$account['username'] || (int)$ownerRow['user_group_id']!==$group || !password_verify($account['password'],$ownerRow['password']))throw new RuntimeException('Owned native account no longer matches private credential.');unset($account,$ownerRow);
        if($checkpoint)$checkpoint('after_owner');
        \Catpart\Factory::migrate($pdo);\Catpart\Catalog::migrate($pdo);\Catpart\Content::migrate($pdo);\Catpart\Requests::migrate($pdo);
        $mail=[];foreach(['notify','from_email','from_name'] as $key)if(array_key_exists($key,$config['mail']??[]))$mail[$key]=$config['mail'][$key];
        if($mail && \Catpart\Factory::settings($pdo,'mail')===[])\Catpart\Factory::saveSettings($pdo,'mail',$mail,0);
        catpart_hosting_native_config($identity);catpart_hosting_write_bridge($identity['site_root'],$identity['runtime_root'],$prepared['extension']);
        foreach(catpart_hosting_public_sources($identity['source_root']) as $relative=>$path){if(str_starts_with($relative,'assets/img/') && is_file($identity['site_root'].'/'.$relative))continue;catpart_hosting_put($identity['runtime_root'],'stage/public/'.$relative,(string)file_get_contents($path));}
        catpart_hosting_copy_tree($prepared['public'],$identity['runtime_root'],'stage/public');
        catpart_hosting_put($identity['runtime_root'],'stage/public/app/bootstrap.php',(string)file_get_contents($identity['runtime_root'].'/bridge/app/bootstrap.php'));
        catpart_hosting_put($identity['runtime_root'],'stage/public/admin/index.php',(string)file_get_contents($identity['runtime_root'].'/bridge/admin/index.php'));
        catpart_hosting_put($identity['runtime_root'],'stage/public/admin/.htaccess',"Options -Indexes\n<FilesMatch \"^(?!index\\.php$).*\\.php$\">\nRequire all denied\n</FilesMatch>\n");
        foreach(['image','upload','media'] as $media){catpart_hosting_live_path($identity['site_root'],$media.'/.htaccess');$old=$identity['site_root'].'/'.$media.'/.htaccess';$rules=is_file($old)?(string)file_get_contents($old):'';catpart_hosting_put($identity['runtime_root'],'stage/public/'.$media.'/.htaccess',$rules."\nOptions -Indexes\n<FilesMatch \"(?i:\\.(?:php[0-9]?|phtml|pht|phar|phps))$\">\nRequire all denied\n</FilesMatch>\n");}
        $staged=[];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($identity['runtime_root'].'/stage/public',FilesystemIterator::SKIP_DOTS));foreach($iterator as $entry)if($entry->isFile())$staged[substr(str_replace('\\','/',$entry->getPathname()),strlen($identity['runtime_root'].'/stage/public')+1)]=hash_file('sha256',$entry->getPathname());ksort($staged);
        $state['staged_files']=$staged;$state['phase']='prepared';$state['owner_id']=$owner;$state['owner_group_id']=$group;$state['schema']=catpart_hosting_schema_fingerprint($pdo);$state['prepared_at']=gmdate('c');catpart_hosting_save($state);
        return ['ready'=>true,'phase'=>'prepared','source_sha256'=>$identity['source_sha256']];
    });
}
function catpart_hosting_transport_receipt(array $identity,string $receipt):void {
    $path=realpath($receipt);if(!$path || !catpart_hosting_inside(str_replace('\\','/',$path),$identity['runtime_root'].'/private'))throw new RuntimeException('Private verified transport receipt required.');
    $data=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    foreach(['origin','database','runtime_root','source_sha256'] as $key)if(($data[$key]??null)!==$identity[$key])throw new RuntimeException('Transport receipt identity mismatch.');
    if(($data['format']??null)!==1 || ($data['scheduled']??false)!==true || ($data['worker_check']??false)!==true || ($data['injected_transport_checks']??false)!==true || empty($data['verified_at']))throw new RuntimeException('Scheduled checked hosting transport required; no delivery claim is implied.');
}
function catpart_hosting_live_path(string $root,string $relative):void {
    $path=$root;$parts=explode('/',$relative);foreach($parts as $index=>$part){if($part==='' || $part==='.' || $part==='..' || str_contains($part,'\\'))throw new RuntimeException('Invalid public deployment path.');$path.='/'.$part;
        $resolved=realpath($path);$normalize=static fn($value)=>PHP_OS_FAMILY==='Windows'?strtolower(str_replace('\\','/',$value)):str_replace('\\','/',$value);
        if(is_link($path) || ($resolved!==false && $normalize($resolved)!==$normalize($path)))throw new RuntimeException('Live deployment ancestor symlink or junction refused.');if(file_exists($path) && ($index<count($parts)-1?!is_dir($path):!is_file($path)))throw new RuntimeException('Live deployment path type collision.');}
}
function catpart_hosting_public_put(string $root,string $relative,string $data):void {
    catpart_hosting_live_path($root,$relative);
    $created=[];$path=$root;foreach(explode('/',dirname($relative)) as $part){if($part==='.')continue;$path.='/'.$part;if(!is_dir($path))$created[]=$path;}
    catpart_hosting_put($root,$relative,$data,0644);foreach($created as $directory)if(!chmod($directory,0755))throw new RuntimeException('Cannot expose newly created public asset directory.');
}
function catpart_hosting_blog_signature(PDO $pdo):string {return hash('sha256',json_encode($pdo->query('SELECT * FROM blog_posts ORDER BY id')->fetchAll(),JSON_THROW_ON_ERROR));}
function catpart_hosting_request_signature(PDO $pdo):array {$out=[];foreach(['cp_order','cp_lead','cp_request_key'] as $table)$out[$table]=$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();return $out;}
/** Failures happen behind maintenance before any new code is exposed. Never rewind accepted requests. */
function catpart_hosting_activation_recover(PDO $pdo,array &$state,array $files,array $beforeBlog,?string $afterBlog,array $beforeRequests,bool $hadMarker,bool $appSwitched):void {
    $root=$state['site_root'];$runtime=$state['runtime_root'];
    if($beforeRequests!==catpart_hosting_request_signature($pdo) || $afterBlog===null || !hash_equals($afterBlog,catpart_hosting_blog_signature($pdo))){
        // A request or article may have been accepted by an already running process.
        // Finish the verified compatible release rather than restore any of its data.
        \Catpart\Content::seed($pdo,$root);foreach($files as $relative=>$path)if(!str_starts_with($relative,'app/'))catpart_hosting_public_put($root,$relative,(string)file_get_contents($path));
        if(!$appSwitched){if(!rename($root.'/app',$runtime.'/'.$state['previous_app']))throw new RuntimeException('Cannot preserve application for forward recovery.');if(!rename($runtime.'/'.$state['next_app'],$root.'/app')){rename($runtime.'/'.$state['previous_app'],$root.'/app');throw new RuntimeException('Cannot finish compatible application switch.');}}
        $state['phase']='active';$state['recovered_forward_at']=gmdate('c');catpart_hosting_save($state);catpart_hosting_public_put($root,'.htaccess',(string)file_get_contents($runtime.'/private/activation.htaccess'));return;
    }
    if($appSwitched){if(!rename($root.'/app',$runtime.'/'.$state['failed_app']) || !rename($runtime.'/'.$state['previous_app'],$root.'/app'))throw new RuntimeException('Cannot recover previous application directory.');}
    foreach($files as $relative=>$path){if(str_starts_with($relative,'app/'))continue;$backup=$state['code_backup'][$relative]??null;
        if($backup!==null)catpart_hosting_public_put($root,$relative,(string)file_get_contents($runtime.'/private/code-backup/'.$relative));
        elseif(is_file($root.'/'.$relative) && !unlink($root.'/'.$relative))throw new RuntimeException('Cannot remove newly introduced public file.');}
    if(!$hadMarker){$marker=\Catpart\Factory::settings($pdo,'content_utc_migration');$converted=$marker['converted_columns']??[];$pdo->beginTransaction();try{
        foreach($beforeBlog as $row){$values=[];$columns=[];foreach($converted as $column){$columns[]='`'.$column.'`=?';$values[]=$row[$column];}$columns[]='image_url=?';$values[]=$row['image_url'];$values[]=$row['id'];$q=$pdo->prepare('UPDATE blog_posts SET '.implode(',',$columns).' WHERE id=?');$q->execute($values);}
        $pdo->exec("DELETE FROM cp_settings WHERE section='content_utc_migration'");$pdo->commit();}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}}
    catpart_hosting_public_put($root,'.htaccess',(string)file_get_contents($runtime.'/private/activation.htaccess'));$state['phase']='prepared';$state['recovered_at']=gmdate('c');catpart_hosting_save($state);
}
function catpart_hosting_activate(PDO $pdo,array $identity,string $receipt,?callable $checkpoint=null):array {
    return catpart_hosting_lock($pdo,function()use($pdo,$identity,$receipt,$checkpoint):array {
        catpart_hosting_preflight($pdo,$identity);$state=catpart_hosting_state($identity);if(!$state || !in_array($state['phase'],['prepared','active','disabled','activating','activation_failed'],true))throw new RuntimeException('Prepared installation required.');
        catpart_hosting_transport_receipt($identity,$receipt);if($state['phase']==='active')return ['ready'=>true,'phase'=>'active'];
        if($state['phase']==='disabled'){$adapter=$identity['runtime_root'].'/stage/public/admin/index.php';if(!hash_equals((string)($state['staged_files']['admin/index.php']??''),(string)hash_file('sha256',$adapter)))throw new RuntimeException('Staged admin adapter changed.');catpart_hosting_public_put($identity['site_root'],'admin/index.php',(string)file_get_contents($adapter));$state['phase']='active';$state['reactivated_at']=gmdate('c');catpart_hosting_save($state);return ['ready'=>true,'phase'=>'active'];}
        require_once $identity['runtime_root'].'/extension/catpart/system/autoload.php';
        $root=$identity['site_root'];$runtime=$identity['runtime_root'];$files=[];$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtime.'/stage/public',FilesystemIterator::SKIP_DOTS));foreach($iterator as $entry){if($entry->isLink())throw new RuntimeException('Staged symlink refused.');if($entry->isFile())$files[substr(str_replace('\\','/',$entry->getPathname()),strlen($runtime.'/stage/public')+1)]=$entry->getPathname();}
        if(is_link($root.'/app'))throw new RuntimeException('Live application symlink refused.');catpart_hosting_live_path($root,'.htaccess');foreach(array_keys($files) as $relative)catpart_hosting_live_path($root,$relative);
        if((stat($root)['dev']??null)!==(stat($runtime)['dev']??null) || !is_writable($root) || !is_writable($root.'/app'))throw new RuntimeException('Same-filesystem writable application switch required.');
        ksort($files);$staged=[];foreach($files as $relative=>$path)$staged[$relative]=hash_file('sha256',$path);if($staged!==($state['staged_files']??[]))throw new RuntimeException('Staged public source changed.');
        $backup=$state['code_backup']??[];foreach($files as $relative=>$path){if(str_starts_with($relative,'app/Config/') || str_contains($relative,'..'))throw new RuntimeException('Protected public path in deployment.');$target=$root.'/'.$relative;if(is_link($target))throw new RuntimeException('Live deployment symlink refused.');
            if(!array_key_exists($relative,$backup)){$backup[$relative]=is_file($target)?hash_file('sha256',$target):null;if(is_file($target))catpart_hosting_put($runtime,'private/code-backup/'.$relative,(string)file_get_contents($target));}}
        $attempt=bin2hex(random_bytes(6));$state['previous_app']='private/previous-app-'.$attempt;$state['next_app']='private/next-app-'.$attempt;$state['failed_app']='private/failed-app-'.$attempt;
        $next=$runtime.'/'.$state['next_app'];$previous=$runtime.'/'.$state['previous_app'];
        catpart_hosting_copy_tree($root.'/app',$runtime,$state['next_app']);catpart_hosting_copy_tree($runtime.'/stage/public/app',$runtime,$state['next_app']);
        // Configs/branding are copied only server-side into private staging; their contents and modes are preserved.
        $nextIterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($next,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);foreach($nextIterator as $entry){$relative=substr(str_replace('\\','/',$entry->getPathname()),strlen($next)+1);$old=$root.'/app/'.$relative;if($entry->isDir())chmod($entry->getPathname(),is_dir($old)?(fileperms($old)&0777):0755);else chmod($entry->getPathname(),is_file($old)?(fileperms($old)&0777):0644);}chmod($next,fileperms($root.'/app')&0777);
        $state['code_backup']=$backup;$state['phase']='activating';catpart_hosting_save($state);
        $htaccess=$root.'/.htaccess';if(is_file($runtime.'/private/activation.htaccess'))$original=(string)file_get_contents($runtime.'/private/activation.htaccess');else{$original=is_file($htaccess)?(string)file_get_contents($htaccess):'';catpart_hosting_put($runtime,'private/activation.htaccess',$original);}
        $beforeBlog=$pdo->query('SELECT id,published_at,created_at,image_url FROM blog_posts ORDER BY id')->fetchAll();$beforeRequests=catpart_hosting_request_signature($pdo);$hadMarker=\Catpart\Factory::settings($pdo,'content_utc_migration')!==[];$afterBlog=null;$appSwitched=false;
        catpart_hosting_public_put($root,'.htaccess',"RewriteEngine On\nRewriteRule ^ - [R=503,L]\nErrorDocument 503 \"Сайт обновляется. Повторите запрос через минуту.\"\n");
        try{
            \Catpart\Content::seed($pdo,$root);
            $afterBlog=catpart_hosting_blog_signature($pdo);if($checkpoint)$checkpoint('after_seed');
            $copied=0;foreach($files as $relative=>$path){if(str_starts_with($relative,'app/'))continue;catpart_hosting_public_put($root,$relative,(string)file_get_contents($path));$copied++;if($checkpoint && $copied===3)$checkpoint('during_files');}
            if(!rename($root.'/app',$previous))throw new RuntimeException('Cannot preserve previous application.');
            if(!rename($next,$root.'/app')){rename($previous,$root.'/app');throw new RuntimeException('Cannot switch staged application.');}$appSwitched=true;if($checkpoint)$checkpoint('after_app_switch');
            $state['phase']='active';$state['activated_at']=gmdate('c');catpart_hosting_save($state);
        }catch(Throwable $error){$state['phase']='activation_failed';catpart_hosting_save($state);catpart_hosting_activation_recover($pdo,$state,$files,$beforeBlog,$afterBlog,$beforeRequests,$hadMarker,$appSwitched);throw $error;}
        finally{if(($state['phase']??'')==='active')catpart_hosting_public_put($root,'.htaccess',$original);}
        return ['ready'=>true,'phase'=>'active'];
    });
}
function catpart_hosting_rollback(PDO $pdo,array $identity):array {
    return catpart_hosting_lock($pdo,function()use($pdo,$identity):array {
        $state=catpart_hosting_state($identity);if(!$state || !in_array($state['phase'],['active','disabled'],true))throw new RuntimeException('Only an activated compatible baseline can be disabled.');
        if(($state['schema']??[])!==catpart_hosting_schema_fingerprint($pdo))throw new RuntimeException('Extension schema changed before rollback.');
        catpart_hosting_put($identity['site_root'],'admin/index.php',"<?php\nhttp_response_code(503); header('X-Robots-Tag: noindex, nofollow'); exit('Администрирование временно недоступно.');\n",0644);
        $state['phase']='disabled';$state['disabled_at']=gmdate('c');catpart_hosting_save($state);return ['ready'=>true,'phase'=>'disabled','public_pipeline'=>'utc-compatible-retained'];
    });
}
function catpart_hosting_cli():int {
    if(PHP_SAPI!=='cli'){http_response_code(404);return 1;}
    try{$options=getopt('',['preflight','prepare','activate','rollback','site:','runtime:','source:','upstream:','origin:','database:','source-sha256:','transport-ready:']);$actions=array_values(array_intersect(['preflight','prepare','activate','rollback'],array_keys($options)));if(count($actions)!==1)throw new RuntimeException('One explicit phase required.');
        if($actions[0]!=='preflight' && !isset($options['source-sha256']))throw new RuntimeException('Pinned source digest required.');$identity=catpart_hosting_identity($options);$pdo=catpart_hosting_pdo($identity);
        $report=match($actions[0]){'preflight'=>catpart_hosting_lock($pdo,static fn()=>catpart_hosting_preflight($pdo,$identity)),'prepare'=>catpart_hosting_prepare($pdo,$identity,$options['upstream']),'activate'=>catpart_hosting_activate($pdo,$identity,(string)($options['transport-ready']??'')),'rollback'=>catpart_hosting_rollback($pdo,$identity)};
        echo json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;return 0;
    }catch(Throwable $error){fwrite(STDERR,"Hosting operation failed; inspect the private phase manifest and documented preflight conditions.\n");return 1;}
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__)exit(catpart_hosting_cli());
