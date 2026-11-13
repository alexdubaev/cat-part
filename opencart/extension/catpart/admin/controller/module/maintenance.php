<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
require_once dirname(__DIR__,3).'/system/autoload.php';
use Catpart\Outbox;
final class Maintenance extends \Catpart\AdminController
{
    public function index():void
    {
        if(!$this->guard('maintenance'))return;
        $body='<p>На изолированном стенде используется локальный перехват писем. Обращения сохраняются до запуска уведомлений.</p><h2>Уведомления</h2><table><thead><tr><th>Запись</th><th>Состояние</th><th>Попытки</th><th>Причина / действие</th></tr></thead><tbody>';
        foreach((new Outbox($this->pdo()))->listing() as $job){$body.='<tr><td>'.self::esc($job['kind']==='order'?'Заказ':'Обращение').' №'.(int)$job['entity_id'].'</td><td>'.self::esc(Outbox::STATES[$job['state']]??$job['state']).'</td><td>'.(int)$job['attempts'].'</td><td>'.($job['error_code']!==''?self::esc(Outbox::errorText($job['error_code'])):'');if($job['state']==='error' && $this->user->hasPermission('modify','extension/catpart/module/maintenance'))$body.='<form method="post" action="'.self::esc($this->url('maintenance','retry')).'"><input type="hidden" name="id" value="'.(int)$job['id'].'"><button class="btn btn-secondary">Повторить</button></form>';$body.='</td></tr>';}
        $body.='</tbody></table><p>Принятие сервером не подтверждает попадание письма во «Входящие».</p><h2>Журнал действий</h2><table><thead><tr><th>Время UTC</th><th>Автор</th><th>Запись</th><th>Действие</th></tr></thead><tbody>';
        foreach($this->pdo()->query('SELECT a.*,u.firstname AS actor_name FROM cp_audit a LEFT JOIN oc_user u ON u.user_id=a.actor_id ORDER BY a.id DESC LIMIT 100') as $row)$body.='<tr><td>'.self::esc($row['created_at']).'</td><td>'.self::esc($row['actor_name']?:'Посетитель / система').'</td><td>'.self::esc($row['entity'].' №'.$row['entity_id']).'</td><td>'.self::esc($row['action']).'</td></tr>';
        $this->page('Обслуживание',$body.'</tbody></table>');
    }
    public function retry():void
    {
        if(!$this->guard('maintenance',true))return;
        try{(new Outbox($this->pdo()))->retry((int)($this->request->post['id']??0),(int)$this->user->getId());}
        catch(\InvalidArgumentException $e){$this->response->addHeader('HTTP/1.1 422 Unprocessable Entity');$this->page('Ошибка',self::esc($e->getMessage()));return;}
        $this->redirect($this->url('maintenance'));
    }
}
