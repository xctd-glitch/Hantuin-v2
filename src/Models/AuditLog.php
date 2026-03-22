<?php

declare(strict_types=1);

namespace SRP\Models;

use PDO;
use SRP\Config\Database;

/**
 * Log audit untuk aksi admin — login, logout, perubahan settings.
 */
class AuditLog
{
    /**
     * @param array<string,mixed> $context
     */
    public static function record(string $action, string $actor, array $context = []): void
    {
        try {
            $connection = Database::getConnection();
            self::ensureTable($connection);

            $statement = $connection->prepare(
                'INSERT INTO audit_logs (ts, action, actor, ip, context)
                 VALUES (UNIX_TIMESTAMP(), :action, :actor, :ip, :context)',
            );
            $statement->bindValue(':action', substr($action, 0, 50), PDO::PARAM_STR);
            $statement->bindValue(':actor', substr($actor, 0, 100), PDO::PARAM_STR);
            $statement->bindValue(':ip', substr(self::getClientIp(), 0, 45), PDO::PARAM_STR);
            $statement->bindValue(
                ':context',
                substr(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', 0, 4096),
                PDO::PARAM_STR,
            );
            $statement->execute();
        } catch (\Throwable $e) {
            error_log('AuditLog: record() failed — ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function getRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $connection = Database::getConnection();
        self::ensureTable($connection);

        $statement = $connection->prepare(
            'SELECT id, ts, action, actor, ip, context
               FROM audit_logs
              ORDER BY id DESC
              LIMIT :limit',
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string,mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    private static bool $tableEnsured = false;

    private static function ensureTable(PDO $connection): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $connection->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS audit_logs (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ts      INT UNSIGNED    NOT NULL,
  action  VARCHAR(50)     NOT NULL,
  actor   VARCHAR(100)    NOT NULL DEFAULT '',
  ip      VARCHAR(45)     NOT NULL DEFAULT '',
  context TEXT            NOT NULL,
  INDEX idx_audit_ts (ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

        self::$tableEnsured = true;
    }

    private static function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
            $ip = trim((string) ($_SERVER[$header] ?? ''));
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '';
    }
}
