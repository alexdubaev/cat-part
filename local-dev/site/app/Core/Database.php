<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Тонкая обёртка над PDO (MySQL). Только подготовленные запросы — никаких
 * конкатенаций пользовательского ввода в SQL. Один экземпляр на запрос (App).
 */
final class Database
{
    private PDO $pdo;

    /** @param array{host:string,port:int,name:string,user:string,pass:string,charset:string} $cfg */
    public function __construct(array $cfg)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );
        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Не удалось подключиться к БД: ' . $e->getMessage(), 0, $e);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Готовит запрос и привязывает параметры с верным типом (int → PARAM_INT и т.д.).
     * Нужно для нативных prepared (EMULATE_PREPARES=false), где MySQL не принимает
     * строковый LIMIT/OFFSET.
     * @param array<string,mixed> $params
     */
    private function run(string $sql, array $params): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @param array<string,mixed> $params @return array<string,mixed>|null */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $params @return list<array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string,mixed> $params Возвращает число затронутых строк. */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $params Возвращает lastInsertId. */
    public function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
