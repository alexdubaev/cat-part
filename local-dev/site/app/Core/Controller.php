<?php

declare(strict_types=1);

namespace App\Core;

/** Базовый контроллер: рендер вью, редиректы, JSON, 404 и доступ к БД. */
abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        echo View::render($view, $data, $layout);
    }

    protected function redirect(string $to): never
    {
        header('Location: ' . url($to));
        exit;
    }

    /**
     * Возврат на предыдущую страницу.
     *
     * 🔥 У донора это было заряженное ружьё: `header('Location: ' . $_SERVER['HTTP_REFERER'])`
     * без единой проверки — классический open redirect. Реферер приходит от клиента,
     * его можно подставить любой: злоумышленник шлёт жертве ссылку на форму нашего сайта,
     * а после POST её уносит на фишинговую копию — с нашего домена, «по доверию».
     *
     * Пускаем назад ТОЛЬКО на свой хост (сверяем с app.base_url, а не с HTTP_HOST:
     * заголовок Host тоже подделывается). Чужой или кривой реферер → fallback.
     *
     * $fragment — якорь без '#' ('zayavka'). Нужен формам: они стоят внизу длинных
     * страниц, и возврат без якоря высаживает человека в самое начало — свой же ответ
     * («заявка отправлена» / «проверьте телефон») он там не увидит и отправит ещё раз.
     * Параметр живёт здесь, а не в вызывающем контроллере, намеренно: собери он адрес
     * сам — вместе с адресом пришлось бы дублировать и проверку реферера выше, а это
     * ровно та защита, которую нельзя иметь в двух экземплярах. Браузеры фрагмент в
     * Referer не шлют, так что склейка безопасна.
     */
    protected function back(string $fallback = '/', string $fragment = ''): never
    {
        $target = url($fallback);
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $ownHost = parse_url((string) App::config('app.base_url'), PHP_URL_HOST);
            if (is_string($refererHost) && is_string($ownHost) && strcasecmp($refererHost, $ownHost) === 0) {
                $target = $referer;
            }
        }

        if ($fragment !== '' && !str_contains($target, '#')) {
            $target .= '#' . rawurlencode($fragment);
        }

        header('Location: ' . $target);
        exit;
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Текст 404 — ЕДИНСТВЕННОЕ место в проекте.
     *
     * 🔥 У донора он был захардкожен ДВАЖДЫ (Core/Controller.php:44 и public/index.php:41),
     * слово в слово: «Перейдите в каталог подшипников или на главную». Копия ядра без
     * правки — и посетитель cat-part.ru читал бы про подшипники. Дублирование строки и
     * есть причина, по которой такое переживает переезд: правишь одно место, второе молчит.
     */
    public const NOT_FOUND_TEXT = 'Возможно, адрес изменился или страница удалена. '
        . 'Начните с каталога или найдите деталь по артикулу через поиск.';

    /**
     * Рендер 404 вместе с выставлением кода ответа. Статический — чтобы фронт-контроллер
     * (промах роутера) отдавал ровно ту же страницу, что и контроллеры, а не свою копию.
     */
    public static function renderNotFound(): string
    {
        http_response_code(404);

        // Ключ именно 'message' — его читает app/Views/pages/error.php. Назовёшь 'text' —
        // вьюха молча подставит свой дефолтный текст, и константа выше станет мёртвой:
        // тот же дубль, от которого мы ушли, только по-новому.
        return View::render('pages.error', [
            'page'    => ['title' => 'Страница не найдена (404) — ' . App::config('site.name')],
            'code'    => 404,
            'message' => self::NOT_FOUND_TEXT,
        ]);
    }

    protected function notFound(): never
    {
        echo self::renderNotFound();
        exit;
    }

    protected function db(): Database
    {
        return App::db();
    }

    protected function requireAuth(): int
    {
        $id = Session::userId();
        if ($id === null) {
            Session::flash('intended', $_SERVER['REQUEST_URI'] ?? '/cabinet');
            $this->redirect('/login');
        }
        return $id;
    }

    /** Проверка CSRF для POST; при провале — 419-подобный отказ. */
    protected function verifyCsrf(): void
    {
        if (!Session::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'Сессия устарела, обновите страницу и повторите.';
            exit;
        }
    }
}
