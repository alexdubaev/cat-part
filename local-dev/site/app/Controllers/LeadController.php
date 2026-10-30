<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Session;

/**
 * Приём заявок с формы «Имя + телефон» (app/Views/partials/leadform.php).
 *
 * Что делает: проверяет CSRF, отсекает ботов, валидирует, шлёт письмо получателям
 * из config.mail.notify и отвечает — JSON для AJAX, редирект назад для формы без JS.
 * Никакой БД: заявка не хранится, она уходит почтой. Появится CRM — появится и запись.
 *
 * 🔥 Донор (Подшипники, app/Controllers/PageController.php:89-127) — образец того,
 * как этот контроллер писать НЕ надо, и оба греха переживают копирование сайта молча:
 *
 *   1. Список получателей был захардкожен прямо в коде и содержал три ЧУЖИХ адреса
 *      (какие именно — перечислено в config.php над mail.notify; здесь их намеренно
 *      нет даже в комментарии, чтобы не разводить копии этого списка по файлам).
 *      Скопируй метод как есть — и заявки cat-part.ru поехали бы посторонним компаниям.
 *      Адреса берутся ТОЛЬКО из config.mail.notify, ни одного литерала с почтой в этом
 *      файле нет.
 *   2. Каждая заявка дублировалась в «Штаб» Гастротехники курлом с чужим site_key.
 *      cat-part.ru — самостоятельный проект (config.integrations.hq.enabled = false),
 *      никуда ничего не дублируем; кода интеграции здесь нет вовсе.
 *
 * 🔴 Полей ровно два — имя и телефон (+ согласие и honeypot). E-mail мы не спрашиваем,
 * поэтому Reply-To у письма нет: заголовок с пустым или выдуманным адресом хуже, чем
 * его отсутствие — ответ уехал бы в никуда.
 */
final class LeadController extends Controller
{
    /** Минимум цифр в телефоне: 10 = номер без кода страны (9001234567). */
    private const PHONE_MIN_DIGITS = 10;

    /** Потолки на длину: в письмо не должен приезжать мегабайт из подделанного POST. */
    private const MAX_NAME = 120;
    private const MAX_PHONE = 32;

    /** Длина имени в теме письма. Строка темы кодируется base64 и не должна пухнуть. */
    private const SUBJECT_NAME = 40;

    public function submit(): void
    {
        $this->verifyCsrf();

        $ajax = $this->wantsJson();

        /*
         * Honeypot. Поле website живой человек не видит и заполнить не может (оно уведено
         * за край экрана, tabindex=-1, aria-hidden). Заполнено — перед нами бот.
         *
         * Отвечаем УСПЕХОМ и ничего не отправляем. Сказать боту «ошибка» — значит подарить
         * автору спам-скрипта обратную связь: он поправит скрипт и вернётся. Пусть считает,
         * что заявка ушла.
         */
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $this->succeed($ajax);
        }

        $name  = $this->clean((string) ($_POST['name'] ?? ''), self::MAX_NAME);
        $phone = $this->clean((string) ($_POST['phone'] ?? ''), self::MAX_PHONE);

        // Телефон валидируем по цифрам, а не по формату записи: «+7 (900) 000-00-00»,
        // «8 900 000 00 00» и «79000000000» — один и тот же номер. Маску на поле не вешаем,
        // человек диктует номер как привык.
        $digits = (string) preg_replace('/\D+/u', '', $phone);

        if ($name === '') {
            $this->fail($ajax, 'Укажите, как к вам обращаться.', $name, $phone);
        }
        if (strlen($digits) < self::PHONE_MIN_DIGITS) {
            $this->fail($ajax, 'Проверьте номер телефона: в нём должно быть не меньше '
                . self::PHONE_MIN_DIGITS . ' цифр.', $name, $phone);
        }

        /*
         * Согласие — required у чекбокса, то есть браузер и так не даст отправить форму.
         * Но required живёт на клиенте, а POST можно послать чем угодно: обработка
         * персональных данных без подтверждённого согласия — это к 152-ФЗ, а не к UX.
         * Проверяем на сервере.
         */
        if (($_POST['consent'] ?? '') === '') {
            $this->fail($ajax, 'Отметьте согласие на обработку персональных данных.', $name, $phone);
        }

        // source приходит из скрытого поля, то есть от клиента. В письмо он идёт как метка,
        // поэтому оставляем только «безопасный» алфавит: подделанное значение не должно
        // ни ломать разметку письма, ни тащить в него ссылку.
        $source = (string) preg_replace('/[^a-z0-9_\-]/i', '', (string) ($_POST['source'] ?? ''));
        $source = $source !== '' ? substr($source, 0, 32) : 'site';

        $to = $this->recipients();
        if ($to === []) {
            // Некому слать — заявка потеряна. Врать «спасибо, приняли» нельзя: человек будет
            // ждать звонка, которого никто не сделает.
            error_log('[catpart] lead: config.mail.notify пуст или содержит только невалидные адреса');
            $this->fail($ajax, $this->fallbackText(), $name, $phone, 500);
        }

        $subject = $this->encodeHeader('Заявка с ' . (string) App::config('site.domain') . ' — '
            . mb_substr($name, 0, self::SUBJECT_NAME, 'UTF-8'));

        $body = 'Заявка с сайта ' . (string) App::config('site.domain') . "\n\n"
            . 'Имя: ' . $name . "\n"
            . 'Телефон: ' . $phone . "\n"
            . 'Блок формы: ' . $source . "\n"
            . 'Страница: ' . $this->pageUrl() . "\n"
            . 'Время: ' . date('d.m.Y H:i') . "\n";

        $sent = @mail(implode(', ', $to), $subject, $body, $this->headers());

