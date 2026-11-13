<?php
declare(strict_types=1);
// Explicit CLI maintenance for the selected loopback stand; never an HTTP endpoint.
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only.');
$runtime=getenv('CATPART_ADMIN_RUNTIME');
if(!is_string($runtime)||!is_file($runtime.'/state.json'))throw new RuntimeException('Explicit task runtime required.');
$state=json_decode(file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
require $runtime.'/core/admin/config.php';
\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],$state['database']);
$pdo=\Catpart\Factory::pdo();
function identifier(string $name):string {if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name))throw new RuntimeException('Invalid schema identifier.');return '`'.$name.'`';}
function eachRow(PDO $pdo,string $table,callable $consumer):void {
    $columns=$pdo->query('SHOW COLUMNS FROM '.identifier($table))->fetchAll(PDO::FETCH_COLUMN);
    $indexes=$pdo->query("SHOW INDEX FROM ".identifier($table)." WHERE Key_name='PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);usort($indexes,static fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);$order=$indexes?array_column($indexes,'Column_name'):$columns;
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    try{$query=$pdo->query('SELECT * FROM '.identifier($table).' ORDER BY '.implode(',',array_map('identifier',$order)));while($row=$query->fetch(PDO::FETCH_ASSOC)){foreach($row as &$v)if($v!==null)$v=base64_encode((string)$v);unset($v);$consumer($row);}$query->closeCursor();}finally{$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);}
}
function databaseDigest(PDO $pdo):array {$result=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){$hash=hash_init('sha256');eachRow($pdo,$table,static function($row)use($hash){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");});$result[$table]=hash_final($hash);}ksort($result);return $result;}
function backup(PDO $pdo,string $runtime,string $database):string {
    $directory=$runtime.'/private/backups';if(!is_dir($directory))mkdir($directory,0700,true);
    $file=$directory.'/backup-'.bin2hex(random_bytes(8)).'.jsonl';$out=fopen($file.'.part','xb');fwrite($out,json_encode(['format'=>2,'database'=>$database],JSON_THROW_ON_ERROR)."\n");
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    try {$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);foreach($tables as $table){
        fwrite($out,json_encode(['table'=>$table,'schema'=>$pdo->query('SHOW CREATE TABLE '.identifier($table))->fetch(PDO::FETCH_NUM)[1]],JSON_THROW_ON_ERROR)."\n");$hash=hash_init('sha256');$count=0;
        eachRow($pdo,$table,static function($row)use($out,$hash,&$count){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");fwrite($out,json_encode(['row'=>$row],JSON_THROW_ON_ERROR)."\n");$count++;});
        fwrite($out,json_encode(['end'=>$table,'count'=>$count,'sha256'=>hash_final($hash)],JSON_THROW_ON_ERROR)."\n");
    }fwrite($out,json_encode(['complete'=>true,'tables'=>count($tables)],JSON_THROW_ON_ERROR)."\n");$pdo->commit();fclose($out);rename($file.'.part',$file);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fclose($out);throw $e;}
    return $file;
}
function restoreNew(string $runtime,string $backup):array {
    $rootPath=realpath($runtime.'/private/backups');$file=realpath($backup);
    if(!$rootPath||!$file||!str_starts_with(str_replace('\\','/',$file),rtrim(str_replace('\\','/',$rootPath),'/').'/'))throw new RuntimeException('Backup must be in own private backup directory.');
    $in=fopen($file,'rb');$header=json_decode(fgets($in),true,512,JSON_THROW_ON_ERROR);
    if(($header['database']??'')!==DB_DATABASE||($header['format']??0)!==2)throw new RuntimeException('Backup identity/format mismatch.');
    $name='catpart_admin_'.bin2hex(random_bytes(6));
    $root=new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    // CREATE without IF NOT EXISTS: a restore can never overwrite an existing DB.
    $root->exec('CREATE DATABASE '.identifier($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    file_put_contents($runtime.'/private/restore-owned-'.$name,json_encode(['database'=>$name,'backup'=>$file],JSON_THROW_ON_ERROR));
    $root->exec('USE '.identifier($name));$root->exec("SET time_zone='+00:00'");$root->exec("SET SESSION sql_mode=''");
    $table=null;$query=null;$expected=[];$complete=false;
    while(($line=fgets($in))!==false){$entry=json_decode($line,true,512,JSON_THROW_ON_ERROR);
        if(isset($entry['table'])){
            if($table!==null)throw new RuntimeException('Missing backup table boundary.');$table=$entry['table'];
            if(!str_starts_with($entry['schema'],'CREATE TABLE '.identifier($table).' ('))throw new RuntimeException('Unexpected backup schema.');
            $root->exec($entry['schema']);$hash=hash_init('sha256');$count=0;$query=null;$root->beginTransaction();
        }elseif(isset($entry['row'])){
            if($table===null)throw new RuntimeException('Unexpected backup row.');$row=$entry['row'];
            if($query===null){$columns=array_keys($row);$query=$root->prepare('INSERT INTO '.identifier($table).' ('.implode(',',array_map('identifier',$columns)).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');}
            $query->execute(array_map(static fn($v)=>$v===null?null:base64_decode($v,true),array_values($row)));hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++;
        }elseif(isset($entry['end'])){
            if($table!==$entry['end']||$count!==$entry['count']||hash_final($hash)!==$entry['sha256'])throw new RuntimeException('Backup table integrity failure.');
            $root->commit();$expected[$table]=$entry['sha256'];$table=null;
        }elseif(($entry['complete']??false)===true){
            if($table!==null||count($expected)!==$entry['tables']||fgets($in)!==false)throw new RuntimeException('Backup completion integrity failure.');$complete=true;break;
        }else throw new RuntimeException('Unexpected backup entry.');
    }
    fclose($in);if($table!==null||!$complete)throw new RuntimeException('Incomplete backup.');ksort($expected);return ['database'=>$name,'pdo'=>$root,'expected'=>$expected];
}
function inventory(PDO $target,string $runtime):array {
    $source=new PDO('mysql:host=127.0.0.1;port=3307;dbname=local_catpart;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $source->exec('SET SESSION TRANSACTION READ ONLY');$source->beginTransaction();
    $tables=['brands','categories','products','attributes','product_attr_values','product_images','product_compat','product_cross','blog_posts'];$report=['source'=>'local_catpart (read-only catalog whitelist)','target'=>DB_DATABASE,'tables'=>[],'identity_maps'=>[]];
    foreach($tables as $table){
        $hash=hash_init('sha256');eachRow($source,$table,static function($row)use($hash){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");});
        $entry=['source_count'=>(int)$source->query('SELECT COUNT(*) FROM '.identifier($table))->fetchColumn(),'target_count'=>(int)$target->query('SELECT COUNT(*) FROM '.identifier($table))->fetchColumn(),'source_catalog_sha256'=>hash_final($hash)];
        if(in_array($table,['products','categories','brands','blog_posts','attributes','product_images'],true)){
            $ids=array_flip($target->query('SELECT id FROM '.identifier($table))->fetchAll(PDO::FETCH_COLUMN));$map=[];foreach($source->query('SELECT id FROM '.identifier($table).' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $id){$id=(int)$id;$map[]=['old_id'=>$id,'new_id'=>isset($ids[$id])?$id:null];}
            $report['identity_maps'][$table]=$map;$entry['missing_ids']=count(array_filter($map,static fn($m)=>$m['new_id']===null));
        }
        $report['tables'][$table]=$entry;
    }
    $source->commit();
    $report['relationships']=['products_without_category'=>(int)$target->query('SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.category_id IS NOT NULL AND c.id IS NULL')->fetchColumn(),'image_orphans'=>(int)$target->query('SELECT COUNT(*) FROM product_images i LEFT JOIN products p ON p.id=i.product_id WHERE p.id IS NULL')->fetchColumn()];
    $report['routes']=['products'=>$target->query("SELECT id,CONCAT('/tovar/',slug) AS path FROM products ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),'categories'=>$target->query("SELECT id,CONCAT('/catalog/',slug) AS path FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),'articles'=>$target->query("SELECT id,CONCAT('/stati/',slug) AS path FROM blog_posts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)];
    $images=$target->query('SELECT image_url AS url FROM products WHERE image_url IS NOT NULL AND image_url<>\'\' UNION SELECT url FROM product_images')->fetchAll(PDO::FETCH_COLUMN);
    $missing=[];$sourceRoot=rtrim($GLOBALS['state']['source'],'/\\').'/local-dev/site';$site=dirname(__DIR__).'/local-dev/site';
    foreach($images as $url){if(!is_string($url)||!str_starts_with($url,'/')||str_contains($url,'..')){$missing[]=$url;continue;}if(!is_file($site.$url)&&!is_file($sourceRoot.$url))$missing[]=$url;}
    $report['images']=['unique_references'=>count($images),'missing_count'=>count($missing),'missing_paths'=>$missing,'source_media_mode'=>'read-only alias; new media only own stand'];
    $report['settings_sections']=$target->query('SELECT section FROM cp_settings ORDER BY section')->fetchAll(PDO::FETCH_COLUMN);
    $report['installed_extensions']=$target->query('SELECT name,code,version FROM oc_extension_install ORDER BY extension_install_id')->fetchAll(PDO::FETCH_ASSOC);
    $path=$runtime.'/private/migration-report.json';file_put_contents($path,json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));return ['report'=>$path,'tables'=>$report['tables'],'relationships'=>$report['relationships'],'images'=>['unique_references'=>count($images),'missing_count'=>count($missing)]];
}
if(realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__){
    $command=$argv[1]??'inventory';
    if($command==='inventory')echo json_encode(inventory($pdo,$runtime),JSON_THROW_ON_ERROR).PHP_EOL;
    elseif($command==='backup')echo json_encode(['backup'=>backup($pdo,$runtime,DB_DATABASE)],JSON_THROW_ON_ERROR).PHP_EOL;
    elseif($command==='restore'){if(($argv[3]??'')!=='--apply')throw new RuntimeException('Restore creates a fresh own schema; pass backup path and --apply.');$result=restoreNew($runtime,$argv[2]);echo json_encode(['restored_database'=>$result['database'],'tables'=>count(databaseDigest($result['pdo']))],JSON_THROW_ON_ERROR).PHP_EOL;}
    else throw new RuntimeException('Unknown maintenance command.');
}
