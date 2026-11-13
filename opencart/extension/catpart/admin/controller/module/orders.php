<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
require_once dirname(__DIR__,3).'/system/autoload.php';
use Catpart\{Requests,Outbox};
use Throwable;

/** Shared rendering for the two registries, always guarded by the concrete area's native ACL. */
abstract class RequestRegistry extends \Catpart\AdminController
{
    protected const AREA='orders';
    protected const KIND='order';
    public function index():void
    {
        if(!$this->guard(static::AREA))return;
        $id=(int)($this->request->get['id']??0);$error=(string)($this->session->data['cp_request_error']??'');unset($this->session->data['cp_request_error']);
        $ok=(string)($this->session->data['cp_request_ok']??'');unset($this->session->data['cp_request_ok']);
        $body=($error!==''?'<p class="alert alert-danger" role="alert">'.self::esc($error).'</p>':'').($ok!==''?'<p class="alert alert-success" role="status">'.self::esc($ok).'</p>':'');
        $service=new Requests($this->pdo());$title=static::KIND==='order'?'Заказы-запросы':'Обратная связь';
        if($id){$row=static::KIND==='order'?$service->order($id):$service->lead($id);if(!$row){$this->response->addHeader('HTTP/1.1 404 Not Found');$this->page('Запись не найдена',$body.'<p>Проверьте номер записи.</p>');return;}$body.=$this->detail($service,$row,$id);$title=$row['number'];}
        else{$body.=$this->registry($service);}
        $this->page($title,$body);
    }
    public function save():void
    {
        if(!$this->guard(static::AREA,true))return;
        $input=self::nativeInput($this->request->post);$id=(int)($input['id']??0);
        try{(new Requests($this->pdo()))->update(static::KIND,$id,$input,(int)$this->user->getId());$this->session->data['cp_request_ok']='Изменения сохранены. История обновлена.';unset($this->session->data['cp_request_old']);}
        catch(\InvalidArgumentException $e){$this->session->data['cp_request_error']=$e->getMessage();$this->session->data['cp_request_old']=$input;}
        catch(Throwable $e){$this->log->write('CatPart request update failed: '.get_class($e));$this->session->data['cp_request_error']='Не удалось сохранить изменения. Повторите позже.';$this->session->data['cp_request_old']=$input;}
        $this->redirect($this->url(static::AREA,'',['id'=>$id]));
    }
    public function retry():void
    {
        if(!$this->guard(static::AREA,true))return;$id=(int)($this->request->post['id']??0);
        try{(new Outbox($this->pdo()))->retry((int)($this->request->post['job']??0),(int)$this->user->getId(),static::KIND,$id);$this->session->data['cp_request_ok']='Уведомление поставлено в очередь повторно.';}
        catch(\InvalidArgumentException $e){$this->session->data['cp_request_error']=$e->getMessage();}
        $this->redirect($this->url(static::AREA,'',['id'=>$id]));
    }
    private function registry(Requests $service):string
    {
        $filters=self::nativeInput($this->request->get);$data=$service->listing(static::KIND,$filters);
        $body='<form method="get" action="index.php"><input type="hidden" name="route" value="extension/catpart/module/'.static::AREA.'"><input type="hidden" name="user_token" value="'.self::esc($this->session->data['user_token']).'"><label>Номер, имя, телефон или email <input name="q" value="'.self::esc($filters['q']??'').'"></label><label>Статус '.$this->statusSelect('status',(int)($filters['status']??0),true).'</label><label>Ответственный '.$this->managerSelect($service,'manager',(int)($filters['manager']??0)).'</label>'.(static::KIND==='lead'?'<label>Тип формы <select name="type"><option value="">Все типы</option>'.implode('',array_map(static fn($type)=>'<option value="'.self::esc($type).'"'.(($filters['type']??'')===$type?' selected':'').'>'.self::esc($type).'</option>',['Контакты','Обратный звонок','Подбор запчасти','Обращение с сайта'])).'</select></label>':'').'<button class="btn btn-primary">Найти</button> <a href="'.self::esc($this->url(static::AREA)).'">Сбросить фильтры</a></form>';
        $body.='<p>Найдено: '.$data['total'].'. Страница '.$data['page'].' из '.$data['pages'].'.</p><table><thead><tr><th>Номер и дата (UTC)</th><th>Контакты</th><th>Источник</th><th>Статус</th></tr></thead><tbody>';
        foreach($data['rows'] as $row){$customer=static::KIND==='order'?$row['customer']:$row;$id=(int)$row[static::KIND==='order'?'order_id':'id'];$body.='<tr><td><a href="'.self::esc($this->url(static::AREA,'',['id'=>$id])).'">'.self::esc($row['number']).'</a><br>'.self::esc($row['created_at']).'</td><td>'.self::esc($customer['name']).'<br>'.self::esc($customer['phone']).'<br>'.self::esc($customer['email']).'</td><td>'.self::esc($row['source']).'</td><td>'.self::esc(Requests::STATUSES[(int)$row['status_id']]??'').'</td></tr>';}
        if(!$data['rows'])$body.='<tr><td colspan="4">Записей по этим условиям пока нет.</td></tr>';
        $body.='</tbody></table><nav class="actions" aria-label="Страницы">';
        if($data['page']>1)$body.='<a href="'.self::esc($this->url(static::AREA,'',array_merge($filters,['page'=>$data['page']-1]))).'">Предыдущая</a>';
        if($data['page']<$data['pages'])$body.='<a href="'.self::esc($this->url(static::AREA,'',array_merge($filters,['page'=>$data['page']+1]))).'">Следующая</a>';
        return $body.'</nav>';
    }
    private function detail(Requests $service,array $row,int $id):string
    {
        $customer=static::KIND==='order'?$row['customer']:['name'=>$row['name'],'phone'=>$row['phone'],'email'=>$row['email'],'comment'=>$row['message']];
        $body='<p><a href="'.self::esc($this->url(static::AREA)).'">← К списку</a></p><dl><dt>Дата и время (UTC)</dt><dd>'.self::esc($row['created_at']).'</dd><dt>Источник</dt><dd>'.self::esc($row['source']).'</dd>'.(static::KIND==='lead'?'<dt>Тип формы</dt><dd>'.self::esc($row['type']).'</dd>':'').'<dt>Имя</dt><dd>'.self::esc($customer['name']).'</dd><dt>Телефон</dt><dd>'.self::esc($customer['phone']).'</dd><dt>Email</dt><dd>'.self::esc($customer['email']).'</dd><dt>Сообщение клиента</dt><dd>'.nl2br(self::esc($customer['comment'])).'</dd></dl>';
        if(static::KIND==='order'){
            $body.='<h2>Исходные условия обращения</h2><p>Снимок на момент отправки. Цена и наличие требуют подтверждения менеджером.</p><table><thead><tr><th>ID / Артикул</th><th>Название</th><th>Количество</th><th>Цена при отправке</th><th>Наличие и условия</th></tr></thead><tbody>';
            foreach($row['snapshot'] as $line)$body.='<tr><td>'.self::esc($line['product_id']).'<br>'.self::esc($line['sku']).'</td><td>'.self::esc($line['name']).'</td><td>'.self::esc($line['quantity']).'</td><td>'.self::esc($line['price']===null?'По запросу':$line['price'].' '.$line['currency']).'<br>Требует подтверждения</td><td>'.($line['in_stock']?'В наличии':'Под заказ').'<br>'.self::esc($line['delivery_terms']).'</td></tr>';
            $body.='</tbody></table>';
        }
        $job=(new Outbox($this->pdo()))->forEntity(static::KIND,$id);
        if($job){$body.='<h2>Уведомление</h2><p>'.self::esc(Outbox::STATES[$job['state']]??$job['state']).'; попыток: '.(int)$job['attempts'].'.</p>';if($job['error_code']!=='')$body.='<p class="error">'.self::esc(Outbox::errorText($job['error_code'])).'</p>';
            if($job['state']==='error' && $this->user->hasPermission('modify','extension/catpart/module/'.static::AREA))$body.='<form method="post" action="'.self::esc($this->url(static::AREA,'retry')).'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="job" value="'.(int)$job['id'].'"><button class="btn btn-secondary">Повторить уведомление</button></form>';
            $body.='<p>Принятие сервером или локальным перехватом не подтверждает попадание во «Входящие».</p>';
        }
        if($this->user->hasPermission('modify','extension/catpart/module/'.static::AREA)){
            $old=$this->session->data['cp_request_old']??[];unset($this->session->data['cp_request_old']);if((int)($old['id']??0)!==$id)$old=[];
            $body.='<h2>Обработка</h2><form method="post" action="'.self::esc($this->url(static::AREA,'save')).'"><input type="hidden" name="id" value="'.$id.'"><label>Статус '.$this->statusSelect('status_id',(int)($old['status_id']??$row['status_id'])).'</label><label>Ответственный '.$this->managerSelect($service,'manager_id',(int)($old['manager_id']??$row['manager_id']??0)).'</label><label>Внутренний комментарий<textarea name="note" maxlength="4000">'.self::esc($old['note']??'').'</textarea></label>';
            if(static::KIND==='order'){
                $body.='<fieldset><legend>Новая расценка (сохраняется отдельной записью истории)</legend>';
                foreach($row['snapshot'] as $line){$pid=(int)$line['product_id'];$body.='<label>'.self::esc($line['sku']).' — '.self::esc($line['name']).' <input name="quote_lines['.$pid.'][price]" inputmode="decimal" placeholder="Цена за единицу" value="'.self::esc($old['quote_lines'][$pid]['price']??'').'"><input name="quote_lines['.$pid.'][currency]" maxlength="3" aria-label="Валюта" value="'.self::esc($old['quote_lines'][$pid]['currency']??$line['currency']).'"></label>';}
                $body.='<label>Условия расценки<textarea name="quotation" maxlength="4000">'.self::esc($old['quotation']??'').'</textarea></label></fieldset>';
            }
            $body.='<button class="btn btn-primary">Сохранить обработку</button></form>';
        }
        $body.='<h2>История обработки</h2><table><thead><tr><th>Время (UTC) / Автор</th><th>Статус</th><th>Комментарий и расценка</th></tr></thead><tbody>';
        foreach($service->historyRows(static::KIND,$id) as $history){$quote=$history['quotation']?json_decode($history['quotation'],true):null;$detail=nl2br(self::esc($history['note']));if($quote){$detail.='<p>Расценка: '.nl2br(self::esc($quote['text']??'')).'</p>';foreach($quote['lines']??[] as $line)$detail.='<p>Товар №'.self::esc($line['product_id']).': '.self::esc($line['price']).' '.self::esc($line['currency']).'</p>';}$body.='<tr><td>'.self::esc($history['created_at']).'<br>'.self::esc($history['actor_name']?:((int)$history['actor_id']?'Сотрудник №'.$history['actor_id']:'Посетитель')).'</td><td>'.self::esc(Requests::STATUSES[$history['status_id']]??'').'<br>Ответственный: '.self::esc($history['manager_id']?:'не назначен').'</td><td>'.$detail.'</td></tr>';}
        return $body.'</tbody></table>';
    }
    private function statusSelect(string $name,int $value,bool $all=false):string
    {
        $html='<select name="'.self::esc($name).'">'.($all?'<option value="0">Все статусы</option>':'');foreach(Requests::STATUSES as $id=>$label)$html.='<option value="'.$id.'"'.($value===$id?' selected':'').'>'.self::esc($label).'</option>';return $html.'</select>';
    }
    private function managerSelect(Requests $service,string $name,int $value):string
    {
        $html='<select name="'.self::esc($name).'"><option value="0">Не назначен / все</option>';foreach($service->managers(static::KIND) as $user)$html.='<option value="'.(int)$user['user_id'].'"'.($value===(int)$user['user_id']?' selected':'').'>'.self::esc($user['firstname'].' '.$user['lastname']).'</option>';return $html.'</select>';
    }
    /** Native Request::clean encodes scalars once before our controller receives them. */
    private static function nativeInput(array $input):array
    {
        foreach($input as $key=>$value)$input[$key]=is_array($value)?self::nativeInput($value):(is_string($value)?html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8'):$value);
        return $input;
    }
}
final class Orders extends RequestRegistry {}