        if (!$sent) {
            /*
             * mail() вернула false — письмо НЕ ушло (не поднялся sendmail, отлуп MTA и т.п.).
             * Молча проглотить = потерять заявку без единого следа: в почте её нет, в логе
             * тоже, а человек уверен, что оставил. Пишем в лог и говорим правду.
             */
            error_log(sprintf(
                '[catpart] lead: mail() failed; to=%s source=%s',
                implode(',', $to),
                $source
            ));
            $this->fail($ajax, $this->fallbackText(), $name, $phone, 500);
        }

        $this->succeed($ajax);
    }

    /**
     * Получатели из config.mail.notify (это МАССИВ, а не строка — чтобы добавить адрес
     * без правки кода). Кривые значения отсеиваем: одна опечатка в конфиге не должна
     * ронять отправку остальным, а мусор в To — повод для MTA отбить письмо целиком.
     *
     * @return list<string>
     */
    private function recipients(): array
    {
        $out = [];
        foreach ((array) App::config('mail.notify', []) as $addr) {
            $addr = trim((string) $addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL) !== false) {
                $out[] = $addr;
            }
        }
        return array_values(array_unique($out));
    }

    /** Заголовки письма. Reply-To нет намеренно — e-mail у человека не спрашиваем. */
    private function headers(): string
    {
        $h = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

        $from = trim((string) App::config('mail.from_email'));
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            // Кривой from_email — не повод не отправить заявку: без From письмо уйдёт
            // от системного адреса хостинга. Криво, но лид дороже.
            error_log('[catpart] lead: config.mail.from_email невалиден, письмо уйдёт без From');
            return $h;
        }

        $name = trim((string) App::config('mail.from_name'));

        return $h . 'From: ' . ($name !== '' ? $this->encodeHeader($name) . ' ' : '')
            . '<' . $from . ">\r\n";
    }

    /**
     * Кодирование строки в заголовок письма (RFC 2047).
     *
     * base64 тут не только ради кириллицы: он заодно закрывает инъекцию заголовков.
     * Имя приезжает от пользователя, и «Иван\r\nBcc: spam@…» в сыром виде превратился бы
     * в лишний заголовок письма. После base64 перевод строки — просто байт внутри слова.
     */
    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Страница, с которой пришла заявка, — для письма.
     *
     * Реферер присылает клиент, поэтому берём его ТОЛЬКО со своего хоста (сверяем с
     * app.base_url — заголовок Host тоже подделывается). Чужой реферер в нашей почте —
     * это подсунутая нам же ссылка, и кто-то из своих по ней однажды кликнет.
     */
    private function pageUrl(): string
    {
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer === '') {
            return '—';
        }

        $host = parse_url($referer, PHP_URL_HOST);
        $own  = parse_url((string) App::config('app.base_url'), PHP_URL_HOST);
        if (!is_string($host) || !is_string($own) || strcasecmp($host, $own) !== 0) {
            return '—';
        }

        return substr($referer, 0, 300);
    }

    /** Текст на случай, когда письмо не ушло: даём человеку рабочий запасной канал. */
    private function fallbackText(): string
    {
        $email = trim((string) App::config('site.email'));

        return 'Не удалось отправить заявку — на нашей стороне сбой.'
            . ($email !== '' ? ' Напишите нам на ' . $email . ' — ответим.' : '');
    }

    /** Обрезка и очистка пользовательской строки: без управляющих символов, с потолком длины. */
    private function clean(string $value, int $max): string
    {
        // Управляющие символы (в т.ч. \r\n) выкидываем сразу: в теме письма их обезвредит
        // base64, но в теле они превратили бы одну строку заявки в несколько.
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

        return mb_substr(trim($value), 0, $max, 'UTF-8');
    }

    /**
     * Это AJAX? Смотрим и на X-Requested-With (его шлёт наш app.js), и на Accept:
     * fetch() в чужом коде мог бы просить JSON и без первого заголовка.
     */
    private function wantsJson(): bool
    {
        if (strcasecmp((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0) {
            return true;
        }

        return str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    /**
     * Успех. Один текст на оба пути ответа: разъедутся — пользователь получит разные
     * обещания в зависимости от того, включён ли у него JS.
     *
     * Сроков ответа тут нет и быть не должно: заказчик их не называл.
     */
    private function succeed(bool $ajax): never
    {
        $message = 'Заявка отправлена. Мы получили ваши контакты и свяжемся с вами.';

        if ($ajax) {
            $this->json(['ok' => true, 'message' => $message]);
        }

        Session::flash('lead_ok', $message);
        $this->back('/', 'zayavka');
    }

    /**
     * Отказ.
     *
     * $status по умолчанию 422 — «запрос понял, но данные не годятся»: это про имя,
     * телефон и согласие, то есть про пользователя. Сбой отправки письма приходит сюда
     * же, но с 500: данные там были в порядке, виноваты мы. Разница не косметическая —
     * по коду ответа мониторинг отличает «человек опечатался в телефоне» (норма, таких
     * будут сотни) от «почта на сервере легла» (инцидент, лиды теряются прямо сейчас).
     */
    private function fail(
        bool $ajax,
        string $message,
        string $name = '',
        string $phone = '',
        int $status = 422
    ): never {
        if ($ajax) {
            $this->json(['ok' => false, 'message' => $message], $status);
        }

        Session::flash('lead_error', $message);
        // Без JS страница перезагрузится целиком — возвращаем введённое, чтобы человек
        // не набирал телефон заново из-за опечатки в нём же.
        Session::flash('lead_old', ['name' => $name, 'phone' => $phone]);
        $this->back('/', 'zayavka');
    }
}
