<?php
declare(strict_types=1);
namespace Catpart;
use PDO;

final class Factory
{
    public static function assertLocal(array $db, ?string $expected = null): void
    {
        $name = (string) ($db['name'] ?? '');
        $legacy = $name === 'local_catpart' && $expected === null;
        $isolated = $expected !== null && preg_match('/^catpart_admin_[a-f0-9]{12}$/D', $expected)
            && hash_equals($expected, $name);
        if (($db['host'] ?? '') !== '127.0.0.1' || (int) ($db['port'] ?? 0) !== 3307 || (!$legacy && !$isolated)) {
            throw new \RuntimeException('Local preview DB is not an explicitly permitted loopback database.');
        }
    }

    public static function pdo(): PDO
    {
        static $pdo;
        if ($pdo instanceof PDO) return $pdo;
        $cfg = ['host' => DB_HOSTNAME, 'port' => DB_PORT, 'name' => DB_DATABASE];
        if (defined('CATPART_TEST_DATABASE')) self::assertLocal($cfg, CATPART_TEST_DATABASE);
        $pdo = new PDO('mysql:host=' . DB_HOSTNAME . ';port=' . (int) DB_PORT . ';dbname=' . DB_DATABASE . ';charset=utf8mb4', DB_USERNAME, DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec("SET time_zone='+00:00'");
        return $pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_settings (section VARCHAR(80) PRIMARY KEY,data LONGTEXT NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,actor_id INT UNSIGNED NOT NULL,entity VARCHAR(80) NOT NULL,entity_id VARCHAR(120) NOT NULL,action VARCHAR(80) NOT NULL,created_at DATETIME NOT NULL,details LONGTEXT NOT NULL,KEY entity_log(entity,entity_id,id)) ENGINE=InnoDB CHARACTER SET utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS cp_redirect (old_path VARCHAR(512) PRIMARY KEY,new_path VARCHAR(512) NOT NULL) ENGINE=InnoDB CHARACTER SET utf8mb4");
    }

    public static function settings(PDO $pdo, string $section): array
    {
        $q=$pdo->prepare('SELECT data FROM cp_settings WHERE section=?');$q->execute([$section]);$value=$q->fetchColumn();
        if ($value === false) return [];
        $data=json_decode((string)$value,true,512,JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    }

    public static function saveSettings(PDO $pdo, string $section, array $data, int $actor): void
    {
        $q=$pdo->prepare('INSERT INTO cp_settings(section,data,updated_at) VALUES(?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE data=VALUES(data),updated_at=VALUES(updated_at)');
        $q->execute([$section,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        self::audit($pdo,$actor,'settings',$section,'save');
    }

    public static function audit(PDO $pdo,int $actor,string $entity,string $id,string $action,array $details=[]):void
    {
        $q=$pdo->prepare('INSERT INTO cp_audit(actor_id,entity,entity_id,action,created_at,details) VALUES(?,?,?,?,UTC_TIMESTAMP(),?)');
        $q->execute([$actor,$entity,$id,$action,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }

    public static function systemPath(): string { return __DIR__; }
}
