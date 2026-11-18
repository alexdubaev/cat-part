<?php
declare(strict_types=1);

/** Production staging helpers. Requiring this file never installs or activates anything. */
const CATPART_HOSTING_CORE_SHA256 = 'cdef537f5a0a7e1b4ddbe16f589dd2e80b10150a09bfbe93ce76ef2d5c3218c0';

function catpart_hosting_directory(string $path): string
{
    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        throw new RuntimeException('An existing absolute directory is required.');
    }
    return rtrim(str_replace('\\', '/', $real), '/');
}

function catpart_hosting_inside(string $path, string $root): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        $path = strtolower($path);
        $root = strtolower($root);
    }
    return $path === $root || str_starts_with($path, $root . '/');
}

function catpart_hosting_paths(string $siteRoot, string $runtimeRoot): array
{
    $siteRoot = catpart_hosting_directory($siteRoot);
    $runtimeRoot = catpart_hosting_directory($runtimeRoot);
    if (catpart_hosting_inside($runtimeRoot, $siteRoot) || catpart_hosting_inside($siteRoot, $runtimeRoot)) {
        throw new RuntimeException('The private runtime and public site must be separate directories.');
    }
    return [$siteRoot, $runtimeRoot];
}

function catpart_hosting_put(string $root, string $relative, string $contents, int $mode = 0600): void
{
    if ($relative === '' || str_contains($relative, '\\') || str_contains($relative, "\0")
        || preg_match('~(?:^|/)\.{1,2}(?:/|$)|^/|:~', $relative)) {
        throw new RuntimeException('Unsafe staging path.');
    }
    $parts = explode('/', $relative);
    $path = $root;
    foreach ($parts as $part) {
        $path .= '/' . $part;
        if (is_link($path)) throw new RuntimeException('Symlinks are not permitted in generated staging paths.');
    }
    $parent = dirname($path);
    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Cannot create private staging directory.');
    }
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !chmod($temporary, $mode)
            || !rename($temporary, $path)) {
            throw new RuntimeException('Cannot write staged runtime file.');
        }
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
}

/** Copy trusted source files without following directory or file symlinks. */
function catpart_hosting_copy_tree(string $source, string $target, string $prefix, ?array $extensions = null): int
{
    $source = catpart_hosting_directory($source);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    $count = 0;
    foreach ($iterator as $entry) {
        if ($entry->isLink()) throw new RuntimeException('Symlink in runtime source tree.');
        if (!$entry->isFile()) continue;
        $relative = substr(str_replace('\\', '/', $entry->getPathname()), strlen($source) + 1);
        if ($extensions !== null && !in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), $extensions, true)) continue;
        $contents = file_get_contents($entry->getPathname());
        if (!is_string($contents)) throw new RuntimeException('Cannot read runtime source file.');
        catpart_hosting_put($target, $prefix . '/' . $relative, $contents);
        $count++;
    }
    return $count;
}

/** Stage locked native core, extension, vendor and public assets; never write the live site. */
function catpart_hosting_prepare_runtime(string $siteRoot, string $runtimeRoot, string $sourceRoot, string $coreArchive): array
{
    [$siteRoot, $runtimeRoot] = catpart_hosting_paths($siteRoot, $runtimeRoot);
    $sourceRoot = catpart_hosting_directory($sourceRoot);
    if (!is_file($coreArchive) || !hash_equals(CATPART_HOSTING_CORE_SHA256, (string)hash_file('sha256', $coreArchive))) {
        throw new RuntimeException('Pinned OpenCart archive checksum mismatch.');
    }
    $zip = new ZipArchive();
    if ($zip->open($coreArchive) !== true) throw new RuntimeException('Cannot open locked OpenCart archive.');
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || !str_starts_with($name, 'upload/') || str_ends_with($name, '/')) continue;
            $data = $zip->getFromIndex($i);
            if (!is_string($data)) throw new RuntimeException('Cannot read locked OpenCart archive member.');
            catpart_hosting_put($runtimeRoot, 'core/' . substr($name, 7), $data);
        }
    } finally {
        $zip->close();
    }
    $storage = $runtimeRoot . '/private/storage';
    foreach (['cache', 'download', 'logs', 'modification', 'session', 'upload', 'vendor'] as $name) {
        $path = $storage . '/' . $name;
        foreach ([$runtimeRoot . '/private', $storage, $path] as $component) {
            if (is_link($component)) throw new RuntimeException('Private storage must not contain symlinks.');
        }
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('Cannot create private storage.');
    }
    catpart_hosting_copy_tree($runtimeRoot . '/core/system/storage/vendor', $runtimeRoot, 'private/storage/vendor');
    catpart_hosting_copy_tree($sourceRoot . '/opencart/extension/catpart', $runtimeRoot, 'extension/catpart');
    $assets = ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
    $count = catpart_hosting_copy_tree($runtimeRoot . '/core/admin/view', $runtimeRoot, 'public/admin/view', $assets);
    $count += catpart_hosting_copy_tree($runtimeRoot . '/core/image', $runtimeRoot, 'public/image', $assets);
    $count += catpart_hosting_copy_tree($runtimeRoot . '/extension/catpart/admin/view', $runtimeRoot, 'public/extension/catpart/admin/view', $assets);
    return ['core' => $runtimeRoot . '/core', 'storage' => $storage, 'extension' => $runtimeRoot . '/extension',
        'public' => $runtimeRoot . '/public', 'static_files' => $count];
}

