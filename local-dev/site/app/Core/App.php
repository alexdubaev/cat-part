<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ядро приложения: хранит конфиг, подключение к БД и роутер. Точка сборки
 * (composition root) — инициализируется из public/index.php.
 */
final class App
{
    private static ?App $instance = null;

    /** @param array<string,mixed> $config */
    private function __construct(
        public readonly array $config,
        public readonly Database $db,
        public readonly Router $router,
    ) {
    }

    /** @param array<string,mixed> $config */
    public static function boot(array $config): self
    {
        // Safe versioned public facts override legacy host config in every reader.
        $brand = require dirname(__DIR__) . '/Config/branding.php';
        $config['branding'] = $brand;
        $config['site'] = array_replace($config['site'] ?? [], [
            'email' => $brand['email'], 'company' => $brand['company'],
            'phone' => $brand['phones'][0]['label'], 'phones' => $brand['phones'],
            'address' => $brand['address'],
        ]);
        $config['mail']['notify'] = [$brand['email']];
        $db = new Database($config['db']);
        $app = new self($config, $db, new Router());
        self::$instance = $app;
        return $app;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('App не инициализировано');
        }
        return self::$instance;
    }

    public static function db(): Database
    {
        return self::instance()->db;
    }

    /** Точечный доступ к конфигу: config('site.name'), config('db.host'). */
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::instance()->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
