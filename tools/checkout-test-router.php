<?php
declare(strict_types=1);
// CLI-server-only isolated HTTP fixture. Never copy this router to production.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['SERVER_PORT'] ?? '') != 8081) exit;
$app = require dirname(__DIR__) . '/local-dev/site/app/bootstrap.php';
$config = $app->config;
$config['app']['base_url'] = 'http://127.0.0.1:8081';
if (isset($_SERVER['HTTP_X_TEST_FAIL_TRANSPORT'])) {
    $config['mail']['capture_dir'] = BASE_PATH; // Deliberately forbidden public path.
}
$_SERVER['REMOTE_ADDR'] = isset($_SERVER['HTTP_X_TEST_THROTTLE']) ? '192.0.2.251' : '192.0.2.250';
$app = \App\Core\App::boot($config);
\App\Core\Session::start();
$register = require BASE_PATH . '/app/routes.php';
$register($app->router);
$app->router->dispatch($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
