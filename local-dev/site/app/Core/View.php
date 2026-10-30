<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Рендер PHP-шаблонов с раскладкой (layout). Шаблон пишет в $content,
 * раскладка оборачивает его. SEO-мета (title/description/canonical/og/jsonld)
 * передаётся через ключ 'page' в данных.
 */
final class View
{
    private const VIEW_DIR = __DIR__ . '/../Views/';

    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'main'): string
    {
        $content = self::renderFile($view, $data);
        return self::renderFile('layouts/' . $layout, ['content' => $content] + $data);
    }

    /** Рендер без раскладки (для AJAX/фрагментов). @param array<string,mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        return self::renderFile($view, $data);
    }

    /** @param array<string,mixed> $data */
    private static function renderFile(string $view, array $data): string
    {
        $file = self::VIEW_DIR . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Шаблон не найден: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
