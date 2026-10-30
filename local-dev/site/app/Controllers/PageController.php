<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;

/**
 * Статические страницы.
 *
 * ⚠️ Здесь НЕТ ни одного факта о заказчике: ни телефона, ни адреса, ни юрлица, ни сроков
 * доставки, ни условий гарантии. Их нам не называли (см. plans/, «Открытые вопросы»).
 * Всё, что известно, лежит в config.site.* и подставляется оттуда; чего нет — того нет,
 * а не «от 3 дней» и не «Москва». Выдуманный срок на сайте магазина — это обещание,
 * которое кто-то предъявит.
 *
 * У донора этот контроллер был ещё и приёмником заявок с хардкодом трёх ЧУЖИХ email,
 * URL Штаба и site_key прямо в коде. Приёма заявок в Ф1 нет; когда появится — адреса
 * берутся из config.mail.notify, дубль в Штаб выключен (integrations.hq.enabled = false).
 */
final class PageController extends Controller
{
    public function about(): void
    {
        $this->render('about', [
            'title'       => 'О компании — ' . App::config('site.name'),
            'description' => App::config('site.name') . ' — независимый поставщик запчастей для техники '
                . 'Caterpillar. Подбор по номеру детали и кросс-номерам, поставка по России.',
            'canonical'   => '/o-kompanii',
            'crumb'       => 'О компании',
        ]);
    }

    public function delivery(): void
    {
        $this->render('delivery', [
            'title'       => 'Доставка запчастей по России | ' . App::config('site.name'),
            'description' => 'Условия доставки запчастей для техники Caterpillar: отправка транспортными '
                . 'компаниями по России.',
            'canonical'   => '/dostavka',
            'crumb'       => 'Доставка',
        ]);
    }

    public function payment(): void
    {
        $this->render('payment', [
            'title'       => 'Оплата — ' . App::config('site.name'),
            'description' => 'Условия оплаты запчастей для техники Caterpillar в ' . App::config('site.name') . '.',
            'canonical'   => '/oplata',
            'crumb'       => 'Оплата',
        ]);
    }

    public function warranty(): void
    {
        $this->render('warranty', [
            'title'       => 'Гарантия на запчасти | ' . App::config('site.name'),
            'description' => 'Гарантийные условия на запчасти для техники Caterpillar, приобретённые в '
                . App::config('site.name') . '.',
            'canonical'   => '/garantiya',
            'crumb'       => 'Гарантия',
        ]);
    }

    public function contacts(): void
    {
        // Email — единственный подтверждённый контакт (config.site.email). Телефон и адрес
        // в конфиге пустые: в description их не вклеиваем, иначе получится «телефон: .».
        $this->render('contacts', [
            'title'       => 'Контакты — ' . App::config('site.name'),
            'description' => 'Контакты ' . App::config('site.name') . ': ' . App::config('site.email')
                . '. Подбор запчастей для техники Caterpillar по номеру детали.',
            'canonical'   => '/kontakty',
            'crumb'       => 'Контакты',
        ]);
    }

    public function privacy(): void
    {
        $this->render('privacy', [
            'title'       => 'Политика конфиденциальности — ' . App::config('site.name'),
            'description' => 'Политика обработки персональных данных и использования файлов cookie на сайте '
                . App::config('site.domain') . '.',
            'canonical'   => '/politika-konfidencialnosti',
            'crumb'       => 'Политика конфиденциальности',
        ]);
    }

    /**
     * Общая сборка $page для статики: шесть страниц отличаются только текстами,
     * и городить в каждой одинаковый массив — плодить места, где разъедется контракт.
     *
     * @param array{title:string,description:string,canonical:string,crumb:string} $meta
     */
    private function render(string $view, array $meta): void
    {
        $this->view('pages/' . $view, [
            'page' => [
                'title'       => $meta['title'],
                'description' => $meta['description'],
                'canonical'   => $meta['canonical'],
                // Единственная крошка — без url: это текущая страница.
                'breadcrumb'  => [['name' => $meta['crumb']]],
            ],
        ]);
    }
}
