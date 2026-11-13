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
        if (!class_exists('Catpart\\Factory')) {
            $loader = defined('BASE_PATH') && is_file(BASE_PATH.'/extension/catpart/system/autoload.php') ? BASE_PATH.'/extension/catpart/system/autoload.php' : dirname(__DIR__,4).'/opencart/extension/catpart/system/autoload.php';
            if(is_file($loader))require_once $loader;
        }
        $brand = require dirname(__DIR__) . '/Config/branding.php';
        $db = new Database($config['db']);
        $settings = [];
        $fields = [];
        try {
            foreach ($db->fetchAll('SELECT section,data FROM cp_settings') as $row) {
                $data = json_decode((string)$row['data'], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data)) $settings[(string)$row['section']] = $data;
            }
            foreach ($db->fetchAll('SELECT content_key,body FROM cp_content') as $row) $fields[(string)$row['content_key']] = (string)$row['body'];
        } catch (\PDOException $error) {
            // The unmodified legacy preview has no extension schema; retain its public branding.
            if ($error->getCode() !== '42S02') throw $error;
        }
        $site = array_replace($config['site'] ?? [], $brand, $settings['site'] ?? []);
        $primary = (int)($site['primary_phone'] ?? 0);
        $phone = $site['phones'][$primary] ?? ['label'=>'','tel'=>''];
        $site['phone'] = (string)($phone['label'] ?? '');
        $site['phone_raw'] = (string)($phone['tel'] ?? '');
        if(array_key_exists('office',$settings['site']??[])){
            $address=preg_replace('/,\s*офис\s+.+$/u','',(string)($site['address']??''));
            $site['address']=$address.((string)$site['office']!==''?', офис '.(string)$site['office']:'');
        }
        $config['site'] = $site;
        $config['branding'] = array_replace($brand, $site);
        $config['mail'] = array_replace($config['mail'] ?? [], $settings['mail'] ?? ['notify'=>[$brand['email']]]);
        foreach (['forms','menus','home','legal','page_seo'] as $section) $config[$section] = array_replace($config[$section] ?? [], $settings[$section] ?? []);
        $config['content_fields'] = $fields;
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
