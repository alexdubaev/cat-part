<?php
declare(strict_types=1);
$runtime=getenv('CATPART_ADMIN_RUNTIME')?:dirname(__DIR__).'/local-dev/admin-runtime';
require $runtime.'/core/admin/config.php';
$pdo=\Catpart\Factory::pdo();
if(!defined('CATPART_TEST_DATABASE') || !preg_match('/^catpart_admin_[a-f0-9]{12}$/D',DB_DATABASE))throw new RuntimeException('Isolated test database required');
$checks=0;
function ok(bool $value,string $label):void{global $checks;if(!$value)throw new RuntimeException($label);++$checks;echo 'PASS '.$label."\n";}
\Catpart\Content::migrate($pdo);
$c=new \Catpart\Content($pdo);
$pdo->beginTransaction();
try {
 $input=['title'=>'Тестовая статья','slug'=>'content-test-'.bin2hex(random_bytes(4)),'body_html'=>'<h2>Раздел</h2><p style="color:red" onclick="evil()">Текст<script>evil()</script></p><figure><img src="/upload/test.webp" alt="Деталь"><figcaption>Подпись</figcaption></figure><table><tbody><tr><td>Размер</td></tr></tbody></table>','status'=>'draft','published_at'=>'','excerpt'=>'Анонс','author'=>'Редактор'];
 $id=$c->saveArticle($input,1);
 $a=$c->article($id);
 ok($a['status']==='draft' && !$c->isVisible($a),'draft is never public');
 ok(!str_contains($a['body_html'],'script') && !str_contains($a['body_html'],'onclick') && str_contains($a['body_html'],'<table>') && str_contains($a['body_html'],'alt="Деталь"'),'rich HTML sanitized retaining images/table');
 $input['id']=$id;$input['title']='Вторая версия';$input['status']='scheduled';$input['published_at']='2099-01-01T10:00';$c->saveArticle($input,1);
 ok(!$c->isVisible($c->article($id)) && $c->article($id)['published_at']==='2099-01-01 07:00:00','Moscow schedule stored UTC and hidden');
 $revs=$c->revisions($id);$c->restoreArticle($id,(int)end($revs)['id'],1);
 ok($c->article($id)['title']==='Тестовая статья' && count($c->revisions($id))===3,'restore creates a new historical revision');
 ok(\Catpart\Content::mapUrl('<iframe src="https://yandex.ru/map-widget/v1/?um=constructor%3Atest"></iframe>')==='https://yandex.ru/map-widget/v1/?um=constructor%3Atest','Yandex iframe normalized');
 foreach(['https://yandex.ru.evil.test/map-widget/v1/','javascript:alert(1)','<iframe src="https://yandex.ru/map-widget/v1/"></iframe><script>evil()</script>'] as $bad){$denied=false;try{\Catpart\Content::mapUrl($bad);}catch(InvalidArgumentException){$denied=true;}ok($denied,'unsafe map denied');}
 $site=\Catpart\Content::validateSite(['name'=>'CatPart','company'=>'СМ ТЕХНО','email'=>'info@cmteh.ru','phones'=>[['label'=>'+7 900 000-00-00','tel'=>'+79000000000']],'metrika_id'=>'114229167','ga4_id'=>'G-1234567890','map_widget'=>'']);
 ok($site['map_widget']==='' && $site['metrika_id']==='114229167','clear map and retained existing Metrika');
 $bad=false;try{\Catpart\Content::validateSite($site+['ga4_id'=>'bad']);}catch(InvalidArgumentException){$bad=true;}
 $bad=false;try{\Catpart\Content::validateSite(array_replace($site,['ga4_id'=>'<script>']));}catch(InvalidArgumentException){$bad=true;}ok($bad,'analytics ID server validation');
 $html=\Catpart\Content::analyticsHtml($site,'127.0.0.1');ok($html==='','local analytics absent');
 $html=\Catpart\Content::analyticsHtml($site,'cat-part.ru');ok(substr_count($html,'mc.yandex.ru/metrika/tag.js')===1 && str_contains($html,'webvisor:true') && str_contains($html,'ecommerce:"dataLayer"') && substr_count($html,'googletagmanager.com/gtag/js')===1,'single async analytics with existing options');
} finally { $pdo->rollBack(); }

$before=$pdo->query('SELECT id,published_at,created_at FROM blog_posts ORDER BY id')->fetchAll();
\Catpart\Content::seed($pdo,dirname(__DIR__).'/local-dev/site');
$after=$pdo->query('SELECT id,published_at,created_at FROM blog_posts ORDER BY id')->fetchAll();
ok($before===$after,'repeat migration does not shift original dates');
$marker=\Catpart\Factory::settings($pdo,'content_utc_migration');ok(($marker['source_timezone']??'')==='Europe/Moscow' && !empty($marker['original_dates']),'source dates retained with explicit timezone migration');
$pdo->beginTransaction();
try{
 $field=$pdo->query('SELECT content_key FROM cp_content WHERE kind=\'text\' ORDER BY content_key LIMIT 1')->fetchColumn();
 $c->saveFields([$field=>'Сохранённый текст владельца'],1);
 \Catpart\Factory::saveSettings($pdo,'menus',['header'=>[],'footer_catalog'=>[],'footer_buyers'=>[],'footer_company'=>[]],1);
 \Catpart\Content::seed($pdo,dirname(__DIR__).'/local-dev/site');
 ok($c->value((string)$field)==='Сохранённый текст владельца','repeat seed preserves edited business copy');
 ok(\Catpart\Factory::settings($pdo,'menus')['header']===[],'repeat seed preserves removed menu items');
}finally{$pdo->rollBack();}
echo $checks." content checks passed\n";
