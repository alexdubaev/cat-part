<?php
declare(strict_types=1);
// Safe LOCAL preview template. Copy to config.php AND config.local.php, then
// supply a local DB account. These filenames remain ignored by Git.
return [
    'app' => ['base_url' => 'http://127.0.0.1:8080', 'timezone' => 'Europe/Moscow', 'debug' => false],
    'db' => ['host' => '127.0.0.1', 'port' => 3307, 'name' => 'local_catpart', 'user' => '', 'pass' => '', 'charset' => 'utf8mb4'],
    'site' => ['name' => 'CatPart', 'domain' => 'cat-part.ru', 'tagline' => 'Запчасти для техники Caterpillar', 'city' => 'Санкт-Петербург'],
    'mail' => ['delivery' => 'capture', 'notify' => [], 'from_email' => 'noreply@cat-part.ru', 'from_name' => 'CatPart'],
    'analytics' => ['metrika_id' => ''],
    'integrations' => ['hq' => ['enabled' => false]],
];
