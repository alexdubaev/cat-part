<?php
declare(strict_types=1);
namespace Catpart;
use PDO;
use InvalidArgumentException;
use DateTimeImmutable;
use DateTimeZone;

final class Content
{
    public function __construct(private PDO $pdo) {}
    public static function migrate(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_content (content_key VARCHAR(160) PRIMARY KEY,kind VARCHAR(20) NOT NULL,title VARCHAR(255) NOT NULL,body MEDIUMTEXT NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_article (post_id INT UNSIGNED PRIMARY KEY,status VARCHAR(20) NOT NULL,author VARCHAR(160) NOT NULL DEFAULT '',seo_h1 VARCHAR(255) NOT NULL DEFAULT '',canonical VARCHAR(512) NOT NULL DEFAULT '',noindex TINYINT NOT NULL DEFAULT 0,og_image VARCHAR(512) NOT NULL DEFAULT '',cover_alt VARCHAR(255) NOT NULL DEFAULT '',related_articles TEXT NOT NULL,related_products TEXT NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_article_revision (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,post_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,snapshot MEDIUMTEXT NOT NULL,created_at DATETIME NOT NULL,KEY article_versions(post_id,id)) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("INSERT IGNORE INTO cp_article(post_id,status,author,related_articles,related_products,updated_at) SELECT id,IF(is_active=1,'published','draft'),'','[]','[]',UTC_TIMESTAMP() FROM blog_posts");
    }
    /** Native OpenCart Request::clean HTML-encodes strings before extension controllers. */
    public static function nativeInput(array $data):array
    {
        foreach($data as $key=>$value)$data[$key]=is_array($value)?self::nativeInput($value):(is_string($value)?html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8'):$value);
        return $data;
    }
    public static function siteRoot():string
    {
        $root=getenv('CATPART_SITE_ROOT');
        return is_string($root)&&$root!==''?rtrim($root,'/\\'):dirname(__DIR__,4).'/local-dev/site';
    }
    public static function seed(PDO $pdo,string $siteRoot):void
    {
        $file=__DIR__.'/ContentDefaults.json';if(!is_file($file))return;
        $defaults=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        $q=$pdo->prepare('INSERT INTO cp_content(content_key,kind,title,body,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE kind=VALUES(kind),title=VALUES(title)');
        foreach($defaults['fields']??[] as $key=>$field)$q->execute([$key,$field['kind'],$field['label'],$field['value']]);
        $brand=require $siteRoot.'/app/Config/branding.php';
        $site=$brand+['name'=>'CatPart','domain'=>'cat-part.ru','tagline'=>'Запчасти для техники Caterpillar','logo_mark'=>'CP','logo'=>'/assets/img/logo.webp','favicon'=>'/assets/img/favicon.svg','default_og_image'=>'/assets/img/og-default.jpg','primary_phone'=>0,'legal'=>'','inn'=>'','ogrn'=>'','city'=>'','hours'=>'','office'=>'','messengers'=>'','socials'=>'','map_widget'=>'','ga4_id'=>'','yandex_verification'=>'','google_verification'=>''];
        foreach(['site'=>$site,'forms'=>$defaults['forms']??[],'mail'=>['from_email'=>$brand['email'],'from_name'=>'CatPart','notify'=>[$brand['email']]],'menus'=>$defaults['menus']??[],'home'=>$defaults['home']??[],'page_seo'=>$defaults['page_seo']??[],'legal'=>$defaults['legal']??[]] as $section=>$value){
            $q2=$pdo->prepare('INSERT IGNORE INTO cp_settings(section,data,updated_at) VALUES(?,?,UTC_TIMESTAMP())');$q2->execute([$section,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $current=Factory::settings($pdo,$section);$merged=$current+$value;if($merged!==$current){$update=$pdo->prepare('UPDATE cp_settings SET data=?,updated_at=UTC_TIMESTAMP() WHERE section=?');$update->execute([json_encode($merged,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$section]);}
        }
        if(Factory::settings($pdo,'content_contact_migration')===[]){
            $currentSite=Factory::settings($pdo,'site');
            if(($currentSite['address']??'')===$brand['address'] && empty($currentSite['office']) && preg_match('/,\s*офис\s+(.+)$/u',$brand['address'],$office)){
                $currentSite['address']=preg_replace('/,\s*офис\s+.+$/u','',$brand['address']);$currentSite['office']=$office[1];Factory::saveSettings($pdo,'site',$currentSite,0);
            }
            Factory::saveSettings($pdo,'content_contact_migration',['version'=>1],0);
        }
        // The inspected legacy source DB session used Europe/Moscow (UTC+03:00).
        // Record the exact original timestamps before the one-time local migration.
        $marker=$pdo->query("SELECT data FROM cp_settings WHERE section='content_utc_migration'")->fetchColumn();
        if($marker===false){
            $pdo->beginTransaction();
            try {
                $originalDates=$pdo->query('SELECT id,published_at,created_at FROM blog_posts ORDER BY id')->fetchAll();
                $pdo->exec('UPDATE blog_posts SET published_at=DATE_SUB(published_at,INTERVAL 3 HOUR),created_at=DATE_SUB(created_at,INTERVAL 3 HOUR)');
                $migration=$pdo->prepare("INSERT INTO cp_settings(section,data,updated_at) VALUES('content_utc_migration',?,UTC_TIMESTAMP())");
                $migration->execute([json_encode(['source_timezone'=>'Europe/Moscow','target_timezone'=>'UTC','original_dates'=>$originalDates],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
                $pdo->commit();
            } catch(\Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
        }
        // Materialize the existing shipped cover mapping once, without replacing custom covers.
        $mapping=['filtry-i-rabochie-zhidkosti-poddelka','hodovaya-chast-kogda-menyat','original-analog-ili-reman','kak-ustroen-nomer-detali-caterpillar'];
        foreach($mapping as $slug){$image='/assets/img/articles/'.$slug.'.webp';if(is_file($siteRoot.$image)){$q2=$pdo->prepare("UPDATE blog_posts SET image_url=? WHERE slug=? AND (image_url IS NULL OR image_url='')");$q2->execute([$image,$slug]);}}
    }
    public function fields(string $group=''):array
    {
        $sql='SELECT * FROM cp_content';$args=[];if($group!==''){$sql.=' WHERE content_key LIKE ?';$args[]=$group.'.%';}
        $q=$this->pdo->prepare($sql.' ORDER BY content_key');$q->execute($args);return $q->fetchAll();
    }
    public function value(string $key,string $default=''):string
    {
        $q=$this->pdo->prepare('SELECT body FROM cp_content WHERE content_key=?');$q->execute([$key]);$v=$q->fetchColumn();return $v===false?$default:(string)$v;
    }
    public function saveFields(array $values,int $actor):void
    {
        $this->atomic(function()use($values,$actor):void{
            $q=$this->pdo->prepare('SELECT kind FROM cp_content WHERE content_key=?');$u=$this->pdo->prepare('UPDATE cp_content SET body=?,updated_at=UTC_TIMESTAMP() WHERE content_key=?');
            foreach($values as $key=>$value){$q->execute([(string)$key]);$kind=$q->fetchColumn();if($kind===false)throw new InvalidArgumentException('Неизвестное поле содержимого.');
                if(!is_string($value))throw new InvalidArgumentException('Некорректное значение поля.');
                $safe=match($kind){'html'=>CatalogSafety::html($value),'url'=>CatalogSafety::url(trim($value)),'image'=>self::imagePath($value),default=>CatalogSafety::text($value,30000,'Текст')};
                $u->execute([$safe,$key]);Factory::audit($this->pdo,$actor,'content',(string)$key,'save');}
        });
    }
    public static function imagePath(string $value):string
    {
        $v=trim($value);if($v==='')return '';
        // Shipped trusted SVG favicon stays permitted; arbitrary SVG uploads are never accepted.
        if($v==='/assets/img/favicon.svg')return $v;
        if(!str_starts_with($v,'/'))$v='/assets/'.ltrim($v,'/');
        return CatalogSafety::image($v);
    }
    public function articles(string $query='',int $page=1):array
    {
        $q=$this->pdo->prepare("SELECT p.id,p.slug,p.title,p.published_at,p.is_active,COALESCE(a.status,IF(p.is_active=1,'published','draft')) status,a.author FROM blog_posts p LEFT JOIN cp_article a ON a.post_id=p.id WHERE p.title LIKE ? OR p.slug LIKE ? ORDER BY p.id DESC LIMIT 50 OFFSET ".(max(0,$page-1)*50));
        $q->execute(['%'.$query.'%','%'.$query.'%']);return $q->fetchAll();
    }
    public function article(int $id):?array
    {
        $q=$this->pdo->prepare('SELECT p.*,a.status,a.author,a.seo_h1,a.canonical,a.noindex,a.og_image,a.cover_alt,a.related_articles,a.related_products FROM blog_posts p LEFT JOIN cp_article a ON a.post_id=p.id WHERE p.id=?');$q->execute([$id]);$r=$q->fetch();return $r?:null;
    }
    public function isVisible(array $row):bool
    {
        return !empty($row['is_active']) && in_array($row['status']??'published',['published','scheduled'],true) && !empty($row['published_at']) && $row['published_at']<=gmdate('Y-m-d H:i:s');
    }
    public static function publicationDate(string $input):?string
    {
        $input=trim($input);if($input==='')return null;
        $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$input,new DateTimeZone('Europe/Moscow'));
        if(!$d || $d->format('Y-m-d\TH:i')!==$input)throw new InvalidArgumentException('Дата публикации: укажите корректные дату и время (Москва).');
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public function saveArticle(array $input,int $actor):int
    {
        $id=max(0,(int)($input['id']??0));$old=$id?$this->article($id):null;if($id&&!$old)throw new InvalidArgumentException('Статья не найдена.');
        $title=CatalogSafety::text($input['title']??'',255,'Заголовок',true);
        $slug=trim((string)($input['slug']??''));
        if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug) || strlen($slug)>190 || in_array($slug,['admin','api','catalog','tovar','stati','korzina','poisk','sitemap','robots'],true))throw new InvalidArgumentException('Адрес: используйте латинские буквы, цифры и дефисы; служебные адреса запрещены.');
        $q=$this->pdo->prepare('SELECT id FROM blog_posts WHERE slug=? AND id<>?');$q->execute([$slug,$id]);if($q->fetchColumn())throw new InvalidArgumentException('Адрес уже занят другой статьёй.');
        if($old && $old['slug']!==$slug && empty($input['confirm_slug']))throw new InvalidArgumentException('Адрес изменён. Подтвердите создание перенаправления со старого адреса.');
        $status=(string)($input['status']??'draft');if(!in_array($status,['draft','published','scheduled','archived'],true))throw new InvalidArgumentException('Недопустимый статус статьи.');
        $date=array_key_exists('_published_utc',$input)?($input['_published_utc']?:null):self::publicationDate((string)($input['published_at']??''));
        if($status==='published' && !$date)$date=gmdate('Y-m-d H:i:s');
        if($status==='scheduled' && !$date)throw new InvalidArgumentException('Для отложенной публикации укажите дату и время (Москва).');
        $values=['title'=>$title,'slug'=>$slug,'excerpt'=>CatalogSafety::text($input['excerpt']??'',3000,'Анонс'),'body_html'=>CatalogSafety::html((string)($input['body_html']??'')),'image_url'=>self::imagePath((string)($input['image_url']??'')),'published_at'=>$date,'is_active'=>in_array($status,['published','scheduled'],true)?1:0,'seo_title'=>CatalogSafety::text($input['seo_title']??'',255,'SEO title'),'seo_description'=>CatalogSafety::text($input['seo_description']??'',1000,'SEO description')];
        $extra=['status'=>$status,'author'=>CatalogSafety::text($input['author']??'',160,'Автор'),'seo_h1'=>CatalogSafety::text($input['seo_h1']??'',255,'H1'),'canonical'=>self::canonical((string)($input['canonical']??'')),'noindex'=>empty($input['noindex'])?0:1,'og_image'=>self::imagePath((string)($input['og_image']??'')),'cover_alt'=>CatalogSafety::text($input['cover_alt']??'',255,'Описание обложки'),'related_articles'=>json_encode($this->validIds($input['related_articles']??[],'blog_posts',$id),JSON_THROW_ON_ERROR),'related_products'=>json_encode($this->validIds($input['related_products']??[],'products'),JSON_THROW_ON_ERROR)];
        return $this->atomic(function()use($id,$old,$values,$extra,$actor):int{
            Catalog::assertHistoricalPath($this->pdo,'/stati/'.$values['slug'],$old?'/stati/'.$old['slug']:null);
            if($old){$prior=$this->pdo->prepare('SELECT id FROM cp_article_revision WHERE post_id=? LIMIT 1');$prior->execute([$id]);if(!$prior->fetchColumn()){$prior=$this->pdo->prepare('INSERT INTO cp_article_revision(post_id,actor_id,snapshot,created_at) VALUES(?,?,?,UTC_TIMESTAMP())');$prior->execute([$id,0,json_encode($old,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);}}
            if($id){$q=$this->pdo->prepare('UPDATE blog_posts SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($values))).' WHERE id=?');$q->execute([...array_values($values),$id]);}
            else{$q=$this->pdo->prepare('INSERT INTO blog_posts('.implode(',',array_keys($values)).',created_at) VALUES('.implode(',',array_fill(0,count($values),'?')).',UTC_TIMESTAMP())');$q->execute(array_values($values));$id=(int)$this->pdo->lastInsertId();}
            $q=$this->pdo->prepare('INSERT INTO cp_article(post_id,'.implode(',',array_keys($extra)).',updated_at) VALUES('.implode(',',array_fill(0,count($extra)+1,'?')).',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE '.implode(',',array_map(fn($k)=>$k.'=VALUES('.$k.')',array_keys($extra))).',updated_at=UTC_TIMESTAMP()');$q->execute([$id,...array_values($extra)]);
            if($old && $old['slug']!==$values['slug'])$this->redirect('/stati/'.$old['slug'],'/stati/'.$values['slug']);
            $snapshot=$this->article($id);$q=$this->pdo->prepare('INSERT INTO cp_article_revision(post_id,actor_id,snapshot,created_at) VALUES(?,?,?,UTC_TIMESTAMP())');$q->execute([$id,$actor,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            Factory::audit($this->pdo,$actor,'article',(string)$id,'save',['status'=>$extra['status']]);return $id;
        });
    }
    private function validIds(mixed $raw,string $table,int $except=0):array
    {
        if(is_string($raw))$raw=preg_split('/[\s,]+/',trim($raw),-1,PREG_SPLIT_NO_EMPTY);
        if(!is_array($raw))throw new InvalidArgumentException('Некорректный список связей.');$ids=[];
        foreach($raw as $v){if(!ctype_digit((string)$v) || (int)$v<1)throw new InvalidArgumentException('Связи: выберите существующие записи.');$v=(int)$v;if($v===$except)continue;$q=$this->pdo->prepare('SELECT id FROM '.$table.' WHERE id=?');$q->execute([$v]);if(!$q->fetchColumn())throw new InvalidArgumentException('Связанная запись не найдена.');$ids[]=$v;}
        return array_values(array_unique($ids));
    }
    public function revisions(int $id):array{$q=$this->pdo->prepare('SELECT id,actor_id,created_at FROM cp_article_revision WHERE post_id=? ORDER BY id DESC');$q->execute([$id]);return $q->fetchAll();}
    public function restoreArticle(int $id,int $revision,int $actor):int
    {
        $q=$this->pdo->prepare('SELECT snapshot FROM cp_article_revision WHERE id=? AND post_id=?');$q->execute([$revision,$id]);$raw=$q->fetchColumn();if(!$raw)throw new InvalidArgumentException('Версия статьи не найдена.');
        $snapshot=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$snapshot['id']=$id;$snapshot['confirm_slug']=1;$snapshot['_published_utc']=$snapshot['published_at'];
        foreach(['related_articles','related_products'] as $k)$snapshot[$k]=json_decode($snapshot[$k]??'[]',true,512,JSON_THROW_ON_ERROR);
        return $this->saveArticle($snapshot,$actor);
    }
    public function redirect(string $from,string $to):void
    {
        if($from===$to)return;
        $q=$this->pdo->prepare('DELETE FROM cp_redirect WHERE old_path=?');$q->execute([$to]);
        $q=$this->pdo->prepare('UPDATE cp_redirect SET new_path=? WHERE new_path=?');$q->execute([$to,$from]);
        $q=$this->pdo->prepare('INSERT INTO cp_redirect(old_path,new_path) VALUES(?,?) ON DUPLICATE KEY UPDATE new_path=VALUES(new_path)');$q->execute([$from,$to]);
    }
    public static function canonical(string $v):string
    {
        $v=trim($v);if($v==='')return '';if(!str_starts_with($v,'/') || str_starts_with($v,'//') || preg_match('/[\x00-\x20<>"\\\\]/',$v))throw new InvalidArgumentException('Canonical: укажите внутренний путь, начинающийся с /.');return $v;
    }
    public static function mapUrl(string $input):string
    {
        $input=trim($input);if($input==='')return '';
        if(preg_match('~<\s*script\b~i',$input))throw new InvalidArgumentException('Карта: JavaScript не принимается.');
        if(str_contains($input,'<')){
            $dom=new \DOMDocument();$prior=libxml_use_internal_errors(true);$dom->loadHTML('<meta charset="utf-8"><body>'.$input.'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($prior);
            $body=$dom->getElementsByTagName('body')->item(0);$elements=[];foreach($body?->childNodes??[] as $node){if($node instanceof \DOMElement)$elements[]=$node;elseif(trim($node->textContent)!=='')throw new InvalidArgumentException('Карта: вставьте один штатный iframe или ссылку виджета.');}
            if(count($elements)!==1 || strtolower($elements[0]->tagName)!=='iframe')throw new InvalidArgumentException('Карта: вставьте один штатный iframe без другого HTML.');
            $frame=$elements[0];foreach($frame->attributes as $attribute){if(str_starts_with(strtolower($attribute->name),'on') || in_array(strtolower($attribute->name),['srcdoc'],true))throw new InvalidArgumentException('Код карты содержит запрещённые атрибуты.');}
            $input=$frame->getAttribute('src');
        }
        $url=parse_url($input);$host=strtolower((string)($url['host']??''));$path=(string)($url['path']??'');
        $allowed=in_array($host,['yandex.ru','www.yandex.ru'],true)&&str_starts_with($path,'/map-widget/v1/') || $host==='api-maps.yandex.ru'&&preg_match('~^/frame/v1/-/[A-Za-z0-9_-]+/?$~D',$path);
        if(($url['scheme']??'')!=='https' || !$allowed || isset($url['user']) || isset($url['pass']) || isset($url['port']) || preg_match('/[\x00-\x20<>"\']/', $input))throw new InvalidArgumentException('Карта: разрешена только HTTPS-ссылка штатного виджета Яндекс Карт.');
        return $input;
    }
    public static function validateSite(array $input):array
    {
        $out=[];foreach(['name','domain','company','tagline','logo_mark','address','office','hours','legal','inn','ogrn','city','messengers','socials','email'] as $key)$out[$key]=CatalogSafety::text($input[$key]??'', $key==='address'||$key==='messengers'||$key==='socials'?3000:500,'Поле «'.$key.'»');
        if($out['name']==='')throw new InvalidArgumentException('Название сайта обязательно.');
        if($out['email']!=='' && !filter_var($out['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Email: проверьте адрес.');
        foreach(['logo','favicon','default_og_image'] as $key)$out[$key]=self::imagePath((string)($input[$key]??''));
        $phones=$input['phones']??[];if(is_string($phones)){$phones=array_map(fn($line)=>['label'=>trim($line),'tel'=>preg_replace('/[^0-9+]/','',$line)],preg_split('/\r?\n/',trim($phones),-1,PREG_SPLIT_NO_EMPTY));}
        if(!is_array($phones))throw new InvalidArgumentException('Телефоны: укажите по одному номеру в строке.');$out['phones']=[];
        foreach($phones as $phone){$label=CatalogSafety::text($phone['label']??'',100,'Телефон');$tel=(string)($phone['tel']??'');if($label==='' && $tel==='')continue;if(!preg_match('/^\+?[0-9]{7,15}$/D',$tel))throw new InvalidArgumentException('Телефон: укажите от 7 до 15 цифр.');$out['phones'][]=['label'=>$label,'tel'=>$tel];}
        $out['primary_phone']=max(0,(int)($input['primary_phone']??0));if($out['phones']!==[] && !isset($out['phones'][$out['primary_phone']]))throw new InvalidArgumentException('Основной телефон: выберите номер из списка.');
        $out['map_widget']=self::mapUrl((string)($input['map_widget']??''));
        foreach(['metrika_id','ga4_id','yandex_verification','google_verification'] as $key)$out[$key]=trim((string)($input[$key]??''));
        if($out['metrika_id']!==''&&!preg_match('/^[1-9][0-9]{0,11}$/D',$out['metrika_id']))throw new InvalidArgumentException('Яндекс Метрика: укажите числовой номер счётчика.');
        if($out['ga4_id']!==''&&!preg_match('/^G-[A-Z0-9]{4,20}$/D',$out['ga4_id']))throw new InvalidArgumentException('Google Analytics4: укажите идентификатор G-…');
        foreach(['yandex_verification','google_verification'] as $key)if($out[$key]!==''&&!preg_match('/^[A-Za-z0-9_=-]{1,255}$/D',$out[$key]))throw new InvalidArgumentException('Подтверждение прав: вставьте только значение content, без HTML.');
        foreach(['messengers','socials'] as $key){$lines=[];foreach(preg_split('/\r?\n/',trim($out[$key]),-1,PREG_SPLIT_NO_EMPTY) as $line){$parts=explode('|',$line,2);$label=trim($parts[0]);$url=CatalogSafety::url(trim($parts[1]??$parts[0]));if(!str_starts_with($url,'https://')&&!str_starts_with($url,'mailto:')&&!str_starts_with($url,'tel:'))throw new InvalidArgumentException('Социальные ссылки: укажите HTTPS-ссылку в формате Название | ссылка.');$lines[]=$label.' | '.$url;}$out[$key]=implode("\n",$lines);}
        $out['analytics_hosts']=['cat-part.ru','www.cat-part.ru'];return $out;
    }
    public function saveSite(array $input,int $actor):void{Factory::saveSettings($this->pdo,'site',self::validateSite($input),$actor);}
    public function saveMail(array $input,int $actor):void
    {
        $from=trim((string)($input['from_email']??''));$notify=$input['notify']??[];if(is_string($notify))$notify=preg_split('/[\s,;]+/',trim($notify),-1,PREG_SPLIT_NO_EMPTY);
        if(!filter_var($from,FILTER_VALIDATE_EMAIL) || !is_array($notify)||$notify===[])throw new InvalidArgumentException('Почта: укажите отправителя и хотя бы одного получателя.');foreach($notify as $email)if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Получатели: проверьте каждый адрес.');
        Factory::saveSettings($this->pdo,'mail',['from_email'=>$from,'from_name'=>CatalogSafety::text($input['from_name']??'',160,'Имя отправителя'),'notify'=>array_values(array_unique($notify))],$actor);
    }
    public static function analyticsHtml(array $site,string $host):string
    {
        if(!in_array(strtolower($host),['cat-part.ru','www.cat-part.ru'],true))return '';
        $id=(string)($site['metrika_id']??'');$ga=(string)($site['ga4_id']??'');$out='';
        if(preg_match('/^[1-9][0-9]{0,11}$/D',$id))$out.='<script>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r)return;}k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js?id='.$id.'","ym");ym('.$id.',"init",{ssr:true,webvisor:true,clickmap:true,ecommerce:"dataLayer",referrer:document.referrer,url:location.href,accurateTrackBounce:true,trackLinks:true});</script>';
        if(preg_match('/^G-[A-Z0-9]{4,20}$/D',$ga)){$json=json_encode($ga,JSON_HEX_TAG|JSON_THROW_ON_ERROR);$out.='<script async src="https://www.googletagmanager.com/gtag/js?id='.htmlspecialchars($ga,ENT_QUOTES).'"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",'.$json.');</script>';}
        return $out;
    }
    public function uploadImage(array $file,int $actor):string
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || (int)($file['size']??0)>10*1024*1024 || !is_uploaded_file((string)($file['tmp_name']??'')))throw new InvalidArgumentException('Изображение: загрузите JPG, PNG, WebP или GIF размером до 10МБ.');
        $tmp=(string)$file['tmp_name'];$info=@getimagesize($tmp);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if(!$info||!isset($types[$mime])||$info['mime']!==$mime||$info[0]*$info[1]>40000000)throw new InvalidArgumentException('Изображение: неподдерживаемое содержимое или размер больше 40мегапикселей.');
        $root=self::siteRoot().'/media/content';if(!is_dir($root)&&!mkdir($root,0755,true))throw new \RuntimeException('Не удалось создать каталог изображений.');$name=bin2hex(random_bytes(16)).'.'.$types[$mime];if(!move_uploaded_file($tmp,$root.'/'.$name))throw new \RuntimeException('Не удалось сохранить изображение.');Factory::audit($this->pdo,$actor,'media',$name,'upload');return '/media/content/'.$name;
    }
    private function atomic(callable $fn):mixed
    {
        $own=!$this->pdo->inTransaction();if($own)$this->pdo->beginTransaction();try{$result=$fn();if($own)$this->pdo->commit();return $result;}catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
