<?php
declare(strict_types=1);
// Explicit local-only worker. Never invokes mail(), SMTP or an external endpoint.
$options=getopt('',['runtime:','limit:']);
$runtime=realpath((string)($options['runtime']??dirname(__DIR__).'/local-dev/admin-runtime'));
if(!$runtime || !is_file($runtime.'/state.json'))throw new RuntimeException('Explicit isolated runtime required.');
$state=json_decode(file_get_contents($runtime.'/state.json'),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with((string)$state['origin'],'http://127.0.0.1:'))throw new RuntimeException('Only the isolated loopback runtime is allowed.');
require $runtime.'/core/admin/config.php';
\Catpart\Factory::assertLocal(['host'=>DB_HOSTNAME,'port'=>DB_PORT,'name'=>DB_DATABASE],$state['database']);
$box=new \Catpart\Outbox(\Catpart\Factory::pdo());
$result=$box->run($box->captureTransport($runtime.'/private/mail-captures',dirname(__DIR__).'/local-dev/site'),(int)($options['limit']??20));
echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
