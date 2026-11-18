<?php
declare(strict_types=1);
// Production CLI worker. Reports counters only; never prints messages, configuration or credentials.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try {
    $options=getopt('',['site:','runtime:','limit:','check']);$check=array_key_exists('check',$options);
    if(empty($options['site']) || empty($options['runtime']))throw new RuntimeException('worker_configuration');
    $site=realpath((string)($options['site']??''));$runtime=realpath((string)($options['runtime']??''));
    $limit=(string)($options['limit']??'20');
    if(!$site || !$runtime || (!preg_match('/^[1-9][0-9]?$/D',$limit) && $limit!=='100'))throw new RuntimeException('worker_configuration');
    $normalize=static fn(string $path):string=>rtrim(str_replace('\\','/',PHP_OS_FAMILY==='Windows'?strtolower($path):$path),'/');
    if($normalize($runtime)===$normalize($site) || str_starts_with($normalize($runtime),$normalize($site).'/'))throw new RuntimeException('worker_configuration');
    $manifest=$runtime.'/hosting-state.json';
    if(!is_file($manifest))throw new RuntimeException('worker_configuration');
    $state=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
    $origin=(string)($state['origin']??'');$url=parse_url($origin);
    if(($state['format']??null)!==1 || !in_array($state['phase']??'',$check?['prepared','active','disabled']:['active','disabled'],true) || realpath((string)($state['site_root']??''))!==$site
        || !$url || ($url['scheme']??'')!=='https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
        || !in_array($url['path']??'',['','/'],true) || in_array(strtolower($url['host']),['localhost','127.0.0.1','::1'],true)
        || !is_string($state['database']??null) || $state['database']==='' || preg_match('/^(local_catpart|catpart_admin_[a-f0-9]{12})$/D',$state['database']))throw new RuntimeException('worker_configuration');
    if(getenv('CATPART_TEST_DATABASE') || defined('CATPART_TEST_DATABASE'))throw new RuntimeException('worker_configuration');
    $loader=$runtime.'/extension/catpart/system/autoload.php';$bootstrap=$site.'/app/bootstrap.php';
    if(!is_file($loader) || !is_file($bootstrap))throw new RuntimeException('worker_configuration');
    require_once $loader;
    require $bootstrap;
    $actualOrigin=rtrim((string)\App\Core\App::config('app.base_url',''),'/');
    if(!hash_equals(rtrim($origin,'/'),$actualOrigin) || !hash_equals($state['database'],(string)\App\Core\App::config('db.name',''))
        || !hash_equals((string)($state['database_host']??''),(string)\App\Core\App::config('db.host','')) || (int)($state['database_port']??0)!==(int)\App\Core\App::config('db.port',0)
        || defined('CATPART_TEST_DATABASE') || \App\Core\App::config('mail.delivery','mail')!=='mail')throw new RuntimeException('worker_configuration');
    $pdo=\App\Core\App::db()->pdo();
    if(!hash_equals($state['database'],(string)$pdo->query('SELECT DATABASE()')->fetchColumn()))throw new RuntimeException('worker_configuration');
    $pdo->exec("SET time_zone='+00:00'");
    if(!function_exists('mail'))throw new RuntimeException('worker_configuration');
    if($check){echo json_encode(['ready'=>true,'mode'=>'check','send'=>false],JSON_THROW_ON_ERROR).PHP_EOL;exit(0);}
    $box=new \Catpart\Outbox($pdo);
    $result=$box->run($box->mailTransport((array)\App\Core\App::config('mail',[]),(array)\App\Core\App::config('site',[])),(int)$limit);
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
    exit($result['errors']>0?1:0);
} catch(Throwable $error) {
    fwrite(STDERR,"Hosting notification worker failed; inspect private configuration or outbox status.\n");
    exit(1);
}
