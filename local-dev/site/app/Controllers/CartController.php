<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Product;

final class CartController extends Controller
{
    private const MAX_QTY = 99;

    public function index(): void
    {
        $this->view('cart/index', [
            'page' => [
                'title' => 'Корзина | ' . App::config('site.name'),
                'description' => 'Проверьте выбранные запчасти и отправьте запрос на подбор.',
                'canonical' => '/korzina',
                'breadcrumb' => [['name' => 'Корзина']],
            ],
            'lines' => $this->lines(),
            'ok' => Session::getFlash('cart_ok'),
            'error' => Session::getFlash('cart_error'),
        ]);
    }

    public function add(): never
    {
        $this->verifyCsrf();
        $id = (int) ($_POST['product_id'] ?? 0);
        if (Product::findActiveById($id) === null) {
            Session::flash('cart_error', 'Позиция недоступна. Обновите каталог и попробуйте снова.');
            $this->back('/korzina');
        }
        $cart = $this->cart();
        $cart[(string) $id] = min(self::MAX_QTY, (int) ($cart[(string) $id] ?? 0) + max(1, (int) ($_POST['qty'] ?? 1)));
        Session::set('cart', $cart);
        Session::flash('cart_ok', 'Позиция добавлена в корзину.');
        $this->redirect('/korzina');
    }

    public function update(): never
    {
        $this->verifyCsrf();
        $id = (int) ($_POST['product_id'] ?? 0);
        $qty = max(0, min(self::MAX_QTY, (int) ($_POST['qty'] ?? 0)));
        $cart = $this->cart();
        if ($qty === 0) unset($cart[(string) $id]); else $cart[(string) $id] = $qty;
        Session::set('cart', $cart);
        $this->redirect('/korzina');
    }

    public function remove(): never
    {
        $this->verifyCsrf();
        $cart = $this->cart();
        unset($cart[(string) ((int) ($_POST['product_id'] ?? 0))]);
        Session::set('cart', $cart);
        $this->redirect('/korzina');
    }

    public function checkout(): never
    {
        $this->verifyCsrf();
        $name = $this->clean((string) ($_POST['name'] ?? ''), 120);
        $phone = $this->clean((string) ($_POST['phone'] ?? ''), 32);
        $email = $this->clean((string) ($_POST['email'] ?? ''), 160);
        $digits = (string) preg_replace('/\D+/u', '', $phone);
        if ($name === '' || strlen($digits) < 10 || ($_POST['consent'] ?? '') === '') {
            Session::flash('cart_error', 'Укажите имя, телефон (не менее 10 цифр) и согласие на обработку данных.');
            $this->redirect('/korzina');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('cart_error', 'Проверьте адрес электронной почты.');
            $this->redirect('/korzina');
        }
        $lines = $this->lines();
        if ($lines === []) {
            Session::flash('cart_error', 'Корзина пуста. Добавьте хотя бы одну позицию.');
            $this->redirect('/korzina');
        }
        $body = "Заказ-запрос с сайта " . App::config('site.domain') . "\n\n"
            . "Имя: {$name}\nТелефон: {$phone}\n"
            . ($email !== '' ? "Email: {$email}\n" : '') . "\nПозиции:\n";
        foreach ($lines as $line) {
            $body .= '- ' . $line['sku'] . ' — ' . $line['name'] . ' × ' . $line['qty'] . "\n";
        }
        $body .= "\nЦены и наличие подтверждаются менеджером.\n";
        $hash = hash('sha256', $body);
        if (Session::get('last_order_hash') === $hash) {
            Session::flash('cart_ok', 'Этот запрос уже принят. Менеджер подтвердит состав и стоимость.');
            $this->redirect('/korzina');
        }
        $subject = '=?UTF-8?B?' . base64_encode('Запрос из корзины CatPart') . '?=';
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $from = (string) App::config('mail.from_email', 'noreply@cat-part.ru');
        if (filter_var($from, FILTER_VALIDATE_EMAIL)) $headers .= "From: <{$from}>\r\n";
        $to = (string) brand_setting('email', 'info@cmteh.ru');
        $sent = false;
        // bootstrap forcibly empties mail.notify for local previews; use that guard
        // instead of trusting a mutable environment label, so test orders never send.
        if ((array) App::config('mail.notify', []) === []) {
            $dir = dirname(__DIR__, 2) . '/storage/order-captures';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $sent = file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt', "To: {$to}\nSubject: {$subject}\n\n{$body}") !== false;
        } else {
            $sent = @mail($to, $subject, $body, $headers);
        }
        if (!$sent) {
            Session::flash('cart_error', 'Не удалось отправить запрос. Корзина сохранена — повторите позже или напишите на ' . $to . '.');
            $this->redirect('/korzina');
        }
        Session::set('last_order_hash', $hash);
        Session::set('cart', []);
        Session::flash('cart_ok', 'Запрос принят. Менеджер подтвердит наличие и стоимость.');
        $this->redirect('/korzina');
    }

    /** @return list<array<string,mixed>> */
    private function lines(): array
    {
        $out = [];
        foreach ($this->cart() as $id => $qty) {
            $product = Product::findActiveById((int) $id);
            if ($product === null) continue;
            $out[] = ['id' => (int) $product['id'], 'sku' => (string) $product['sku'], 'name' => (string) $product['name'], 'price' => $product['price'], 'qty' => min(self::MAX_QTY, max(1, (int) $qty))];
        }
        return $out;
    }

    /** @return array<string,int> */
    private function cart(): array
    {
        $cart = Session::get('cart', []);
        return is_array($cart) ? array_filter($cart, static fn ($qty, $id) => ctype_digit((string) $id) && (int) $qty > 0, ARRAY_FILTER_USE_BOTH) : [];
    }

    private function clean(string $value, int $max): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        return mb_substr(trim($value), 0, $max, 'UTF-8');
    }
}
