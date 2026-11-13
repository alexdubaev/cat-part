<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Event;
require_once dirname(__DIR__,3).'/system/autoload.php';

final class Shell extends \Opencart\System\Engine\Controller
{
    public function route(string &$route,array &$args):void
    {
        $requested=(string)($this->request->get['route']??'common/dashboard');
        if (preg_match('~^(catalog|cms|sale|customer|marketing|report|design|marketplace|extension/(?!catpart/))[/]~',$requested)) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $this->response->setOutput('Этот инструмент не подключён к данным CatPart.');
            $this->response->output();exit;
        }
        if ($route==='common/dashboard') {
            $route=is_file(DIR_EXTENSION.'catpart/admin/controller/module/dashboard.php')
                ?'extension/catpart/module/dashboard':'extension/catpart/module/foundation';
        }
    }
    public function menu(string &$route,array &$data):void
    {
        $data['menus']=[];
        foreach (['dashboard'=>'Главная','catalog'=>'Каталог','orders'=>'Заказы','leads'=>'Обратная связь','content'=>'Содержимое','settings'=>'Настройки сайта','imports'=>'Импорт','reports'=>'Отчёты','maintenance'=>'Обслуживание'] as $area=>$label) {
            if (!$this->user->hasPermission('access','extension/catpart/module/'.$area)) continue;
            if (!is_file(DIR_EXTENSION.'catpart/admin/controller/module/'.$area.'.php')) continue;
            $data['menus'][]=['id'=>'cp-'.$area,'icon'=>'fas fa-circle','name'=>$label,'href'=>$this->url->link('extension/catpart/module/'.$area,'user_token='.$this->session->data['user_token']),'children'=>[]];
        }
        $children=[];
        foreach (['user/user'=>'Сотрудники','user/user_permission'=>'Роли и права'] as $area=>$label) {
            if ($this->user->hasPermission('access',$area)) $children[]=['name'=>$label,'href'=>$this->url->link($area,'user_token='.$this->session->data['user_token']),'children'=>[]];
        }
        if ($children) $data['menus'][]=['id'=>'cp-users','icon'=>'fas fa-users','name'=>'Пользователи и права','href'=>'','children'=>$children];
    }
    public function header(string &$route,array &$data,string &$output):void
    {
        $output=preg_replace('~<img[^>]+src="view/image/logo\.png"[^>]*>~','<strong style="font-size:22px;color:#222">Cat<span style="color:#c49100">Part</span></strong>',$output)??$output;
        $style='<style>:root{--bs-primary:#a97800}.btn-primary{background:#bd9000;border-color:#a47b00}#column-left{background:#292a2d}.cp-admin{max-width:1600px}.cp-admin label{display:block;font-weight:600;margin:12px 0 5px}.cp-admin input:not([type=checkbox]):not([type=radio]),.cp-admin textarea,.cp-admin select{max-width:100%;padding:8px;border:1px solid #bbb;border-radius:4px}.cp-admin textarea{width:100%;min-height:100px}.cp-admin table{width:100%;border-collapse:collapse}.cp-admin th,.cp-admin td{padding:8px;border-bottom:1px solid #ddd;vertical-align:top;overflow-wrap:anywhere}.cp-admin .actions{display:flex;gap:8px;margin:16px 0}.cp-admin .error{color:#ad1f19}.cp-admin .success{color:#286b36}.cp-admin fieldset{border:1px solid #ddd;padding:16px;margin:16px 0}</style>';
        $output=str_replace('</head>',$style.'</head>',$output);
    }
}
