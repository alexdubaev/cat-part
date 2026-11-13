<?php
declare(strict_types=1);
namespace Opencart\Admin\Controller\Extension\Catpart\Module;
require_once __DIR__.'/orders.php';
final class Leads extends RequestRegistry
{
    protected const AREA='leads';
    protected const KIND='lead';
}
