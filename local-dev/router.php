<?php

declare(strict_types=1);

// Router for PHP's built-in server. Apache .htaccess is not used locally.
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$siteRoot = __DIR__ . DIRECTORY_SEPARATOR . 'site';
// Normalize both URL separators before validating the path. On Windows, an
// encoded backslash is a real path separator for is_file(), and dot segments
// can alias a private directory, so validate the canonical segment list.
$decodedPath = str_replace('\\', '/', rawurldecode($uriPath));
$segments = [];
foreach (explode('/', $decodedPath) as $segment) {
    if ($segment === '' || $segment === '.') {
        continue;
    }
    if ($segment === '..') {
        http_response_code(404);
        return true;
    }
    // Windows strips trailing dots/spaces when resolving filesystem paths,
    // so `db.` and `db` must be treated as the same protected directory.
    if (preg_match('/[.\\s]$/u', $segment) === 1
        || preg_match('/[:*?"<>|]/', $segment) === 1) {
        http_response_code(404);
        return true;
    }
    $segments[] = $segment;
}
$relative = implode('/', $segments);

// Dotfiles are deployment metadata, not preview assets (for example
// .htaccess and a possible .env); never expose them through the dev server.
foreach ($segments as $segment) {
    if (str_starts_with($segment, '.')) {
        http_response_code(404);
        return true;
    }
}

// The admin runtime exists only when explicitly selected by the task process.
$nativeRoot = getenv('CATPART_OC_ROOT');
$taskDatabase = getenv('CATPART_TEST_DATABASE');
$isTask = is_string($taskDatabase) && preg_match('/^catpart_admin_[a-f0-9]{12}$/D', $taskDatabase) === 1;
if ($isTask && is_string($nativeRoot) && is_dir($nativeRoot)) {
    $serveAsset = static function (string $root, string $path): bool {
        $realRoot = realpath($root); $real = realpath($root . '/' . $path);
        if ($realRoot === false || $real === false || !is_file($real)
            || !str_starts_with(str_replace('\\', '/', $real), rtrim(str_replace('\\', '/', $realRoot), '/') . '/')) return false;
        $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $types = ['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif','ico'=>'image/x-icon','svg'=>'image/svg+xml','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf'];
        if (!isset($types[$extension])) return false;
        header('Content-Type: ' . $types[$extension]);readfile($real);return true;
    };
    if ($relative === 'admin' || str_starts_with($relative, 'admin/')) {
        $adminRelative = substr($relative, strlen('admin/'));
        if ($relative === 'admin' || $adminRelative === '' || $adminRelative === 'index.php') {
            $route = str_replace(['|','%7C'], '.', (string)($_GET['route'] ?? 'common/dashboard'));
            if (preg_match('~^(catalog|cms|sale|customer|marketing|report|design|marketplace|extension/(?!catpart/))[/]~', $route)) {
                http_response_code(403);echo 'Этот инструмент не подключён к данным CatPart.';return true;
            }
            $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
            $_SERVER['PHP_SELF'] = '/admin/index.php';
            $_SERVER['SCRIPT_FILENAME'] = $nativeRoot . '/admin/index.php';
            $_SERVER['HTTPS'] = false;
            chdir($nativeRoot . '/admin');
            require $nativeRoot . '/admin/index.php';return true;
        }
        if (str_starts_with($adminRelative, 'view/') && $serveAsset($nativeRoot.'/admin', $adminRelative)) return true;
        http_response_code(404);return true;
    }
    if (str_starts_with($relative,'image/') && $serveAsset($nativeRoot.'/image',substr($relative,6))) return true;
    if (str_starts_with($relative,'extension/catpart/admin/view/') && $serveAsset(dirname(__DIR__).'/opencart/extension/catpart/admin/view',substr($relative,strlen('extension/catpart/admin/view/')))) return true;
    if (str_starts_with($relative,'upload/') && !is_file($siteRoot.'/'.$relative)) {
        $sourceMedia = getenv('CATPART_SOURCE_MEDIA');
        if (is_string($sourceMedia) && $serveAsset($sourceMedia,substr($relative,7))) return true;
    }
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && $relative === 'zayavka' && !$isTask) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Local preview does not send real заявки.\n";
    return true;
}

// Keep source and data directories private during local preview.
foreach (['app', 'db', 'storage', 'import.php'] as $blocked) {
    $relativeLower = strtolower($relative);
    $blockedLower = strtolower($blocked);
    if ($relativeLower === $blockedLower || str_starts_with($relativeLower, $blockedLower . '/')) {
        http_response_code(404);
        return true;
    }
}

$candidate = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
if ($relative !== '' && is_file($candidate)) {
    if (str_ends_with(strtolower($relative), '.php') && $relative !== 'index.php') {
        http_response_code(404);
        return true;
    }
    return false;
}

require $siteRoot . DIRECTORY_SEPARATOR . 'index.php';
return true;
