<?php

declare(strict_types=1);

/**
 * Общий bootstrap: автозагрузка, конфиг, БД. Используется фронт-контроллером
 * (public/index.php) и CLI-скриптами (db/import.php). Возвращает App.
 * Сессию/роутинг подключает вызывающий код.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Support/helpers.php';

$config = require BASE_PATH . '/app/Config/config.php';
$local = BASE_PATH . '/app/Config/config.local.php';
if (!is_file($local)) {
    throw new \RuntimeException('Local preview config is missing; refusing to use production defaults.');
}
$localConfig = (array) require $local;
$localDb = (array) ($localConfig['db'] ?? []);
$extensionAutoload = dirname(BASE_PATH, 2) . '/opencart/extension/catpart/system/autoload.php';
if (is_file($extensionAutoload)) {
    require_once $extensionAutoload;
    $expectedDatabase = getenv('CATPART_TEST_DATABASE') ?: null;
    \Catpart\Factory::assertLocal($localDb, is_string($expectedDatabase) ? $expectedDatabase : null);
} elseif (($localDb['host'] ?? null) !== '127.0.0.1'
    || (int) ($localDb['port'] ?? 0) !== 3307 || ($localDb['name'] ?? null) !== 'local_catpart') {
    throw new \RuntimeException('Local preview DB config must target 127.0.0.1:3307/local_catpart.');
}
$config = array_replace_recursive($config, $localConfig);

// Keep the real recipient visible for assertions, but force private local capture.
$config['mail']['delivery'] = 'capture';
$config['mail']['capture_dir'] = dirname(BASE_PATH) . '/private/mail-captures';

$app = \App\Core\App::boot($config);
date_default_timezone_set((string) \App\Core\App::config('app.timezone', 'UTC'));

if (\App\Core\App::config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} elseif (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
}

return $app;
