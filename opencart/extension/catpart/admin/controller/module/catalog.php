<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
use Catpart\Catalog as Store;
use Catpart\CatalogSafety;

final class Catalog extends \Catpart\AdminController
{
    private const TYPES=['product'=>'Товары','category'=>'Категории','brand'=>'Бренды','attribute'=>'Характеристики'];
    public function index():void
    {
        if(!$this->guard('catalog'))return;$type=$this->type();$query=is_string($this->request->get['q']??null)?trim(html_entity_decode($this->request->get['q'],ENT_QUOTES|ENT_HTML5,'UTF-8')):'';$page=max(1,(int)($this->request->get['page']??1));$store=new Store($this->pdo());$r=$store->listing($type,$query,$page);
        $html=$this->navigation($type).'<div class="d-flex gap-3 my-3"><a class="btn btn-primary" href="'.self::esc($this->url('catalog','edit',['type'=>$type])).'">Создать запись</a><a class="btn btn-outline-secondary" href="'.self::esc($this->url('imports')).'">Импорт CSV / XLSX</a></div>';
        if(!empty($this->session->data['cp_catalog_success'])){$html.='<div class="alert alert-success">'.self::esc($this->session->data['cp_catalog_success']).'</div>';unset($this->session->data['cp_catalog_success']);}
        $html.='<form method="get" action="index.php" class="input-group mb-3"><input type="hidden" name="route" value="extension/catpart/module/catalog"><input type="hidden" name="user_token" value="'.self::esc($this->session->data['user_token']).'"><input type="hidden" name="type" value="'.self::esc($type).'"><input class="form-control" name="q" value="'.self::esc($query).'" placeholder="Название или артикул"><button class="btn btn-secondary">Найти</button></form><p>Записей: '.(int)$r['total'].'</p><div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr><th>Название</th><th>Артикул / код</th><th>Состояние</th><th></th></tr></thead><tbody>';
        foreach($r['rows'] as $row)$html.='<tr><td style="overflow-wrap:anywhere">'.self::esc($row['name']).'</td><td>'.self::esc($row['sku']??$row['code']??'').'</td><td>'.($type==='attribute'?'Определение характеристики':(!empty($row['is_active'])?'Опубликовано':'Скрыто / архив')).'</td><td><a href="'.self::esc($this->url('catalog','edit',['type'=>$type,'id'=>$row['id']])).'">Открыть</a></td></tr>';
        $html.='</tbody></table></div><nav aria-label="Страницы">';if($page>1)$html.='<a class="btn btn-light" href="'.self::esc($this->url('catalog','',['type'=>$type,'q'=>$query,'page'=>$page-1])).'">← Назад</a> ';$html.='Страница '.$page.' из '.$r['pages'];if($page<$r['pages'])$html.=' <a class="btn btn-light" href="'.self::esc($this->url('catalog','',['type'=>$type,'q'=>$query,'page'=>$page+1])).'">Далее →</a>';$html.='</nav>';$this->page(self::TYPES[$type],$html);
    }
    public function edit():void
    {
        if(!$this->guard('catalog'))return;$type=$this->type();$id=(int)($this->request->get['id']??0);$row=$id?(new Store($this->pdo()))->record($type,$id):[];
        if($id && !$row){$this->response->addHeader('HTTP/1.1 404 Not Found');$this->page('Запись не найдена','<p>Проверьте выбранный раздел.</p>');return;}$this->form($type,$row?:[]);
    }
    public function save():void
    {
        if(!$this->guard('catalog',true))return;$type=$this->type();$input=$this->posted($this->request->post);$store=new Store($this->pdo());$id=(int)($input['id']??0);
        try{
            $siteRoot=dirname(__DIR__,6).'/local-dev/site';
            foreach(['main_upload'=>'image_url','logo_upload'=>'logo_url'] as $field=>$target)if((int)($this->request->files[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$input[$target]=$store->uploadImage($this->request->files[$field],$siteRoot);
            if($type==='product'){
                if ((int)($this->request->files['main_upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) { $next=count($input['images']??[]); $input['images'][$next]=['url'=>$input['image_url'],'alt'=>$input['name']??'','caption'=>'']; $input['primary_image']=(string)$next; }
                foreach(['category_ids','related_ids','analog_ids'] as $key)$input[$key]=$input[$key]??[];
                $files=$this->request->files['gallery_upload']??[];foreach((array)($files['error']??[]) as $i=>$error)if((int)$error!==UPLOAD_ERR_NO_FILE){$file=[];foreach(['name','type','tmp_name','error','size'] as $k)$file[$k]=$files[$k][$i];$input['images'][$i]['url']=$store->uploadImage($file,$siteRoot);}
            }
            $saved=$store->save($type,$input,(int)$this->user->getId(),!empty($input['confirm_slug']));$this->session->data['cp_catalog_success']='Запись сохранена. Изменения доступны на витрине для опубликованных записей.';$this->redirect($this->url('catalog','edit',['type'=>$type,'id'=>$saved,'saved'=>1]));
        }catch(\InvalidArgumentException $e){$old=$id?$store->record($type,$id):[];$this->form($type,array_replace($old??[],$input),$e->getMessage());}
    }
    public function archive():void
    {
        if(!$this->guard('catalog',true))return;$type=$this->type();$id=(int)($this->request->post['id']??0);$store=new Store($this->pdo());try{$store->archive($type,$id,html_entity_decode((string)($this->request->post['confirmed_name']??''),ENT_QUOTES|ENT_HTML5,'UTF-8'),(int)$this->user->getId());$this->session->data['cp_catalog_success']='Запись скрыта и помещена в архив. Все связи и прошлые заказы сохранены.';$this->redirect($this->url('catalog','',['type'=>$type]));}catch(\InvalidArgumentException $e){$this->form($type,$store->record($type,$id)??[],$e->getMessage());}
    }
    private function form(string $type,array $r,string $error=''):void
    {
        $store=new Store($this->pdo());$id=(int)($r['id']??0);$html=$this->navigation($type).'<p><a href="'.self::esc($this->url('catalog','',['type'=>$type])).'">← К списку</a></p>';
        if($error!=='')$html.='<div class="alert alert-danger" role="alert">'.self::esc($error).' Введённые данные сохранены.</div>';
        elseif(!empty($this->request->get['saved']))$html.='<div class="alert alert-success">Сохранено.</div>';
        $html.='<form method="post" enctype="multipart/form-data" action="'.self::esc($this->url('catalog','save',['type'=>$type])).'"><input type="hidden" name="id" value="'.$id.'">';
        $html.=$this->field('name','Название',$r['name']??'','text',true);
        if($type==='attribute'){
            $html.=$this->field('code','Код характеристики',$r['code']??'','text',true).$this->field('unit','Единица измерения',$r['unit']??'').$this->select('type','Тип значения',['str'=>'Текст','num'=>'Число','enum'=>'Перечень'],$r['type']??'str').$this->field('sort','Порядок',$r['sort']??100,'number').$this->check('is_filterable','Использовать в фильтрах',!empty($r['is_filterable']));
        }else{
            $html.=$this->field('slug','Адрес (slug)',$r['slug']??'').$this->check('confirm_slug','Подтверждаю смену адреса и прямой 301 со старого адреса',false).$this->select('status','Публикация',['draft'=>'Черновик','published'=>'Опубликовано','archived'=>'Архив'],$r['status']??'draft');
            if($type==='product'){
                $html.=$this->field('sku','Артикул',$r['sku']??'','text',true).$this->select('category_id','Основная категория',$this->options($store->choices('category')),$r['category_id']??'').$this->select('category_ids[]','Дополнительные категории',$this->options($store->choices('category')),$r['category_ids']??[],true).$this->select('brand_id','Бренд',$this->options($store->choices('brand')),$r['brand_id']??'');
                $html.=$this->select('price_mode','Режим цены',['request'=>'По запросу','fixed'=>'Указанная цена'],$r['price_mode']??'request').$this->field('price','Цена',$r['price']??'').$this->field('currency','Валюта (код)',$r['currency']??'RUB').$this->check('in_stock','В наличии (признак, без количественного остатка)',!empty($r['in_stock'])).$this->field('delivery_terms','Условия поставки',$r['delivery_terms']??'','textarea').$this->field('short_description','Краткое описание',$r['short_description']??'','textarea').$this->field('body_html','Полное описание',$r['body_html']??'','rich');
                $html.=$this->field('image_url','Основное изображение: адрес файла',$r['image_url']??'').$this->field('main_upload','Загрузить основное изображение','','file');
                $images=$r['images']??[];$images[]=[];$html.='<h2>Галерея</h2><p>Выберите основное фото. Пустая строка не сохраняется. Для удаления очистите адрес; для добавления используйте кнопку.</p><div class="table-responsive"><table class="table" data-collection="images"><thead><tr><th>Основное</th><th>Изображение / загрузка</th><th>Alt</th><th>Подпись</th></tr></thead><tbody>';
                foreach($images as $i=>$image)$html.='<tr><td><input type="radio" aria-label="Основное фото '.($i+1).'" name="primary_image" value="'.$i.'" '.(isset($r['primary_image']) ? ((string)$r['primary_image']===(string)$i?'checked':'') : (($image['is_primary']??false)||$i===0?'checked':'')).'></td><td><input class="form-control" aria-label="Адрес изображения" name="images['.$i.'][url]" value="'.self::esc($image['url']??'').'"><input type="file" accept="image/png,image/jpeg,image/webp,image/gif" name="gallery_upload['.$i.']" aria-label="Загрузить фото"></td><td><input class="form-control" aria-label="Alt изображения" name="images['.$i.'][alt]" value="'.self::esc($image['alt']??'').'"></td><td><input class="form-control" aria-label="Подпись изображения" name="images['.$i.'][caption]" value="'.self::esc($image['caption']??'').'"></td></tr>';
                $html.='</tbody></table></div><button class="btn btn-light mb-3" type="button" data-add="images">Добавить фото</button>';
                $cross=$r['cross']??[];$cross[]=[];$html.='<h2>OEM и кросс-номера</h2><table class="table" data-collection="cross"><thead><tr><th>Номер</th><th>Бренд</th><th>Тип</th></tr></thead><tbody>';
                foreach($cross as $i=>$row){$html.='<tr><td><input class="form-control" aria-label="Кросс-номер" name="cross['.$i.'][cross_sku]" value="'.self::esc($row['cross_sku']??'').'"></td><td><input class="form-control" aria-label="Бренд кросса" name="cross['.$i.'][brand_name]" value="'.self::esc($row['brand_name']??'').'"></td><td>'.$this->bareSelect('cross['.$i.'][kind]',['oem'=>'OEM','analog'=>'Аналог','replaced_by'=>'Заменён на','replaces'=>'Заменяет'],$row['kind']??'analog').'</td></tr>';}
                $html.='</tbody></table><button class="btn btn-light mb-3" type="button" data-add="cross">Добавить номер</button>';
                $attrs=$r['attributes']??[];$attrs[]=[];$choices=$this->options($store->choices('attribute'));$html.='<h2>Характеристики товара</h2><table class="table" data-collection="attributes"><thead><tr><th>Характеристика</th><th>Значение</th></tr></thead><tbody>';
                foreach($attrs as $i=>$row)$html.='<tr><td>'.$this->bareSelect('attributes['.$i.'][attribute_id]',$choices,$row['attribute_id']??'').'</td><td><input class="form-control" aria-label="Значение характеристики" name="attributes['.$i.'][value_str]" value="'.self::esc(($row['value_str']??'')!==''?($row['value_str']??''):($row['value_num']??'')).'"></td></tr>';
                $html.='</tbody></table><button class="btn btn-light mb-3" type="button" data-add="attributes">Добавить характеристику</button>';$products=$this->options($store->choices('product'));unset($products[$id]);$html.=$this->select('related_ids[]','Связанные товары',$products,$r['related_ids']??[],true).$this->select('analog_ids[]','Аналоги',$products,$r['analog_ids']??[],true);
            }else{
                if($type==='category')$html.=$this->select('parent_id','Родительская категория',$this->options($store->choices('category')),$r['parent_id']??'').$this->field('image_url','Изображение: адрес файла',$r['image_url']??'').$this->field('main_upload','Загрузить изображение','','file');
                else $html.=$this->field('logo_url','Логотип: адрес файла',$r['logo_url']??'').$this->field('logo_upload','Загрузить логотип','','file').$this->check('is_oem','Оригинальный производитель',!empty($r['is_oem']));
                $html.=$this->field('intro_html','Описание',$r['intro_html']??'','rich').$this->field('sort','Порядок',$r['sort']??100,'number');
            }
            $html.='<h2>Поисковое представление</h2>';foreach(['seo_h1'=>'H1','seo_title'=>'Title','seo_description'=>'Description','canonical'=>'Canonical (пусто — текущий адрес)','og_image'=>'Изображение для ссылки'] as $key=>$label)$html.=$this->field($key,$label,$r[$key]??'');$html.=$this->check('noindex','Не включать в индекс и sitemap',!empty($r['noindex']));
        }
        $html.='<div class="my-3"><button class="btn btn-primary" type="submit">Сохранить</button></div></form>';
        if($id && $type!=='attribute')$html.='<hr><form method="post" action="'.self::esc($this->url('catalog','archive',['type'=>$type])).'"><input type="hidden" name="id" value="'.$id.'"><label><input required type="checkbox" name="confirmed_name" value="'.self::esc($r['name']??'').'"> Подтверждаю архивирование «'.self::esc($r['name']??'').'». Запись скроется; связи и история сохранятся.</label><p><button class="btn btn-outline-danger">В архив</button></p></form>';
        $html.='<script src="view/javascript/ckeditor/ckeditor.js"></script><script>document.querySelectorAll("textarea[data-rich]").forEach(function(e){if(window.CKEDITOR)CKEDITOR.replace(e,{height:260,language:"ru",removePlugins:"iframe,flash,forms",format_tags:"p;h2;h3;h4",allowedContent:true});});document.querySelectorAll("[data-add]").forEach(function(b){b.addEventListener("click",function(){let key=b.dataset.add,t=document.querySelector("[data-collection=\""+key+"\"] tbody"),row=t.lastElementChild.cloneNode(true),n=t.children.length;row.querySelectorAll("input,select").forEach(function(e){e.name=e.name.replace(/\\[\\d+\\]/,"["+n+"]");if(e.type==="radio"){e.checked=false;e.value=n;}else if(e.tagName==="SELECT")e.selectedIndex=0;else e.value="";});t.appendChild(row);});});</script>';
        $this->page(($id?'Редактирование: ':'Создание: ').self::TYPES[$type],$html);
    }
    private function posted(mixed $value):mixed { if(is_array($value))return array_map([$this,'posted'],$value);return is_string($value)?html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8'):$value; }
    private function type():string{$t=(string)($this->request->get['type']??'product');return isset(self::TYPES[$t])?$t:'product';}
    private function navigation(string $active):string{$h='<nav class="nav nav-tabs">';foreach(self::TYPES as $key=>$name)$h.='<a class="nav-link '.($active===$key?'active':'').'" href="'.self::esc($this->url('catalog','',['type'=>$key])).'">'.$name.'</a>';return $h.'</nav>';}
    private function field(string $key,string $label,mixed $value,string $type='text',bool $required=false):string{$id='cp-'.$key;$h='<div class="mb-3"><label class="form-label" for="'.$id.'">'.self::esc($label).'</label>';if(in_array($type,['textarea','rich'],true))$h.='<textarea '.($type==='rich'?'data-rich':'').' class="form-control" rows="4" id="'.$id.'" name="'.$key.'">'.self::esc($value).'</textarea>';else $h.='<input class="form-control" id="'.$id.'" name="'.$key.'" type="'.$type.'" '.($type==='file'?'accept="image/png,image/jpeg,image/webp,image/gif"':'value="'.self::esc($value).'"').' '.($required?'required':'').'>';return $h.'</div>';}
    private function check(string $key,string $label,bool $on):string{return '<div class="form-check mb-3"><input type="hidden" name="'.$key.'" value="0"><input class="form-check-input" type="checkbox" id="cp-'.$key.'" name="'.$key.'" value="1" '.($on?'checked':'').'><label class="form-check-label" for="cp-'.$key.'">'.self::esc($label).'</label></div>';}
    private function options(array $rows):array{$out=[''=>'— Не выбрано —'];foreach($rows as $r)$out[$r['id']]=$r['name'].' [#'.$r['id'].']';return $out;}
    private function select(string $key,string $label,array $options,mixed $selected,bool $multiple=false):string{return '<div class="mb-3"><label class="form-label">'.self::esc($label).$this->bareSelect($key,$options,$selected,$multiple).'</label></div>';}
    private function bareSelect(string $key,array $options,mixed $selected,bool $multiple=false):string{$values=array_map('strval',is_array($selected)?$selected:[$selected]);$h='<select class="form-select" name="'.$key.'" '.($multiple?'multiple size="6"':'').'>';foreach($options as $value=>$label)$h.='<option value="'.self::esc($value).'" '.(in_array((string)$value,$values,true)?'selected':'').'>'.self::esc($label).'</option>';return $h.'</select>';}
}
