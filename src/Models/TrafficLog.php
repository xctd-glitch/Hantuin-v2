<?php

declare(strict_types=1);

namespace SRP\Models;

use PDO;
use PDOStatement;
use SRP\Config\Cache;
use SRP\Config\Database;

class TrafficLog
{
    private static ?PDOStatement $insertStmt = null;
    private static ?PDO $insertConn = null;

    // ── Write-queue constants ──────────────────────────────
    private const QUEUE_FILE_PATH       = '/logs/traffic-queue.jsonl';
    private const QUEUE_LOCK_FILE_PATH  = '/logs/traffic-flush.lock';
    private const QUEUE_THRESHOLD_BYTES = 4096;   // ~20 rows × ~200 B each
    private const QUEUE_MAX_AGE_SECS    = 5;
    private const QUEUE_LAST_FLUSH_KEY  = 'srp_log_queue_flush_at';

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): void
    {
        // Skip logging during muted time slots (slots 2-4) to reduce DB writes.
        if (\SRP\Models\SrpClient::isInMutedSlot()) {
            return;
        }

        $ip       = substr(self::readString($data, 'ip'), 0, 45);
        $ua       = substr(self::readString($data, 'ua'), 0, 500);
        $cid      = substr(self::readString($data, 'cid'), 0, 100);
        $cc       = substr(self::readString($data, 'cc'), 0, 10);
        $lp       = substr(self::readString($data, 'lp'), 0, 100);
        $decision = in_array(self::readString($data, 'decision'), ['A', 'B'], true)
            ? self::readString($data, 'decision')
            : '';

        if ($decision === '') {
            throw new \InvalidArgumentException('Invalid decision value');
        }

        $row = [
            'ts'       => time(),
            'ip'       => $ip,
            'ua'       => $ua,
            'cid'      => $cid !== '' ? $cid : null,
            'cc'       => $cc !== '' ? $cc : null,
            'lp'       => $lp !== '' ? $lp : null,
            'decision' => $decision,
        ];

        $queueSize = self::enqueueRow($row);

        if ($queueSize < 0) {
            // Queue unavailable — fall back to direct single-row INSERT
            self::directInsert($row);
            return;
        }

        // Check size threshold
        $shouldFlush = $queueSize >= self::QUEUE_THRESHOLD_BYTES;

        // Check time-based flush using shared cache
        if (!$shouldFlush) {
            $fetched     = Cache::get(self::QUEUE_LAST_FLUSH_KEY);
            $lastFlush   = is_int($fetched) ? $fetched : 0;
            $shouldFlush = (time() - $lastFlush) >= self::QUEUE_MAX_AGE_SECS;
        }

        if ($shouldFlush) {
            self::tryFlushQueue();
        }
    }

    /**
     * @return array{total:int,a_count:int,b_count:int,since:string}
     */
    public static function getWeeklyStats(): array
    {
        $dow         = (int) date('N');
        $startOfWeek = mktime(0, 0, 0, (int) date('m'), (int) date('d') - ($dow - 1), (int) date('Y'));
        if ($startOfWeek === false) {
            $startOfWeek = time();
        }

        $connection = Database::getConnection();
        $statement  = $connection->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN decision = "A" THEN 1 ELSE 0 END) AS a_count,
                    SUM(CASE WHEN decision = "B" THEN 1 ELSE 0 END) AS b_count
               FROM logs
              WHERE ts >= :start_of_week',
        );
        $statement->bindValue(':start_of_week', $startOfWeek, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();
        if (!is_array($row)) {
            $row = [];
        }

        return [
            'total'   => (int) ($row['total'] ?? 0),
            'a_count' => (int) ($row['a_count'] ?? 0),
            'b_count' => (int) ($row['b_count'] ?? 0),
            'since'   => date('D d M', $startOfWeek),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function getAll(int $limit = 50): array
    {
        return self::getPage($limit, 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function getPage(int $limit = 50, int $offset = 0): array
    {
        $limit      = max(1, min(200, $limit));
        $offset     = max(0, $offset);
        $connection = Database::getConnection();
        $statement  = $connection->prepare(
            'SELECT id, ts, ip, ua, click_id, country_code, user_lp, decision
               FROM logs
              ORDER BY id DESC
              LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        /** @var list<array<string,mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    public static function clearAll(): int
    {
        $connection = Database::getConnection();
        $statement  = $connection->prepare('DELETE FROM logs');
        $statement->execute();
        $count = $statement->rowCount();

        self::$insertStmt = null;
        self::$insertConn = null;

        // Also clear the write queue so queued rows are not inserted after a clear
        $queuePath = dirname(__DIR__, 2) . self::QUEUE_FILE_PATH;
        if (is_file($queuePath)) {
            @unlink($queuePath);
        }

        return $count;
    }

    public static function autoCleanup(int $retentionDays = 7): int
    {
        $retentionDays = max(1, min(365, $retentionDays));
        $cutoff        = time() - ($retentionDays * 86400);

        $connection = Database::getConnection();
        $statement  = $connection->prepare('DELETE FROM logs WHERE ts < :cutoff');
        $statement->bindValue(':cutoff', $cutoff, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * @return list<array{day:mixed,total:int,a_count:int,b_count:int}>
     */
    public static function getDailyStats(int $days = 30): array
    {
        $days       = max(1, min(90, $days));
        $cutoff     = time() - ($days * 86400);
        $connection = Database::getConnection();

        $statement = $connection->prepare(
            'SELECT DATE(FROM_UNIXTIME(ts)) AS day,
                    COUNT(*) AS total,
                    SUM(CASE WHEN decision = "A" THEN 1 ELSE 0 END) AS a_count,
                    SUM(CASE WHEN decision = "B" THEN 1 ELSE 0 END) AS b_count
               FROM logs
              WHERE ts >= :cutoff
              GROUP BY DATE(FROM_UNIXTIME(ts))
              ORDER BY day DESC
              LIMIT :days',
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
                'day'     => $row['day'],
                'total'   => (int) $row['total'],
                'a_count' => (int) $row['a_count'],
                'b_count' => (int) $row['b_count'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{total:int,oldest_days:int,newest_days:int,size_estimate:int}
     */
    public static function getStats(): array
    {
        $connection = Database::getConnection();
        $statement  = $connection->prepare(
            'SELECT COUNT(*) AS total, MIN(ts) AS oldest, MAX(ts) AS newest FROM logs',
        );
        $statement->execute();
        $row = $statement->fetch();

        if (!is_array($row) || (int) $row['total'] === 0) {
            return ['total' => 0, 'oldest_days' => 0, 'newest_days' => 0, 'size_estimate' => 0];
        }

        return [
            'total'         => (int) $row['total'],
            'oldest_days'   => $row['oldest'] ? (int) ((time() - (int) $row['oldest']) / 86400) : 0,
            'newest_days'   => $row['newest'] ? (int) ((time() - (int) $row['newest']) / 86400) : 0,
            'size_estimate' => (int) $row['total'] * 500,
        ];
    }

    // ── Private helpers ────────────────────────────────────

    /**
     * Append one row to the queue file.
     * Returns file position after write (≥0) on success, or -1 on failure.
     *
     * @param array{ts:int,ip:string,ua:string,cid:string|null,cc:string|null,lp:string|null,decision:string} $row
     */
    private static function enqueueRow(array $row): int
    {
        $queuePath = dirname(__DIR__, 2) . self::QUEUE_FILE_PATH;
        $queueDir  = dirname($queuePath);

        if (!is_dir($queueDir) || !is_writable($queueDir)) {
            return -1;
        }

        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false) {
            return -1;
        }

        $fh = @fopen($queuePath, 'a');
        if ($fh === false) {
            return -1;
        }

        flock($fh, LOCK_EX);
        fwrite($fh, $line . "\n");
        $position = ftell($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        return $position !== false ? $position : 0;
    }

    /**
     * Atomically rename the queue file and batch-insert all buffered rows.
     * Uses a non-blocking lock so only one worker flushes at a time.
     */
    private static function tryFlushQueue(): void
    {
        $queuePath = dirname(__DIR__, 2) . self::QUEUE_FILE_PATH;
        $lockPath  = dirname(__DIR__, 2) . self::QUEUE_LOCK_FILE_PATH;

        $lockHandle = @fopen($lockPath, 'c');
        if ($lockHandle === false) {
            return;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            return; // Another worker is already flushing
        }

        try {
            $processingPath = $queuePath . '.processing';

            // Atomic rename: new appends from other workers go to a new file
            if (!@rename($queuePath, $processingPath)) {
                return;
            }

            Cache::set(self::QUEUE_LAST_FLUSH_KEY, time(), self::QUEUE_MAX_AGE_SECS + 60);

            $rawLines = @file($processingPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($rawLines === false || empty($rawLines)) {
                @unlink($processingPath);
                return;
            }

            $rows = [];
            foreach ($rawLines as $line) {
                if (!is_string($line)) {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $rows[] = $decoded;
                }
            }

            if (!empty($rows)) {
                self::batchInsert($rows);
            }

            // Only delete the file after successful insert
            @unlink($processingPath);
        } catch (\Throwable $e) {
            error_log('TrafficLog: queue flush failed — ' . $e->getMessage());
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Execute a single multi-row INSERT for all buffered rows.
     *
     * @param list<array<string,mixed>> $rows
     */
    private static function batchInsert(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $placeholders = [];
        $values       = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $dec = isset($row['decision']) && is_string($row['decision'])
                && in_array($row['decision'], ['A', 'B'], true)
                ? $row['decision']
                : null;

            if ($dec === null) {
                continue;
            }

            $ts  = isset($row['ts']) && is_int($row['ts']) ? $row['ts'] : time();
            $ip  = isset($row['ip']) && is_string($row['ip']) ? substr($row['ip'], 0, 45) : '';
            $ua  = isset($row['ua']) && is_string($row['ua']) ? substr($row['ua'], 0, 500) : '';
            $cid = isset($row['cid']) && is_string($row['cid']) && $row['cid'] !== ''
                ? substr($row['cid'], 0, 100)
                : null;
            $cc  = isset($row['cc']) && is_string($row['cc']) && $row['cc'] !== ''
                ? substr($row['cc'], 0, 10)
                : null;
            $lp  = isset($row['lp']) && is_string($row['lp']) && $row['lp'] !== ''
                ? substr($row['lp'], 0, 100)
                : null;

            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?)';
            $values[]       = $ts;
            $values[]       = $ip;
            $values[]       = $ua;
            $values[]       = $cid;
            $values[]       = $cc;
            $values[]       = $lp;
            $values[]       = $dec;
        }

        if (empty($placeholders)) {
            return;
        }

        $connection = Database::getConnection();
        $statement  = $connection->prepare(
            'INSERT INTO logs (ts, ip, ua, click_id, country_code, user_lp, decision) VALUES '
            . implode(', ', $placeholders),
        );
        $statement->execute($values);
    }

    /**
     * Single-row INSERT fallback (used when queue file is unavailable).
     *
     * @param array{ts:int,ip:string,ua:string,cid:string|null,cc:string|null,lp:string|null,decision:string} $row
     */
    private static function directInsert(array $row): void
    {
        $connection = Database::getConnection();
        if (self::$insertStmt === null || self::$insertConn !== $connection) {
            self::$insertStmt = $connection->prepare(
                'INSERT INTO logs (ts, ip, ua, click_id, country_code, user_lp, decision)
                 VALUES (:ts, :ip, :ua, :click_id, :country_code, :user_lp, :decision)',
            );
            self::$insertConn = $connection;
        }

        self::$insertStmt->execute([
            ':ts'           => $row['ts'],
            ':ip'           => $row['ip'],
            ':ua'           => $row['ua'],
            ':click_id'     => $row['cid'],
            ':country_code' => $row['cc'],
            ':user_lp'      => $row['lp'],
            ':decision'     => $row['decision'],
        ]);
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function readString(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        return (string) $value;
    }
}
