<?php
declare(strict_types=1);

// Isolated filesystem integration check. No DB or hosting access, credentials or real mail.
require __DIR__ . '/hosting-runtime.php';
$archive = $argv[1] ?? '';
if (!is_file($archive)) throw new RuntimeException('Pass the locked OpenCart archive path.');
$fixture = sys_get_temp_dir() . '/catpart-hosting-check-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
mkdir($fixture . '/site', 0700);
mkdir($fixture . '/runtime', 0700);
$originalDirectory = getcwd();
function hosting_check(bool $condition, string $description): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
}
try {
    $site = catpart_hosting_directory($fixture . '/site');
    $runtime = catpart_hosting_directory($fixture . '/runtime');
    try {
        catpart_hosting_paths($site, $site);
        throw new LogicException('Nested paths accepted.');
    } catch (RuntimeException $error) {
        hosting_check(!$error instanceof LogicException, 'overlapping public/private roots rejected');
    }
    try {
        catpart_hosting_put($runtime, '../escaped.php', '<?php');
        throw new LogicException('Traversal accepted.');
    } catch (RuntimeException $error) {
        hosting_check(!$error instanceof LogicException, 'staging traversal rejected');
    }
    $prepared = catpart_hosting_prepare_runtime($site, $runtime, dirname(__DIR__), $archive);
    hosting_check(is_file($prepared['core'] . '/admin/index.php'), 'locked core extracted privately');
    hosting_check(is_file($prepared['storage'] . '/vendor/autoload.php'), 'vendor staged privately');
    hosting_check(is_file($prepared['extension'] . '/catpart/system/autoload.php'), 'extension staged privately');
    hosting_check($prepared['static_files'] > 100, 'native and extension assets staged');
    hosting_check(!is_file($site . '/admin/index.php'), 'preparation leaves live site untouched');
    $publicFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($prepared['public'], FilesystemIterator::SKIP_DOTS));
    foreach ($publicFiles as $entry) if ($entry->isFile()) {
        hosting_check(!in_array(strtolower($entry->getExtension()), ['php', 'twig', 'scss', 'json', 'sql'], true), 'public assets exclude source/config/templates');
    }
    $mapping = catpart_hosting_write_bridge($site, $runtime, $prepared['extension']);
    hosting_check(count($mapping) === 2 && !is_file($site . '/app/bootstrap.php'), 'bridges are staged, not activated');
    catpart_hosting_put($site, 'app/Config/config.php', "<?php return ['app'=>['timezone'=>'UTC'],'mail'=>['delivery'=>'existing-delivery'],'fixture'=>'synthetic-config-marker'];\n");
    catpart_hosting_put($site, 'app/Config/config.local.php', "<?php return ['app'=>['timezone'=>'Europe/Moscow']];\n");
    catpart_hosting_put($site, 'app/Support/helpers.php', "<?php\n");
    catpart_hosting_put($site, 'app/Core/App.php', <<<'PHP'
<?php
namespace App\Core;
final class App {
    public static array $fixtureConfig;
    public static function boot(array $config): self { self::$fixtureConfig=$config; return new self(); }
    public static function config(string $key, mixed $default=null): mixed { return self::$fixtureConfig['app']['timezone']??$default; }
}
PHP);
    $bootstrapFile = $mapping[$site . '/app/bootstrap.php'];
    hosting_check(!str_contains((string)file_get_contents($bootstrapFile), 'synthetic-config-marker'), 'generated bridge contains no config values');
    require $bootstrapFile;
    hosting_check(\App\Core\App::$fixtureConfig['mail']['delivery'] === 'existing-delivery', 'production mail delivery preserved');
    hosting_check(date_default_timezone_get() === 'Europe/Moscow', 'existing optional local configuration merged');
    hosting_check(getenv('CATPART_SITE_ROOT') === $site, 'content and catalog resolve selected storefront root');
    catpart_hosting_put($runtime, 'core/admin/config.php', "<?php\n");
    catpart_hosting_put($runtime, 'core/admin/index.php', "<?php \$GLOBALS['hosting_adapter_observed']=[\$_SERVER['HTTPS'],getcwd(),\$_SERVER['SCRIPT_NAME']];\n");
    $_SERVER['HTTPS'] = 'on';
    require $mapping[$site . '/admin/index.php'];
    hosting_check($GLOBALS['hosting_adapter_observed'] === ['on', str_replace('/', DIRECTORY_SEPARATOR, $runtime . '/core/admin'), '/admin/index.php']
        || $GLOBALS['hosting_adapter_observed'] === ['on', $runtime . '/core/admin', '/admin/index.php'], 'adapter preserves HTTPS and invokes native admin from its working directory');
    echo "PASS hosting runtime: isolated extraction, static allowlist, path guards, staging-only bridges, config/mail preservation, native HTTPS adapter\n";
} finally {
    if (is_string($originalDirectory)) chdir($originalDirectory);
    // This exact random fixture is owned by this check; never follow symlinks during cleanup.
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($fixture);
}
