<?php
declare(strict_types=1);
// Dedicated loopback HTTP fixture; must never be used as a production router.
$port=getenv('CATPART_TEST_HTTP_PORT');$db=getenv('CATPART_TEST_DATABASE');
if(PHP_SAPI!=='cli-server'||!is_string($port)||($_SERVER['SERVER_PORT']??'')!==$port||!is_string($db)||!preg_match('/^catpart_admin_[a-f0-9]{12}$/D',$db)){http_response_code(404);return true;}
$path=(string)(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
if(preg_match('~^/(app|db|storage)(/|$)~i',$path)){http_response_code(404);return true;}
if($path!=='/' && preg_match('~\.(css|js|webp|png|jpg|ico|svg)$~i',$path) && is_file(dirname(__DIR__).'/local-dev/site'.$path))return false;
$app=require dirname(__DIR__).'/local-dev/site/app/bootstrap.php';$config=$app->config;$config['app']['base_url']='http://127.0.0.1:'.$port;
$_SERVER['REMOTE_ADDR']='192.0.2.250';
$app=\App\Core\App::boot($config);\App\Core\Session::start();$register=require BASE_PATH.'/app/routes.php';$register($app->router);$app->router->dispatch($_SERVER['REQUEST_METHOD'],$path);
