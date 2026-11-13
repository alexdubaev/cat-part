/**
 * cat-part.ru — фронтенд-скрипты.
 *
 * Ванильный JS без зависимостей и сборки. Каталог на 22 000 SKU должен
 * открываться быстро с любого канала на объекте, поэтому библиотек тут нет
 * и не будет: всё, что ниже, — прогрессивное улучшение. Без JS сайт
 * полностью рабочий: меню в потоке, поиск — обычная GET-форма.
 */
(function () {
  'use strict';

  var catalogPanel = document.querySelector('.cp-catalog-panel');
  if (catalogPanel && window.matchMedia('(max-width: 800px)').matches) catalogPanel.open = false;

  /* ── Бургер: раскрытие строки меню на мобиле ───────────────────────────── */

  var burger = document.getElementById('cpBurger');
  var bar = document.getElementById('cpNavBar');

  if (burger && bar) {
    burger.addEventListener('click', function () {
      var open = bar.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // Escape закрывает меню и возвращает фокус на кнопку — иначе с клавиатуры
    // из раскрытой панели не выбраться.
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && bar.classList.contains('is-open')) {
        bar.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
        burger.focus();
      }
    });
  }

  /* ── Тень у липкой шапки при прокрутке ─────────────────────────────────── */

  var hd = document.querySelector('.cp-hd');
  if (hd) {
    var scrolled = false;
    var onScroll = function () {
      var now = window.pageYOffset > 8;
      // Трогаем классы только на смене состояния: scroll срабатывает
      // десятки раз в секунду, лишние записи в DOM дают дёрганье прокрутки.
      if (now !== scrolled) {
        scrolled = now;
        hd.classList.toggle('is-scrolled', now);
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Каталог: «Раскрыть» список подразделов в плитке раздела ───────────── */

  var catalogToggle = document.getElementById('cpCatalogToggle');
  var homeCatalog = document.getElementById('cpHomeCatalog');
  if (catalogToggle && homeCatalog) {
    var extraCategories = homeCatalog.querySelectorAll('[data-catalog-extra]');
    catalogToggle.hidden = false;
    catalogToggle.addEventListener('click', function () {
      var expanded = catalogToggle.getAttribute('aria-expanded') !== 'true';
      Array.prototype.forEach.call(extraCategories, function (card) { card.hidden = !expanded; });
      catalogToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      catalogToggle.querySelector('[data-catalog-label]').textContent = expanded ? 'Свернуть каталог' : 'Весь каталог';
      catalogToggle.querySelector('[data-catalog-arrow]').textContent = expanded ? '↑' : '↓';
    });
  }

  // Плитка показывает первые 10 подразделов, остальные скрыты CSS
  // (.cp-group__li--extra). Раскрытие идёт прямо в плитке, без перехода:
  // человек сравнивает разделы на одной странице, и уводить его со страницы,
  // чтобы он просто увидел названия узлов, — потеря.
  //
  // Кнопка приезжает из PHP с hidden и раскрывается здесь: без JS раскрывать
  // список некому, и мёртвая кнопка была бы хуже её отсутствия. Полный
  // перечень подразделов в этом случае доступен на странице раздела —
  // «Открыть раздел →» лежит в том же подвале плитки.
  var groupMore = document.querySelectorAll('[data-group-more]');

  Array.prototype.forEach.call(groupMore, function (btn) {
    var listId = btn.getAttribute('aria-controls');
    var list = listId ? document.getElementById(listId) : null;

    // Списка нет — кнопка так и остаётся скрытой: пусть лучше плитка будет
    // подрезанной, чем с кнопкой, которая ничего не делает.
    if (!list) return;

    btn.hidden = false;

    btn.addEventListener('click', function () {
      var open = list.classList.toggle('is-expanded');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.textContent = open ? 'Свернуть' : 'Раскрыть';
    });
  });

  /* ── Поиск: фокус по «/» ───────────────────────────────────────────────── */

  // Вход №1 в каталог запчастей — поиск по артикулу. Даём привычный
  // хоткей, но не перехватываем «/», когда человек уже печатает.
  var search = document.getElementById('cpSearchInput');
  if (search) {
    document.addEventListener('keydown', function (ev) {
      if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey) return;
      var t = ev.target;
      var tag = t && t.tagName ? t.tagName.toLowerCase() : '';
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || (t && t.isContentEditable)) return;
      ev.preventDefault();
      search.focus();
      search.select();
    });
  }

  /* ── Форма заявки: отправка без перезагрузки ───────────────────────────── */

  // Прогрессивное улучшение, а не условие работы. Без JS (и если что-то ниже
  // упало) форма остаётся обычным POST на /zayavka: тот же контроллер, тот же
  // ответ, только со сменой страницы. Поэтому перехватываем submit, а не
  // подменяем разметку, и никогда не трогаем action/method.
  var leadForms = document.querySelectorAll('form[data-lead]');

  Array.prototype.forEach.call(leadForms, function (form, formIndex) {
    var msg = form.querySelector('[data-lead-msg]');
    var btn = form.querySelector('button[type="submit"]');
    var busy = false;

    var show = function (text, ok) {
      if (!msg) return;
      msg.textContent = text;
      msg.classList.toggle('cp-leadform__msg--ok', ok);
      msg.classList.toggle('cp-leadform__msg--err', !ok);
      msg.hidden = false;
    };

    form.addEventListener('submit', function (ev) {
      // fetch есть — работаем сами. Нет (старый браузер) — не мешаем форме
      // уйти обычным POST: preventDefault не вызываем.
      if (!window.fetch || !window.FormData) return;

      ev.preventDefault();

      // Двойной клик по кнопке = две одинаковые заявки в почте у менеджера.
      if (busy) return;
      busy = true;
      if (btn) btn.disabled = true;
      Array.prototype.forEach.call(form.querySelectorAll('[data-field-error]'), function (error) { error.remove(); });
      Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid]'), function (input) {
        input.removeAttribute('aria-invalid');
        if ((input.getAttribute('aria-describedby') || '').indexOf('cp-lead-error-') === 0) input.removeAttribute('aria-describedby');
      });

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        // X-Requested-With читает LeadController::wantsJson() и отвечает JSON
        // вместо редиректа. same-origin — чтобы уехала сессионная кука с CSRF.
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (res) {
          // Не .json() напрямую: при протухшем CSRF сервер отдаёт 419 обычным
          // текстом, и разбор упал бы с невнятной ошибкой парсера. Читаем как
          // текст и пробуем разобрать сами.
          return res.text().then(function (body) {
            try {
              return JSON.parse(body);
            } catch (e) {
              return {
                ok: false,
                message: res.status === 419
                  ? 'Страница открыта слишком давно. Обновите её и отправьте заявку заново.'
                  : 'Не удалось отправить заявку. Обновите страницу и попробуйте ещё раз.'
              };
            }
          });
        })
        .then(function (data) {
          var ok = !!(data && data.ok);
          show(
            (data && data.message) || (ok ? 'Заявка отправлена.' : 'Не удалось отправить заявку.'),
            ok
          );
          // Чистим форму только на успехе: после ошибки человек должен увидеть
          // и поправить то, что ввёл, а не набирать всё заново.
          if (ok) {
            form.reset();
            if (form.elements.lead_token && /^[a-f0-9]{64}$/.test(data.lead_token || '')) form.elements.lead_token.value = data.lead_token;
          } else if (data && data.errors && typeof data.errors === 'object') {
            var firstInvalid = null;
            Object.keys(data.errors).forEach(function (key) {
              var input = form.elements.namedItem(key);
              if (!input || !input.closest) return;
              var error = document.createElement('span');
              error.className = 'cp-form-error';
              error.setAttribute('data-field-error', key);
              error.id = 'cp-lead-error-' + formIndex + '-' + key;
              error.textContent = String(data.errors[key]);
              (input.closest('label') || input.parentNode).appendChild(error);
              input.setAttribute('aria-invalid', 'true');
              input.setAttribute('aria-describedby', error.id);
              if (!firstInvalid) firstInvalid = input;
            });
            if (firstInvalid) firstInvalid.focus();
          }
        })
        .catch(function () {
          // Сеть отвалилась (объект, мобильный интернет) — говорим правду и
          // оставляем введённое на месте.
          show('Не удалось связаться с сервером. Проверьте соединение и попробуйте ещё раз.', false);
        })
        .then(function () {
          busy = false;
          if (btn) btn.disabled = false;
        });
    });
  });

  /* ── Карточка товара: галерея ──────────────────────────────────────────── */

  var galMain = document.querySelector('[data-gal-main]');
  var galThumbs = document.querySelector('[data-gal-thumbs]');
  if (galMain && galThumbs) {
    galThumbs.addEventListener('click', function (ev) {
      var btn = ev.target.closest ? ev.target.closest('.cp-gal__thumb') : null;
      if (!btn) return;

      var src = btn.getAttribute('data-gal-src');
      if (!src || src === galMain.getAttribute('src')) return;

      galMain.setAttribute('src', src);
      // fetchpriority=high имеет смысл только для первой картинки: дальше
      // это уже не LCP, а обычная подмена по клику.
      galMain.removeAttribute('fetchpriority');

      var all = galThumbs.querySelectorAll('.cp-gal__thumb');
      for (var i = 0; i < all.length; i++) all[i].classList.remove('is-active');
      btn.classList.add('is-active');
    });
  }

  /* ── Карточка товара: вкладки ──────────────────────────────────────────── */

  /**
   * Панели уже отрисованы сервером и без JS видны все подряд — это и есть
   * рабочее состояние страницы. Скрипт только надстраивает переключалку:
   * строит кнопки по data-tab и прячет неактивные панели. Если он не
   * загрузился, человек просто листает разделы подряд, ничего не теряя.
   */
  var tabsBox = document.querySelector('[data-tabs]');
  if (tabsBox) {
    var panels = tabsBox.querySelectorAll('.cp-tabs__panel');
    if (panels.length > 1) {
      var nav = document.createElement('div');
      nav.className = 'cp-tabs__nav';
      nav.setAttribute('role', 'tablist');

      var buttons = [];

      var activate = function (index) {
        for (var i = 0; i < panels.length; i++) {
          var on = i === index;
          panels[i].hidden = !on;
          buttons[i].setAttribute('aria-selected', on ? 'true' : 'false');
          // Из таб-порядка выпадают неактивные кнопки: по стрелкам ходят
          // внутри списка вкладок, Tab уводит дальше по странице.
          buttons[i].tabIndex = on ? 0 : -1;
        }
      };

      for (var i = 0; i < panels.length; i++) {
        (function (idx) {
          var panel = panels[idx];
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'cp-tabs__btn';
          btn.setAttribute('role', 'tab');
          btn.textContent = panel.getAttribute('data-tab') || ('Раздел ' + (idx + 1));
          btn.addEventListener('click', function () { activate(idx); });
          btn.addEventListener('keydown', function (ev) {
            if (ev.key !== 'ArrowRight' && ev.key !== 'ArrowLeft') return;
            var next = ev.key === 'ArrowRight'
              ? (idx + 1) % panels.length
              : (idx - 1 + panels.length) % panels.length;
            activate(next);
            buttons[next].focus();
          });
          buttons.push(btn);
          nav.appendChild(btn);
        })(i);
      }

      tabsBox.insertBefore(nav, tabsBox.firstChild);
      tabsBox.classList.add('is-ready');
      activate(0);
    }
  }

  /* ── Cookie-уведомление ────────────────────────────────────────────────── */

  var cookie = document.getElementById('cpCookie');
  if (cookie) {
    var stored = null;
    // localStorage бросает исключение в приватном режиме Safari и при
    // запрете сторонних данных — молча деградируем до «показать ещё раз».
    try { stored = localStorage.getItem('cp_cookie_ok'); } catch (e) {}

    if (stored !== '1') {
      // Keep the notice visible in normal flow, so it cannot cover controls.
      var main = document.getElementById('main');
      if (main) main.before(cookie);
      cookie.hidden = false;
      requestAnimationFrame(function () { cookie.classList.add('is-shown'); });

      var ok = document.getElementById('cpCookieOk');
      if (ok) {
        ok.addEventListener('click', function () {
          try { localStorage.setItem('cp_cookie_ok', '1'); } catch (e) {}
          cookie.classList.remove('is-shown');
          setTimeout(function () { cookie.hidden = true; }, 350);
        });
      }
    }
  }
})();
