<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
require_once dirname(__DIR__,3).'/system/autoload.php';
use Catpart\Requests;
class Dashboard extends \Catpart\AdminController
{
    protected const AREA='dashboard';
    public function index():void
    {
        if(!$this->guard(static::AREA))return;$service=new Requests($this->pdo());$stats=$service->stats();
        $body='<p>Показатели по сохранённым данным CatPart. Запросы не являются оплаченными продажами.</p><table><tbody>';
        foreach(['orders'=>'Заказов-запросов','new_orders'=>'Новых заказов','leads'=>'Обращений','new_leads'=>'Новых обращений','notification_errors'=>'Ошибок уведомлений'] as $key=>$label)$body.='<tr><th>'.self::esc($label).'</th><td>'.$stats[$key].'</td></tr>';
        $body.='</tbody></table>';
        foreach(['order_statuses'=>'Заказы по статусам','lead_statuses'=>'Обращения по статусам'] as $key=>$label){$body.='<h2>'.self::esc($label).'</h2><table><thead><tr><th>Статус</th><th>Количество</th></tr></thead><tbody>';foreach($stats[$key] as $row)$body.='<tr><td>'.self::esc(Requests::STATUSES[$row['status_id']]??'').'</td><td>'.(int)$row['amount'].'</td></tr>';if(!$stats[$key])$body.='<tr><td colspan="2">Пока нет записей.</td></tr>';$body.='</tbody></table>';}
        $body.='<div class="actions">';foreach(['orders'=>'Открыть заказы','leads'=>'Открыть обращения','maintenance'=>'Уведомления и журнал'] as $area=>$label)if($this->user->hasPermission('access','extension/catpart/module/'.$area))$body.='<a class="btn btn-secondary" href="'.self::esc($this->url($area)).'">'.self::esc($label).'</a>';
        $this->page(static::AREA==='reports'?'Отчёты':'Главная',$body.'</div>');
    }
}
