<?php
declare(strict_types=1);
namespace App\Core;

/** Shared IP limit survives new sessions; trust REMOTE_ADDR, never forwarded headers. */
final class RequestThrottle
{
    public static function checkoutAllowed(): bool
    {
        $dir = dirname(BASE_PATH) . '/private/rate-limits';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) return false;
        $key = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $file = @fopen($dir . '/checkout-' . $key . '.json', 'c+');
        if ($file === false) return false;
        if (!flock($file, LOCK_EX)) { fclose($file); return false; }
        $now = time();
        $times = json_decode(stream_get_contents($file) ?: '[]', true);
        $times = array_values(array_filter(is_array($times) ? $times : [], static fn ($t) => is_int($t) && $t > $now - 600));
        $allowed = count($times) < 5;
        if ($allowed) $times[] = $now;
        rewind($file);
        ftruncate($file, 0);
        $saved = fwrite($file, json_encode($times)) !== false;
        fflush($file);
        flock($file, LOCK_UN);
        fclose($file);
        return $allowed && $saved;
    }
}
