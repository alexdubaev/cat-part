# Обследование
Рабочая копия local-dev/site; PHP 8.3 / MariaDB 10.11, 127.0.0.1:3307 local_catpart. Запуск local-dev/start-local.ps1, HTTP 127.0.0.1:8080. Не обращаться к хостингу.
HomeController::index -> Category::roots и Post::latest(3) -> app/Views/home.php. 18 активных корневых категорий, все image_url NULL. 15 штатных разделов плюс adapter 8T-1687, plug 9S-8009 и Cat Reman. Все сохраняются, таксономию не менять.
4 опубликованные статьи: filtry-i-rabochie-zhidkosti-poddelka; hodovaya-chast-kogda-menyat; original-analog-ili-reman; kak-ustroen-nomer-detali-caterpillar. Все image_url NULL.
Классы cp-grid--cat, cp-cat, cp-about, cp-why, cp-posts/cp-post, cp-prod__img, cp-gal__stage в assets/css/site.css.
Views/blog/_card.php и show.php используют image_url; home.php дублирует карточку. Models/Post.php LIST_COLUMNS используется в разных местах. Catalog/category.php содержит карточки товаров.
Секреты config.php/config.local.php игнорируются Git; не читать и не сохранять их значения. upload 23,459 PNG, ~2.8GB, копия отдельная. До задачи главная/каталог/поиск HTTP200, POST /zayavka заблокирован локальным router.
