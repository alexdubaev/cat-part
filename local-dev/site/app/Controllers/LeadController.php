<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{App,Controller,Session};
use Catpart\Requests;
use InvalidArgumentException;
use Throwable;

final class LeadController extends Controller
{
    public function submit(): void
    {
        $this->verifyCsrf();$ajax=strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''),'XMLHttpRequest')===0 || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
        $old=[];foreach(['name','phone','email','comment','consent'] as $key)$old[$key]=(string)($_POST[$key]??'');
        if(trim((string)($_POST['website']??''))!=='')$this->success($ajax,'Обращение принято.');
        if(!is_string(Session::get('request_owner')))Session::set('request_owner',bin2hex(random_bytes(32)));
        $source=preg_replace('/[^a-z0-9_\-]/i','',(string)($_POST['source']??'site'))?:'site';$source=substr($source,0,60);
        $type=match($source){'contacts','contact'=>'Контакты','home','callback'=>'Обратный звонок',default=>str_contains($source,'catalog')||str_contains($source,'product')?'Подбор запчасти':'Обращение с сайта'};
        try{$result=(new Requests(App::db()->pdo()))->submitLead(Session::get('request_owner'),(string)($_POST['lead_token']??''),$old,$type,$this->pageUrl().'#'.$source);}
        catch(InvalidArgumentException $e){$this->failure($ajax,$e->getMessage(),$old,422);}
        catch(Throwable $e){error_log('[catpart] lead persistence failure: '.get_class($e));$this->failure($ajax,'Не удалось сохранить обращение. Введённые данные сохранены — повторите отправку этой формы.',$old,500);}
        $this->success($ajax,'Обращение принято. Номер '.$result['number'].'. Мы свяжемся с вами.');
    }
    private function pageUrl():string
    {
        $ref=(string)($_SERVER['HTTP_REFERER']??'');$own=parse_url((string)App::config('app.base_url'));
        $parts=parse_url($ref);
        if(!$parts || strcasecmp((string)($parts['host']??''),(string)($own['host']??''))!==0 || (int)($parts['port']??80)!==(int)($own['port']??80))return '/';
        return substr((string)($parts['path']??'/'),0,300);
    }
    private function success(bool $ajax,string $message):never
    {
        if($ajax)$this->json(['ok'=>true,'message'=>$message,'lead_token'=>bin2hex(random_bytes(32))]);
        Session::flash('lead_ok',$message);$this->back('/','zayavka');
    }
    private function failure(bool $ajax,string $message,array $old,int $status):never
    {
        if($ajax)$this->json(['ok'=>false,'message'=>$message,'errors'=>Requests::fieldErrors($message)],$status);
        Session::flash('lead_error',$message);Session::flash('lead_old',$old);Session::flash('lead_field_errors',Requests::fieldErrors($message));$this->back('/','zayavka');
    }
}
