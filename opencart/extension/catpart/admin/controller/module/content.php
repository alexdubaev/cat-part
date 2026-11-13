<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
require_once dirname(__DIR__,3).'/system/autoload.php';
use Catpart\Content as Store;
use Catpart\ContentUi as Ui;
use Catpart\Factory;
use Catpart\CatalogSafety;
final class Content extends \Catpart\AdminController
{
    private function store():Store{return new Store($this->pdo());}
    private function tabs():string
    {
        $html='<nav class="d-flex gap-2 flex-wrap mb-4">';foreach(['articles'=>'Статьи','home'=>'Главная','pages'=>'Страницы','menus'=>'Меню и подвал','forms'=>'Тексты форм'] as $key=>$label)$html.='<a class="btn btn-light" href="'.self::esc($this->url('content','',['section'=>$key])).'">'.self::esc($label).'</a>';return $html.'</nav>';
    }
    public function index():void
    {
        if(!$this->guard('content'))return;$section=(string)($this->request->get['section']??'articles');$html=$this->tabs();
        if($section==='articles'){
            $html.='<a class="btn btn-primary mb-3" href="'.self::esc($this->url('content','edit')).'">Создать статью</a><form method="get"><input type="hidden" name="route" value="extension/catpart/module/content"><input type="hidden" name="user_token" value="'.self::esc($this->session->data['user_token']).'"><label>Найти статью <input name="q" class="form-control" value="'.self::esc($this->request->get['q']??'').'"></label><button class="btn btn-light">Найти</button></form><table class="table"><thead><tr><th>Статья</th><th>Статус</th><th>Публикация (Москва)</th></tr></thead><tbody>';
            foreach($this->store()->articles((string)($this->request->get['q']??''),(int)($this->request->get['page']??1)) as $row){$date=$row['published_at']?(new \DateTimeImmutable($row['published_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i'):'';$html.='<tr><td><a href="'.self::esc($this->url('content','edit',['id'=>$row['id']])).'">'.self::esc($row['title']).'</a></td><td>'.self::esc(['draft'=>'Черновик','published'=>'Опубликована','scheduled'=>'Отложена','archived'=>'Архив'][$row['status']]??$row['status']).'</td><td>'.self::esc($date).'</td></tr>';}
            $page=max(1,(int)($this->request->get['page']??1));$html.='</tbody></table><a href="'.self::esc($this->url('content','',['section'=>'articles','page'=>$page+1,'q'=>$this->request->get['q']??''])).'">Следующие статьи →</a>';
        }elseif(in_array($section,['home','pages','menus','forms'],true)){$html.=$this->sectionForm($section);}
        else{$this->response->addHeader('HTTP/1.1 404 Not Found');$this->response->setOutput('Раздел не найден.');return;}
        $this->page('Содержимое',$html);
    }
    public function edit():void{if(!$this->guard('content'))return;$id=(int)($this->request->get['id']??0);$row=$id?$this->store()->article($id):[];if($id&&!$row){$this->response->addHeader('HTTP/1.1 404 Not Found');return;}$this->editor($row?:[]);}
    private function editor(array $row,string $error=''):void
    {
        $id=(int)($row['id']??0);$html=$this->tabs().Ui::start($this->url('content','save'),$error,(string)($this->request->get['saved']??'')!==''?'Статья сохранена.':'').'<input type="hidden" name="id" value="'.$id.'">';
        foreach(['title'=>'Заголовок статьи','seo_h1'=>'H1 (если отличается от заголовка)','slug'=>'Адрес (slug)','excerpt'=>'Анонс'] as $key=>$label)$html.=Ui::field($key,$label,$row[$key]??'',$key==='excerpt'?'textarea':'text');
        $html.=Ui::field('body_html','Содержание',$row['body_html']??'','rich','Визуальный редактор: H2–H4, таблицы, списки, ссылки, изображения. Вставка из Word очищается; опасный HTML удаляется сервером.');
        foreach(['image_url'=>'Обложка (путь изображения)','cover_alt'=>'Описание обложки (alt)','author'=>'Автор','seo_title'=>'SEO title','seo_description'=>'SEO description','og_image'=>'Изображение для публикации ссылки','canonical'=>'Canonical (внутренний путь; пусто — адрес статьи)'] as $key=>$label)$html.=Ui::field($key,$label,$row[$key]??'',$key==='seo_description'?'textarea':'text');
        $html.=Ui::select('status','Состояние',['draft'=>'Черновик','published'=>'Опубликовать','scheduled'=>'Отложенная публикация','archived'=>'Архив'],$row['status']??'draft');
        $date=(string)($row['published_at']??'');if($date!==''&&!str_contains($date,'T'))$date=(new \DateTimeImmutable($date,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('Y-m-d\TH:i');
        $html.=Ui::field('published_at','Дата и время публикации (Москва)',$date,'datetime-local').Ui::check('noindex','Исключить из индексации и sitemap',!empty($row['noindex']));
        foreach(['related_articles'=>'Связанные статьи','related_products'=>'Связанные товары'] as $key=>$label){$selected=$row[$key]??[];if(is_string($selected))$selected=json_decode($selected,true)?:[];$table=$key==='related_articles'?'blog_posts':'products';$column=$table==='products'?'name':'title';$html.='<label class="form-label">'.self::esc($label).'<select class="form-select" name="'.$key.'[]" multiple size="8">';$rows=$this->pdo()->query('SELECT id,'.$column.' label FROM '.$table.' WHERE is_active=1 ORDER BY '.$column.' LIMIT 10000')->fetchAll();foreach($rows as $choice)$html.='<option value="'.$choice['id'].'"'.(in_array((int)$choice['id'],array_map('intval',$selected),true)?' selected':'').'>'.self::esc(($table==='products'?'#'.$choice['id'].' — ':'').$choice['label']).'</option>';$html.='</select></label>';}
        if($id)$html.=Ui::check('confirm_slug','Подтверждаю изменение адреса и создание прямого301 со старого адреса',!empty($row['confirm_slug']));$html.=Ui::buttons($this->url('content'));
        if($id){$html.='<p><a class="btn btn-light" target="_blank" rel="noopener" href="'.self::esc($this->url('content','preview',['id'=>$id])).'">Предпросмотр сохранённой статьи</a></p><h2>История сохранений</h2>';foreach($this->store()->revisions($id) as $revision)$html.='<form class="mb-2" method="post" action="'.self::esc($this->url('content','restore')).'" onsubmit="return confirm(\'Восстановить предыдущую версию статьи: '.self::esc(str_replace(["'",'"'], '',(string)($row['title']??''))).'?\')"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="revision" value="'.$revision['id'].'"><span>'.self::esc($revision['created_at']).' UTC · сотрудник#'.$revision['actor_id'].'</span> <button class="btn btn-outline-secondary">Восстановить эту версию</button></form>';}
        $html.=Ui::scripts($this->url('content','upload'));$this->page($id?'Редактирование статьи':'Новая статья',$html);
    }
    public function save():void{if(!$this->guard('content',true))return;try{$input=Store::nativeInput($this->request->post);unset($input['_published_utc']);$id=$this->store()->saveArticle($input,(int)$this->user->getId());$this->redirect($this->url('content','edit',['id'=>$id,'saved'=>1]));}catch(\InvalidArgumentException $e){$this->editor(Store::nativeInput($this->request->post),$e->getMessage());}}
    public function restore():void{if(!$this->guard('content',true))return;try{$id=(int)($this->request->post['id']??0);$this->store()->restoreArticle($id,(int)($this->request->post['revision']??0),(int)$this->user->getId());$this->redirect($this->url('content','edit',['id'=>$id,'saved'=>1]));}catch(\InvalidArgumentException $e){$this->page('История статьи','<div class="alert alert-danger">'.self::esc($e->getMessage()).'</div>');}}
    public function preview():void
    {
        if(!$this->guard('content'))return;$row=$this->store()->article((int)($this->request->get['id']??0));if(!$row){$this->response->addHeader('HTTP/1.1 404 Not Found');return;}
        require Store::siteRoot().'/app/bootstrap.php';
        $this->response->addHeader('X-Robots-Tag: noindex, nofollow');$this->response->setOutput(\App\Core\View::render('blog/show',['post'=>$row,'related'=>[],'page'=>['title'=>$row['seo_title']?:$row['title'],'description'=>$row['seo_description']?:$row['excerpt'],'canonical'=>'/stati/'.$row['slug'],'noindex'=>true,'og_image'=>$row['og_image']?:$row['image_url']]]));
    }
    public function upload():void
    {
        if(!$this->guard('content',true))return;$this->response->addHeader('Content-Type: application/json; charset=utf-8');try{$url=$this->store()->uploadImage($this->request->files['image']??[],(int)$this->user->getId());$this->response->setOutput(json_encode(['url'=>$url],JSON_THROW_ON_ERROR));}catch(\InvalidArgumentException $e){$this->response->addHeader('HTTP/1.1 422 Unprocessable Entity');$this->response->setOutput(json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
    }
    private function sectionForm(string $section,string $error='',?array $posted=null):string
    {
        $html=Ui::start($this->url('content','saveSection'),$error,!empty($this->request->get['saved'])?'Изменения сохранены.':'').'<input type="hidden" name="section" value="'.self::esc($section).'">';
        if($section==='menus'){
            $menus=$posted['menus']??Factory::settings($this->pdo(),'menus');foreach(['header'=>'Шапка','footer_catalog'=>'Подвал: каталог','footer_buyers'=>'Подвал: покупателям','footer_company'=>'Подвал: компания'] as $key=>$label){$html.='<h2>'.self::esc($label).'</h2><div id="menu-'.$key.'">';$rows=(array)($menus[$key]??[]);$rows[]=['label'=>'','url'=>'','enabled'=>true,'sort_order'=>(count($rows)+1)*10];foreach($rows as $i=>$row)$html.='<div class="row border-bottom py-2"><div class="col-md-4">'.Ui::field('menus['.$key.']['.$i.'][label]','Подпись',$row['label']??'').'</div><div class="col-md-5">'.Ui::field('menus['.$key.']['.$i.'][url]','Адрес ссылки',$row['url']??'').'</div><div class="col-md-2">'.Ui::field('menus['.$key.']['.$i.'][sort_order]','Порядок',$row['sort_order']??0,'number').'</div><div class="col-md-1">'.Ui::check('menus['.$key.']['.$i.'][enabled]','Видна',!empty($row['enabled'])).'</div></div>';$html.='</div>';}
        }elseif($section==='forms'){$forms=$posted['forms']??Factory::settings($this->pdo(),'forms');$labels=['lead_title'=>'Общий заголовок (пусто — заголовок блока)','lead_hint'=>'Пояснение к форме','lead_note'=>'Подсказка для подбора','lead_button'=>'Кнопка заявки','lead_consent'=>'Текст согласия','checkout_title'=>'Заголовок оформления','checkout_hint'=>'Условия оформления','checkout_button'=>'Кнопка оформления','checkout_consent'=>'Согласие при оформлении'];foreach(['name_label'=>'Подпись имени','phone_label'=>'Подпись телефона','email_label'=>'Подпись email','message_label'=>'Подпись сообщения','comment_label'=>'Подпись комментария'] as $key=>$label)$labels[$key]=$label;foreach($labels as $key=>$label)$html.=Ui::field('forms['.$key.']',$label,$forms[$key]??'','textarea');foreach(['home'=>'Главная','contacts'=>'Контакты','catalog'=>'Каталог','product'=>'Товар (пусто — артикул)'] as $key=>$label)$html.=Ui::field('forms[lead_titles]['.$key.']','Заголовок формы: '.$label,$forms['lead_titles'][$key]??'');}
        else{
            $pageKey=(string)($posted['page_key']??$this->request->get['page_key']??'about');
            if($section==='pages'){
                $pageChoices=['about'=>'О компании','delivery'=>'Доставка','payment'=>'Оплата','warranty'=>'Гарантия','contacts'=>'Контакты','privacy'=>'Политика конфиденциальности','articles'=>'Раздел статей','home'=>'Поисковое представление главной'];
                if(!isset($pageChoices[$pageKey]))$pageKey='about';
                $html.='<nav class="d-flex gap-2 flex-wrap mb-4">';foreach($pageChoices as $key=>$label)$html.='<a class="btn btn-light" href="'.self::esc($this->url('content','',['section'=>'pages','page_key'=>$key])).'">'.self::esc($label).'</a>';$html.='</nav><input type="hidden" name="page_key" value="'.self::esc($pageKey).'">';
            }
            if($section==='home'){$home=$posted['home']??Factory::settings($this->pdo(),'home');$html.='<h2>Блоки главной</h2>';foreach(['hero'=>'Первый экран и тезисы','categories'=>'Каталог','products'=>'Товары','about'=>'О компании','benefits'=>'Преимущества','help'=>'Помощь','lead'=>'Форма подбора','articles'=>'Статьи'] as $key=>$label)$html.='<div class="row border-bottom"><div class="col-md-8">'.Ui::check('home[blocks]['.$key.'][enabled]',$label,!empty($home['blocks'][$key]['enabled'])).'</div><div class="col-md-4">'.Ui::field('home[blocks]['.$key.'][sort_order]','Порядок',$home['blocks'][$key]['sort_order']??0,'number').'</div></div>';
                $html.=Ui::select('home[products_mode]','Подборка товаров',['automatic'=>'Автоматически: активные товары из категорий','manual'=>'Ручной выбор'],$home['products_mode']??'automatic');$selected=array_map('intval',(array)($home['product_ids']??[]));$html.='<label>Товары ручной подборки<select class="form-select" name="home[product_ids][]" multiple size="8">';foreach($this->pdo()->query('SELECT id,sku,name FROM products WHERE is_active=1 ORDER BY name')->fetchAll() as $r)$html.='<option value="'.$r['id'].'"'.(in_array((int)$r['id'],$selected,true)?' selected':'').'>'.self::esc($r['sku'].' — '.$r['name']).'</option>';$html.='</select></label><h2>Первые разделы каталога</h2>';
                $categories=$this->pdo()->query('SELECT slug,name FROM categories WHERE parent_id IS NULL OR parent_id=0 ORDER BY sort,name')->fetchAll();
                $choices=[''=>'Не показывать в первом ряду'];foreach($categories as $category)$choices[$category['slug']]=$category['name'];
                $featured=(array)($home['featured_categories']??[]);for($i=0;$i<max(6,count($featured));$i++)$html.=Ui::select('home[featured_categories]['.$i.']','Раздел №'.($i+1),$choices,$featured[$i]??'');
                $html.='<h2>Тексты, изображения и ссылки</h2>';
            }
            $prefix=$section==='home'?'home':'pages.'.$pageKey;$fields=in_array($pageKey,['articles','home'],true)&&$section==='pages'?[]:$this->store()->fields($prefix);$last='';foreach($fields as $row){$parts=explode('.',$row['content_key']);$group=$parts[0].'.'.($parts[1]??'');if($group!==$last){$html.='<h3>'.self::esc((['home.text'=>'Тексты главной','home.image'=>'Изображения','home.link'=>'Ссылки','home.attr'=>'Подписи','home.hero_image'=>'Фоновое изображение','home.benefit_image'=>'Изображения преимуществ','pages.about'=>'О компании','pages.delivery'=>'Доставка','pages.payment'=>'Оплата','pages.warranty'=>'Гарантия','pages.contacts'=>'Контакты','pages.privacy'=>'Политика конфиденциальности'][$group]??'Содержимое')).'</h3>';$last=$group;}$value=$posted['fields'][$row['content_key']]??$row['body'];$html.=Ui::field('fields['.$row['content_key'].']',$row['title'],$value,$row['kind']==='html'?'rich':($row['kind']==='text'?'textarea':'text'),$row['kind']==='image'?'Путь сохранённого изображения; новое изображение можно загрузить ниже.':'');}
            if($section==='pages'){$seo=$posted['seo']??Factory::settings($this->pdo(),'page_seo');foreach($seo as $path=>$meta){$pagePaths=['about'=>'/o-kompanii','delivery'=>'/dostavka','payment'=>'/oplata','warranty'=>'/garantiya','contacts'=>'/kontakty','privacy'=>'/politika-konfidencialnosti','articles'=>'/stati','home'=>'/'];if(($pagePaths[$pageKey]??'')!==$path)continue;$html.='<h2>Поисковое представление: '.self::esc($path).'</h2>';foreach(['title'=>'Title','description'=>'Description','seo_h1'=>'H1','canonical'=>'Canonical','og_image'=>'Изображение ссылки'] as $key=>$label)$html.=Ui::field('seo['.$path.']['.$key.']',$label,$meta[$key]??'');$html.=Ui::check('seo['.$path.'][noindex]','Исключить из индексации и sitemap',!empty($meta['noindex']));}}
        }
        if($section==='menus') foreach(['partials.header','partials.footer'] as $group) {
            $html.='<h2>'.($group==='partials.header'?'Подписи шапки':'Подписи подвала').'</h2>';
            foreach($this->store()->fields($group) as $field) {
                if(str_contains($field['content_key'],'.image.'))continue;
                $html.=Ui::field('fields['.$field['content_key'].']',$field['title'],$posted['fields'][$field['content_key']]??$field['body'],'textarea');
            }
        }
        if($section==='pages' && $pageKey==='articles') foreach($this->store()->fields('blog') as $field) $html.=Ui::field('fields['.$field['content_key'].']',$field['title'],$posted['fields'][$field['content_key']]??$field['body'],'textarea');
        return $html.Ui::buttons().Ui::scripts($this->url('content','upload'));
    }
    public function saveSection():void
    {
        if(!$this->guard('content',true))return;$data=Store::nativeInput($this->request->post);$section=(string)($data['section']??'');try{
            if(!in_array($section,['home','pages','menus','forms'],true))throw new \InvalidArgumentException('Раздел не найден.');$actor=(int)$this->user->getId();
            if($section==='menus'){$menus=[];foreach(['header','footer_catalog','footer_buyers','footer_company'] as $key){$menus[$key]=[];foreach((array)($data['menus'][$key]??[]) as $row){$label=CatalogSafety::text($row['label']??'',160,'Подпись ссылки');$url=CatalogSafety::url((string)($row['url']??''));if($label===''&&$url==='')continue;if($label===''||$url==='')throw new \InvalidArgumentException('У ссылки должны быть подпись и адрес.');$menus[$key][]=['label'=>$label,'url'=>$url,'enabled'=>!empty($row['enabled']),'sort_order'=>(int)($row['sort_order']??0)];}}$pdo=$this->pdo();$pdo->beginTransaction();try{$this->store()->saveFields((array)($data['fields']??[]),$actor);Factory::saveSettings($pdo,'menus',$menus,$actor);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
            elseif($section==='forms'){$forms=[];foreach((array)($data['forms']??[]) as $key=>$value){if($key==='lead_titles'){foreach((array)$value as $source=>$title)$forms[$key][$source]=CatalogSafety::text($title,500,'Заголовок формы');}else $forms[$key]=CatalogSafety::text($value,3000,'Текст формы');}Factory::saveSettings($this->pdo(),'forms',$forms,$actor);}
            else{
                // Validate all groups before persisting, then make one transaction for page/home save.
                $pdo=$this->pdo();$pdo->beginTransaction();try{$this->store()->saveFields((array)($data['fields']??[]),$actor);
                    if($section==='home'){$existing=Factory::settings($pdo,'home');$home=$data['home']??[];$blocks=[];foreach(array_keys($existing['blocks']??[]) as $key)$blocks[$key]=['enabled'=>!empty($home['blocks'][$key]['enabled']),'sort_order'=>(int)($home['blocks'][$key]['sort_order']??0)];$ids=array_values(array_unique(array_map('intval',(array)($home['product_ids']??[]))));foreach($ids as $id){$q=$pdo->prepare('SELECT id FROM products WHERE id=? AND is_active=1');$q->execute([$id]);if(!$q->fetchColumn())throw new \InvalidArgumentException('Товар подборки недоступен.');}$mode=(string)($home['products_mode']??'automatic');if(!in_array($mode,['automatic','manual'],true))throw new \InvalidArgumentException('Выберите режим подборки.');Factory::saveSettings($pdo,'home',array_replace($existing,['blocks'=>$blocks,'products_mode'=>$mode,'product_ids'=>$ids,'featured_categories'=>array_values(array_unique(array_filter(array_map('strval',(array)($home['featured_categories']??$existing['featured_categories']??[])))))]),$actor);}
                    elseif(isset($data['seo'])){$seo=[];foreach((array)$data['seo'] as $path=>$meta){$path=Store::canonical((string)$path);$seo[$path]=['title'=>CatalogSafety::text($meta['title']??'',255,'Title'),'description'=>CatalogSafety::text($meta['description']??'',1000,'Description'),'seo_h1'=>CatalogSafety::text($meta['seo_h1']??'',255,'H1'),'canonical'=>Store::canonical((string)($meta['canonical']??''))?:$path,'og_image'=>Store::imagePath((string)($meta['og_image']??'')),'noindex'=>!empty($meta['noindex'])];}Factory::saveSettings($pdo,'page_seo',array_replace(Factory::settings($pdo,'page_seo'),$seo),$actor);}$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            }
            $this->redirect($this->url('content','',['section'=>$section,'saved'=>1,'page_key'=>$data['page_key']??'about']));
        }catch(\InvalidArgumentException $e){$this->page('Содержимое',$this->tabs().$this->sectionForm($section,$e->getMessage(),$data));}
    }
}
