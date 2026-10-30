<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Простой роутинг: статические и параметрические пути (`/catalog/{slug}`).
 * Маршрут → [КлассКонтроллера, метод]. Параметры передаются методу по порядку.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,vars:list<string>,handler:array{0:class-string,1:string}}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /** @param array{0:class-string,1:string} $handler */
    private function add(string $method, string $path, array $handler): void
    {
        $vars = [];

        // Путь режем на плейсхолдеры {slug} и литеральные куски. Литералы экранируем:
        // у донора этого не было, и точка в маршруте '/sitemap.xml' оставалась
        // регэксповской «любой символ» — то есть '/sitemapAxml' тоже открывался бы,
        // отдавая тот же контент по другому адресу (дубли для поисковика).
        $regex = preg_replace_callback(
            '#\{(\w+)\}|[^{]+#',
            static function (array $m) use (&$vars): string {
                if (isset($m[1]) && $m[1] !== '') {
                    $vars[] = $m[1];
                    return '([^/]+)';
                }
                return preg_quote($m[0], '#');
            },
            $path
        );

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'vars'    => $vars,
            'handler' => $handler,
        ];
    }

    /**
     * Находит маршрут и вызывает обработчик. Возвращает false, если совпадений нет (404).
     *
     * 🔥 HEAD обслуживаем теми же обработчиками, что и GET. У донора роутер знал только
     * GET и POST, поэтому HEAD-запрос не совпадал ни с одним маршрутом и получал 404 —
     * а HEAD шлют мониторинги доступности и поисковые роботы (проверка живости URL перед
     * обходом). Сайт «лежал» для всех, кто спрашивает заголовки, а не тело.
     * Тело ответа отсекает фронт-контроллер: здесь ветвления по методу быть не должно.
     */
    public function dispatch(string $method, string $path): bool
    {
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $path = '/' . trim(rawurldecode($path), '/');
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) === 1) {
                array_shift($matches);
                [$class, $action] = $route['handler'];
                $controller = new $class();
                $controller->$action(...$matches);
                return true;
            }
        }
        return false;
    }
}
