<?php
declare(strict_types=1);
namespace Catpart;
use PDO;

abstract class AdminController extends \Opencart\System\Engine\Controller
{
    protected function guard(string $area,bool $modify=false):bool
    {
        if (!$this->user->isLogged() || !$this->user->hasPermission($modify?'modify':'access','extension/catpart/module/'.$area)) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $this->response->setOutput('Недостаточно прав для этой операции.');
            return false;
        }
        if ($modify && ($this->request->server['REQUEST_METHOD']??'GET')!=='POST') {
            $this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
            $this->response->addHeader('Allow: POST');$this->response->setOutput('Требуется POST.');return false;
        }
        return true;
    }
    protected function pdo():PDO { return Factory::pdo(); }
    public static function esc(mixed $value):string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    protected function url(string $area,string $action='',array $query=[]):string
    {
        return $this->url->link('extension/catpart/module/'.$area.($action!==''?'.'.$action:''),http_build_query(['user_token'=>$this->session->data['user_token']]+$query),true);
    }
    protected function redirect(string $url):void { $this->response->redirect($url); }
    protected function page(string $title,string $safeBody):void
    {
        $this->document->setTitle($title.' | CatPart');
        $this->response->setOutput($this->load->controller('common/header').$this->load->controller('common/column_left')
            .'<main id="content"><div class="page-header"><div class="container-fluid"><h1>'.self::esc($title).'</h1></div></div><div class="container-fluid cp-admin">'.$safeBody.'</div></main>'
            .$this->load->controller('common/footer'));
    }
}
