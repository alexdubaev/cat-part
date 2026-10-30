<?php
declare(strict_types=1);
namespace App\Core;

/** Shared transport; local bootstrap forces capture outside the document root. */
final class Mailer
{
    public static function send(string $subject, string $body, string $headers): bool
    {
        $to = implode(', ', (array) App::config('mail.notify', []));
        if ($to === '') return false;
        if (App::config('mail.delivery', 'mail') !== 'capture') {
            return @mail($to, $subject, $body, $headers);
        }
        $dir = (string) App::config('mail.capture_dir', '');
        if ($dir === '' || (!is_dir($dir) && !@mkdir($dir, 0700, true))) return false;
        $resolved = realpath($dir);
        $public = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? BASE_PATH));
        if ($resolved === false || $public === false) return false;
        $privatePath = strtolower(str_replace('\\', '/', $resolved)) . '/';
        $publicPath = strtolower(str_replace('\\', '/', $public)) . '/';
        if (str_starts_with($privatePath, $publicPath)) return false;
        $file = $resolved . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.txt';
        $ok = @file_put_contents($file, "To: {$to}\nSubject: {$subject}\n\n{$body}", LOCK_EX) !== false;
        if ($ok) @chmod($file, 0600);
        return $ok;
    }
}
