<?php
declare(strict_types=1);
// Called explicitly by the local runtime tool, never on public requests.
$runtime=getenv('CATPART_ADMIN_RUNTIME');
if(!is_string($runtime) || !is_file($runtime.'/state.json'))throw new RuntimeException('Explicit local runtime required.');
$state=json_decode(file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
require $runtime.'/core/admin/config.php';
\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],$state['database']);
$pdo=\Catpart\Factory::pdo();
$applied=[];
foreach(['Catalog','Content','Requests','Outbox'] as $name){
    $class='Catpart\\'.$name;
    if(class_exists($class) && is_callable([$class,'migrate'])){$class::migrate($pdo);$applied[]=$name;}
}
if(class_exists('Catpart\\Content') && is_callable(['Catpart\\Content','seed'])){
    \Catpart\Content::seed($pdo,dirname(__DIR__).'/local-dev/site');
}
echo json_encode(['migrations'=>$applied],JSON_THROW_ON_ERROR).PHP_EOL;
