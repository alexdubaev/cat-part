<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
require_once dirname(__DIR__,3).'/system/autoload.php';
use Catpart\Content as Store;
use Catpart\ContentUi as Ui;
use Catpart\Factory;
use Catpart\CatalogSafety;
final class Settings extends \Catpart\AdminController
{
    public function index():void{if(!$this->guard('settings'))return;$this->form((string)($this->request->get['tab']??'site'));}
    private function form(string $tab,string $error='',?array $posted=null):void
    {
        if(!in_array($tab,['site','contacts','analytics','mail','legal'],true))$tab='site';$site=Factory::settings($this->pdo(),'site');$mail=Factory::settings($this->pdo(),'mail');$legal=Factory::settings($this->pdo(),'legal');
        if($posted!==null){if($tab==='mail')$mail=array_replace($mail,$posted);elseif($tab==='legal')$legal=array_replace($legal,$posted);else $site=array_replace($site,$posted);}
        $html='<nav class="d-flex gap-2 flex-wrap mb-4">';foreach(['site'=>'Сайт и оформление','contacts'=>'Контакты и карта','analytics'=>'Аналитика и подтверждение прав','mail'=>'Уведомления','legal'=>'Правовые сведения'] as $key=>$label)$html.='<a class="btn btn-light" href="'.self::esc($this->url('settings','',['tab'=>$key])).'">'.self::esc($label).'</a>';$html.='</nav>'.Ui::start($this->url('settings','save'),$error,!empty($this->request->get['saved'])?'Настройки сохранены.':'').'<input type="hidden" name="tab" value="'.self::esc($tab).'">';
        if($tab==='site'){
            foreach(['name'=>'Название сайта','domain'=>'Публичный домен','company'=>'Название компании','tagline'=>'Подпись бренда','logo_mark'=>'Текстовый знак','logo'=>'Логотип','favicon'=>'Favicon','default_og_image'=>'Изображение ссылки по умолчанию'] as $key=>$label)$html.=Ui::field($key,$label,$site[$key]??'','text',in_array($key,['logo','favicon','default_og_image'],true)?'Укажите путь изображения. Новые изображения загружаются ниже.':'');
        }elseif($tab==='contacts'){
            foreach(['email'=>'Email','address'=>'Адрес','office'=>'Офис','city'=>'Город (структурированные данные)','hours'=>'Часы работы','messengers'=>'Мессенджеры','socials'=>'Социальные ссылки'] as $key=>$label)$html.=Ui::field($key,$label,$site[$key]??'',in_array($key,['messengers','socials'],true)?'textarea':'text',in_array($key,['messengers','socials'],true)?'По одной ссылке в строке: Название | https://…':'');
            $phones=$site['phones']??[];$phoneText=is_array($phones)?implode("\n",array_column($phones,'label')):(string)$phones;$html.=Ui::field('phones','Телефоны, по одному в строке',$phoneText,'textarea');$choices=[];foreach(is_array($phones)?$phones:[] as $i=>$phone)$choices[$i]=$phone['label'];if($choices===[])$choices[0]='Первый номер';$html.=Ui::select('primary_phone','Основная кнопка звонка',$choices,$site['primary_phone']??0);
            $html.=Ui::field('map_widget','Код или ссылка виджета Яндекс Карт',$site['map_widget']??'','textarea','Принимается один штатный iframe или HTTPS-ссылка виджета Яндекс Карт. Очистите поле, чтобы убрать карту.');
            if(!empty($site['map_widget'])){try{$map=Store::mapUrl((string)$site['map_widget']);$html.='<h2>Предпросмотр карты</h2><iframe title="Карта офиса" src="'.self::esc($map).'" width="100%" height="320" loading="lazy" style="border:0"></iframe>';}catch(\InvalidArgumentException){}}
        }elseif($tab==='analytics'){
            $html.='<p>Введите только номер счётчика или значение content, без HTML. Очистка поля отключает подключение. На локальном стенде и в админке счётчики не запускаются. Метатег не означает, что права уже подтверждены в кабинете сервиса.</p>';
            foreach(['metrika_id'=>'Яндекс Метрика — номер счётчика','ga4_id'=>'Google Analytics4 — ID измерения','yandex_verification'=>'Яндекс Вебмастер — значение content','google_verification'=>'Google Search Console — значение content'] as $key=>$label)$html.=Ui::field($key,$label,$site[$key]??'');
        }elseif($tab==='mail'){
            $html.='<p>Заказы и обращения сохраняются перед уведомлением. Локальный стенд перехватывает письма в приватный каталог; реальная отправка выключена. Принятие сервером не означает попадание во Входящие.</p>';
            $html.=Ui::field('from_email','Адрес отправителя',$mail['from_email']??'','email').Ui::field('from_name','Имя отправителя',$mail['from_name']??'').Ui::field('notify','Получатели, по одному адресу в строке',is_array($mail['notify']??null)?implode("\n",$mail['notify']):($mail['notify']??''),'textarea');
        }else{
            foreach(['legal'=>'Юридическое наименование','inn'=>'ИНН','ogrn'=>'ОГРН'] as $key=>$label)$html.=Ui::field($key,$label,$site[$key]??'');$html.=Ui::field('disclaimer','Правовая оговорка в подвале',$legal['disclaimer']??'','textarea');
        }
        $html.=Ui::buttons();if($tab==='site')$html.=Ui::scripts($this->url('settings','upload'));$this->page('Настройки сайта',$html);
    }
    public function save():void
    {
        if(!$this->guard('settings',true))return;$posted=Store::nativeInput($this->request->post);$tab=(string)($posted['tab']??'site');$store=new Store($this->pdo());$actor=(int)$this->user->getId();
        try{
            if($tab==='mail')$store->saveMail($posted,$actor);
            else{
                $site=array_replace(Factory::settings($this->pdo(),'site'),$posted);unset($site['tab'],$site['disclaimer']);$site=Store::validateSite($site);
                $this->pdo()->beginTransaction();try{Factory::saveSettings($this->pdo(),'site',$site,$actor);if($tab==='legal')Factory::saveSettings($this->pdo(),'legal',['disclaimer'=>CatalogSafety::text($posted['disclaimer']??'',10000,'Правовая оговорка')],$actor);$this->pdo()->commit();}catch(\Throwable $e){if($this->pdo()->inTransaction())$this->pdo()->rollBack();throw $e;}
            }
            $this->redirect($this->url('settings','',['tab'=>$tab,'saved'=>1]));
        }catch(\InvalidArgumentException $e){$this->form($tab,$e->getMessage(),$posted);}
    }
    public function upload():void
    {
        if(!$this->guard('settings',true))return;$this->response->addHeader('Content-Type: application/json; charset=utf-8');try{$path=(new Store($this->pdo()))->uploadImage($this->request->files['image']??[],(int)$this->user->getId());$this->response->setOutput(json_encode(['url'=>$path],JSON_THROW_ON_ERROR));}catch(\InvalidArgumentException $e){$this->response->addHeader('HTTP/1.1 422 Unprocessable Entity');$this->response->setOutput(json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
    }
}
