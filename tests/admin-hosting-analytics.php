<?php
declare(strict_types=1);
// Render actual views with a production origin and synthetic loopback data; no network or mail.
$runtime=getenv('CATPART_ADMIN_RUNTIME');$mode=$argv[1]??'';
if(!is_string($runtime)||!is_file($runtime.'/state.json')||!in_array($mode,['public','admin'],true))throw new RuntimeException('Explicit isolated runtime and mode required.');
$state=json_decode((string)file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^catpart_admin_[a-f0-9]{12}$/D',$state['database']??''))throw new RuntimeException('Only isolated QA data allowed.');
if($mode==='admin')define('APPLICATION','Admin');
$site=dirname(__DIR__).'/local-dev/site';$_SERVER['DOCUMENT_ROOT']=$site;$_SERVER['REQUEST_URI']=$mode==='admin'?'/admin/index.php':'/stati/example';$_SESSION=[];
require $site.'/app/bootstrap.php';
$config=require $site.'/app/Config/config.local.php';\Catpart\Factory::assertLocal($config['db'],$state['database']);
$config['app']['base_url']='https://cat-part.ru';\App\Core\App::boot($config);
$post=\App\Models\Post::latest(1)[0];
$html=\App\Core\View::render('blog/show',['post'=>$post,'related'=>[],'page'=>['title'=>$post['title'],'canonical'=>'/stati/'.$post['slug'],'noindex'=>$mode==='admin']]);
$hasMetrika=str_contains($html,'mc.yandex.ru/metrika/tag.js');$hasPixel=str_contains($html,'mc.yandex.ru/watch/');
if($mode==='admin'&&($hasMetrika||$hasPixel||str_contains($html,'googletagmanager.com')))throw new RuntimeException('Private preview emitted external analytics.');
if($mode==='public'&&(!$hasMetrika||!$hasPixel))throw new RuntimeException('Public analytics missing.');
echo 'PASS '.$mode.' actual article render analytics boundary; no network or mail'.PHP_EOL;
