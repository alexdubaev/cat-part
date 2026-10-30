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

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && $relative === 'zayavka') {
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