/** Generate bridges containing paths only. Existing site credentials are read only at request time. */
function catpart_hosting_write_bridge(string $siteRoot, string $runtimeRoot, string $extensionRoot): array
{
    [$siteRoot, $runtimeRoot] = catpart_hosting_paths($siteRoot, $runtimeRoot);
    $extensionRoot = catpart_hosting_directory($extensionRoot);
    if (!catpart_hosting_inside($extensionRoot, $runtimeRoot)
        || !is_file($extensionRoot . '/catpart/system/autoload.php')) {
        throw new RuntimeException('The CatPart extension must be inside the private runtime.');
    }
    $site = var_export($siteRoot, true);
    $runtime = var_export($runtimeRoot, true);
    $loader = var_export($extensionRoot . '/catpart/system/autoload.php', true);
    $bootstrap = <<<'PHP'
<?php
declare(strict_types=1);
// Generated production bridge. Local preview bootstrap and its guards remain separate.
if (defined('CATPART_TEST_DATABASE')) throw new RuntimeException('Test runtime cannot load the production bridge.');
if (!defined('BASE_PATH')) define('BASE_PATH', __SITE__);
putenv('CATPART_SITE_ROOT=' . BASE_PATH);
require_once __LOADER__;
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});
require_once BASE_PATH . '/app/Support/helpers.php';
$config = require BASE_PATH . '/app/Config/config.php';
$local = BASE_PATH . '/app/Config/config.local.php';
if (is_file($local)) $config = array_replace_recursive($config, (array)require $local);
$app = \App\Core\App::boot($config);
date_default_timezone_set((string)\App\Core\App::config('app.timezone', 'Europe/Moscow'));
if (PHP_SAPI !== 'cli') ini_set('display_errors', '0');
return $app;
PHP;
    $adapter = <<<'PHP'
<?php
declare(strict_types=1);
// Public adapter only; core, config, sessions and vendor remain outside the web root.
putenv('CATPART_SITE_ROOT=' . __SITE__);
putenv('CATPART_ADMIN_RUNTIME=' . __RUNTIME__);
header('X-Robots-Tag: noindex, nofollow');
ini_set('display_errors', '0');
$admin = __RUNTIME__ . '/core/admin';
if (!is_file($admin . '/config.php')) {
    http_response_code(503);
    exit('Администрирование временно недоступно.');
}
// Preserve the hosting server's HTTPS value: native OpenCart uses it for Secure cookies.
$_SERVER['SCRIPT_NAME'] = '/admin/index.php';
$_SERVER['PHP_SELF'] = '/admin/index.php';
$_SERVER['SCRIPT_FILENAME'] = $admin . '/index.php';
chdir($admin);
require $admin . '/index.php';
PHP;
    $replace = ['__SITE__' => $site, '__RUNTIME__' => $runtime, '__LOADER__' => $loader];
    catpart_hosting_put($runtimeRoot, 'bridge/app/bootstrap.php', strtr($bootstrap, $replace) . "\n");
    catpart_hosting_put($runtimeRoot, 'bridge/admin/index.php', strtr($adapter, $replace) . "\n");
    return [$siteRoot . '/app/bootstrap.php' => $runtimeRoot . '/bridge/app/bootstrap.php',
        $siteRoot . '/admin/index.php' => $runtimeRoot . '/bridge/admin/index.php'];
}
