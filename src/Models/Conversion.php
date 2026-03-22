<?php

declare(strict_types=1);

namespace SRP\Models;

use PDO;
use SRP\Config\Database;

class Conversion
{
    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): void
    {
        $clickId  = substr(self::readString($data, 'click_id'), 0, 100);
        $payout   = round(self::readFloat($data, 'payout'), 4);
        $currency = strtoupper(substr(self::readString($data, 'currency', 'USD'), 0, 10));
        $status   = substr(self::readString($data, 'status', 'approved'), 0, 50);
        $country  = strtoupper(substr(self::readString($data, 'country'), 0, 10)) ?: null;
        $ip       = substr(self::readString($data, 'ip'), 0, 45);
        $raw      = substr(self::readString($data, 'raw', '{}'), 0, 4096);

        $connection = Database::getConnection();

        if (self::hasCountryColumn($connection)) {
            $statement = $connection->prepare(
                'INSERT INTO conversions (ts, click_id, payout, currency, status, country, ip, raw)
                 VALUES (UNIX_TIMESTAMP(), :click_id, :payout, :currency, :status, :country, :ip, :raw)'
            );
            $statement->bindValue(':click_id', $clickId, PDO::PARAM_STR);
            $statement->bindValue(':payout', $payout);
            $statement->bindValue(':currency', $currency, PDO::PARAM_STR);
            $statement->bindValue(':status', $status, PDO::PARAM_STR);
            $statement->bindValue(':country', $country, $country !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $statement->bindValue(':ip', $ip, PDO::PARAM_STR);
            $statement->bindValue(':raw', $raw, PDO::PARAM_STR);
        } else {
            $statement = $connection->prepare(
                'INSERT INTO conversions (ts, click_id, payout, currency, status, ip, raw)
                 VALUES (UNIX_TIMESTAMP(), :click_id, :payout, :currency, :status, :ip, :raw)'
            );
            $statement->bindValue(':click_id', $clickId, PDO::PARAM_STR);
            $statement->bindValue(':payout', $payout);
            $statement->bindValue(':currency', $currency, PDO::PARAM_STR);
            $statement->bindValue(':status', $status, PDO::PARAM_STR);
            $statement->bindValue(':ip', $ip, PDO::PARAM_STR);
            $statement->bindValue(':raw', $raw, PDO::PARAM_STR);
        }

        $statement->execute();
    }

    /**
     * @return array{total:int,total_payout:float}
     */
    public static function getWeeklyStats(): array
    {
        $dow = (int) date('N');
        $startOfWeek = mktime(0, 0, 0, (int) date('m'), (int) date('d') - ($dow - 1), (int) date('Y'));
        if ($startOfWeek === false) {
            $startOfWeek = time();
        }

        $connection = Database::getConnection();
        $statement = $connection->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(payout), 0) AS total_payout
               FROM conversions
              WHERE ts >= :start_of_week
                AND status = \'approved\''
        );
        $statement->bindValue(':start_of_week', $startOfWeek, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();
        if (!is_array($row)) {
            $row = [];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'total_payout' => round((float) ($row['total_payout'] ?? 0), 2),
        ];
    }

    /**
     * @return list<array{day:mixed,total:int,total_payout:float}>
     */
    public static function getDailyStats(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $cutoff = time() - ($days * 86400);
        $connection = Database::getConnection();

        $statement = $connection->prepare(
            'SELECT DATE(FROM_UNIXTIME(ts)) AS day,
                    COUNT(*) AS total,
                    COALESCE(SUM(payout), 0) AS total_payout
               FROM conversions
              WHERE ts >= :cutoff
                AND status = \'approved\'
              GROUP BY DATE(FROM_UNIXTIME(ts))
              ORDER BY day DESC
              LIMIT :days'
        );
        $statement->bindValue(':cutoff', $cutoff, PDO::PARAM_INT);
        $statement->bindValue(':days', $days, PDO::PARAM_INT);
        $statement->execute();

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'day' => $row['day'],
                'total' => (int) $row['total'],
                'total_payout' => round((float) $row['total_payout'], 2),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function getRecent(int $limit = 30): array
    {
        return self::getPage($limit, 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function getPage(int $limit = 30, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $connection = Database::getConnection();
        $columns = self::hasCountryColumn($connection)
            ? 'id, ts, click_id, payout, currency, status, country, ip'
            : 'id, ts, click_id, payout, currency, status, ip';
        $statement = $connection->prepare(
            "SELECT {$columns}
               FROM conversions
              ORDER BY id DESC
              LIMIT :limit OFFSET :offset"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        /** @var list<array<string,mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    private static ?bool $countryColumnExists = null;

    private static function hasCountryColumn(PDO $connection): bool
    {
        if (self::$countryColumnExists !== null) {
            return self::$countryColumnExists;
        }

        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            ':table_name' => 'conversions',
            ':column_name' => 'country',
        ]);

        self::$countryColumnExists = (int) $statement->fetchColumn() > 0;

        return self::$countryColumnExists;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function readString(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function readFloat(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? $default;
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }

        return (float) $value;
    }
}
