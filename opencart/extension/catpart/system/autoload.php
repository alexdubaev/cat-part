<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Catpart\\')) {
        $relative = substr($class, 8);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $relative) && is_file(__DIR__ . '/' . $relative . '.php')) {
            require_once __DIR__ . '/' . $relative . '.php';
        }
    }
});
