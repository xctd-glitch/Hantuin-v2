<?php

declare(strict_types=1);

namespace SRP\Config;

use PDO;
use RuntimeException;
use Throwable;

class Database
{
    private static ?PDO $connection = null;
    private static bool $bootstrapped = false;
    private static int $lastUsedAt = 0;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            $now = time();
            if ($now - self::$lastUsedAt < 60 || self::isConnectionAlive(self::$connection)) {
                self::$lastUsedAt = $now;
                return self::$connection;
            }
            self::$connection = null;
        }

        $config = self::readConfig();

        try {
            $pdo = self::createPdo($config, true);
            if (!self::$bootstrapped) {
                self::initializeSchema($pdo);
                self::$bootstrapped = true;
            }
            self::$connection = $pdo;
            self::$lastUsedAt = time();
            return $pdo;
        } catch (Throwable $e) {
            throw new RuntimeException('DB init failed', 0, $e);
        }
    }

    private static function isConnectionAlive(PDO $connection): bool
    {
        try {
            $connection->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{host:string,user:string,pass:string,name:string,port:int,socket:string}
     */
    private static function readConfig(): array
    {
        return [
            'host' => (string) ($_ENV['SRP_DB_HOST'] ?? '127.0.0.1'),
            'user' => (string) ($_ENV['SRP_DB_USER'] ?? 'root'),
            'pass' => (string) ($_ENV['SRP_DB_PASS'] ?? ''),
            'name' => (string) ($_ENV['SRP_DB_NAME'] ?? 'srp'),
            'port' => max(1, min(65535, (int) ($_ENV['SRP_DB_PORT'] ?? 3306))),
            'socket' => trim((string) ($_ENV['SRP_DB_SOCKET'] ?? '')),
        ];
    }

    /**
     * @param array{host:string,user:string,pass:string,name:string,port:int,socket:string} $config
     */
    private static function createPdo(array $config, bool $withDatabase): PDO
    {
        if ($config['socket'] !== '') {
            $dsn = 'mysql:unix_socket=' . $config['socket'] . ';charset=utf8mb4';
        } else {
            $dsn = 'mysql:host=' . $config['host'] . ';port=' . (string) $config['port'] . ';charset=utf8mb4';
        }

        if ($withDatabase) {
            $dsn .= ';dbname=' . $config['name'];
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        }

        return new PDO($dsn, $config['user'], $config['pass'], $options);
    }

    private static function initializeSchema(PDO $connection): void
    {
        $connection->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  redirect_url VARCHAR(2048) NOT NULL DEFAULT '',
  system_on TINYINT(1) NOT NULL DEFAULT 0,
  country_filter_mode ENUM('all','whitelist','blacklist') NOT NULL DEFAULT 'all',
  country_filter_list TEXT NOT NULL,
  postback_url VARCHAR(2048) NOT NULL DEFAULT '',
  updated_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

        if (!self::columnExists($connection, 'settings', 'postback_url')) {
            $connection->exec(
                "ALTER TABLE settings
                 ADD COLUMN postback_url VARCHAR(2048) NOT NULL DEFAULT ''
                 AFTER country_filter_list"
            );
        }

        if (!self::columnExists($connection, 'settings', 'postback_token')) {
            $connection->exec(
                "ALTER TABLE settings
                 ADD COLUMN postback_token VARCHAR(64) NOT NULL DEFAULT ''
                 AFTER postback_url"
            );
        }

        $connection->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ts INT UNSIGNED NOT NULL,
  ip VARCHAR(45) NOT NULL,
  ua VARCHAR(500) NOT NULL,
  click_id VARCHAR(100) NULL,
  country_code VARCHAR(10) NULL,
  user_lp VARCHAR(100) NULL,
  decision ENUM('A','B') NOT NULL,
  INDEX idx_logs_ts_dec (ts, decision),
  INDEX idx_logs_cc_ts (country_code, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

        if (!self::indexExists($connection, 'logs', 'idx_logs_ts_dec')) {
            $connection->exec('ALTER TABLE logs ADD INDEX idx_logs_ts_dec (ts, decision)');
        }

        if (self::indexExists($connection, 'logs', 'idx_logs_ts')) {
            $connection->exec('ALTER TABLE logs DROP INDEX idx_logs_ts');
        }

        $connection->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS conversions (
  id        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ts        INT UNSIGNED     NOT NULL,
  click_id  VARCHAR(100)     NOT NULL DEFAULT '',
  payout    DECIMAL(10,4)    NOT NULL DEFAULT 0.0000,
  currency  VARCHAR(10)      NOT NULL DEFAULT 'USD',
  status    VARCHAR(50)      NOT NULL DEFAULT 'approved',
  country   VARCHAR(10)      NULL,
  ip        VARCHAR(45)      NOT NULL DEFAULT '',
  raw       TEXT             NOT NULL,
  INDEX idx_conv_ts       (ts),
  INDEX idx_conv_click_id (click_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

        if (!self::columnExists($connection, 'conversions', 'country')) {
            $connection->exec(
                "ALTER TABLE conversions
                 ADD COLUMN country VARCHAR(10) NULL
                 AFTER status"
            );
        }

        $statement = $connection->prepare(
            "INSERT INTO settings (
                id,
                redirect_url,
                system_on,
                country_filter_mode,
                country_filter_list,
                postback_url,
                postback_token,
                updated_at
            )
             VALUES (
                :id,
                :redirect_url,
                :system_on,
                :country_filter_mode,
                :country_filter_list,
                :postback_url,
                :postback_token,
                UNIX_TIMESTAMP()
            )
             ON DUPLICATE KEY UPDATE id = id"
        );
        $statement->execute([
            ':id' => 1,
            ':redirect_url' => '',
            ':system_on' => 0,
            ':country_filter_mode' => 'all',
            ':country_filter_list' => '',
            ':postback_url' => '',
            ':postback_token' => '',
        ]);
    }

    private static function columnExists(PDO $connection, string $tableName, string $columnName): bool
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function indexExists(PDO $connection, string $tableName, string $indexName): bool
    {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND INDEX_NAME = :index_name'
        );
        $statement->execute([
            ':table_name' => $tableName,
            ':index_name' => $indexName,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
