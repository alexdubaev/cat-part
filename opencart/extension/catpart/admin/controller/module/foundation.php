<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
require_once dirname(__DIR__,3).'/system/autoload.php';

final class Foundation extends \Catpart\AdminController
{
    public function index():void
    {
        if (!$this->guard('foundation')) return;
        $this->page('CatPart', '<div class="alert alert-info">Изолированный стенд. Данные каталога и витрины общие. Реальные письма не отправляются.</div>');
    }
    /** A read-only permission probe used by the isolated integration verifier. */
    public function probe():void
    {
        if (!defined('CATPART_TEST_DATABASE')) { $this->response->addHeader('HTTP/1.1 404 Not Found');return; }
        $area=(string)($this->request->get['area']??'');
        if (!in_array($area,['settings','content'],true) || !$this->guard($area,true)) return;
        $this->response->setOutput('Разрешено');
    }
}
