<?php

declare(strict_types=1);

namespace SRP\Models;

use PDO;
use SRP\Config\Cache;
use SRP\Config\Database;

class Settings
{
    private const CACHE_KEY = 'srp_cfg';
    private const CACHE_TTL = 60;

    /**
     * @var array{
     *     redirect_url:string,
     *     system_on:int,
     *     country_filter_mode:string,
     *     country_filter_list:string,
     *     postback_url:string,
     *     postback_token:string,
     *     updated_at:int
     * }|null
     */
    private static ?array $memo = null;

    /**
     * @return array{
     *     redirect_url:string,
     *     system_on:int,
     *     country_filter_mode:string,
     *     country_filter_list:string,
     *     postback_url:string,
     *     postback_token:string,
     *     updated_at:int
     * }
     */
    public static function get(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $val = Cache::get(self::CACHE_KEY);
        if (is_array($val)) {
            $data = self::normalizeRow($val);
            return self::$memo = $data;
        }

        $connection = Database::getConnection();
        $statement = $connection->prepare(
            'SELECT redirect_url,
                    system_on,
                    country_filter_mode,
                    country_filter_list,
                    postback_url,
                    postback_token,
                    updated_at
               FROM settings
              WHERE id = :id'
        );
        $statement->bindValue(':id', 1, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        $data = self::normalizeRow(is_array($row) ? $row : self::getDefaults());

        Cache::set(self::CACHE_KEY, $data, self::CACHE_TTL);

        return self::$memo = $data;
    }

    public static function update(
        bool $on,
        string $url,
        string $filterMode = 'all',
        string $filterList = '',
        string $postbackUrl = '',
        string $postbackToken = ''
    ): void {
        $safeUrl = self::validateUrl($url);
        $safePostback = self::validatePostbackUrl($postbackUrl);
        $safeToken = self::validatePostbackToken($postbackToken);

        if (!in_array($filterMode, ['all', 'whitelist', 'blacklist'], true)) {
            throw new \InvalidArgumentException('Invalid country filter mode');
        }

        $countries = [];
        if ($filterList !== '') {
            foreach (explode(',', $filterList) as $code) {
                $code = strtoupper(trim($code));
                if ($code !== '' && Validator::isValidCountryCode($code)) {
                    $countries[] = $code;
                }
            }
        }
        $cleanList = implode(',', array_unique($countries));

        $connection = Database::getConnection();
        $statement = $connection->prepare(
            'UPDATE settings
                SET system_on = :system_on,
                    redirect_url = :redirect_url,
                    country_filter_mode = :country_filter_mode,
                    country_filter_list = :country_filter_list,
                    postback_url = :postback_url,
                    postback_token = :postback_token,
                    updated_at = UNIX_TIMESTAMP()
              WHERE id = :id'
        );
        $statement->bindValue(':system_on', $on ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':redirect_url', $safeUrl, PDO::PARAM_STR);
        $statement->bindValue(':country_filter_mode', $filterMode, PDO::PARAM_STR);
        $statement->bindValue(':country_filter_list', $cleanList, PDO::PARAM_STR);
        $statement->bindValue(':postback_url', $safePostback, PDO::PARAM_STR);
        $statement->bindValue(':postback_token', $safeToken, PDO::PARAM_STR);
        $statement->bindValue(':id', 1, PDO::PARAM_INT);
        $statement->execute();

        self::$memo = null;
        Cache::delete(self::CACHE_KEY);
    }

    /**
     * @return array{mode:string,list:list<string>}
     */
    public static function getCountryFilter(): array
    {
        $cfg = self::get();
        $list = $cfg['country_filter_list'] !== '' ? explode(',', $cfg['country_filter_list']) : [];
        return [
            'mode' => $cfg['country_filter_mode'],
            'list' => array_map('strtoupper', $list),
        ];
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * @return array{
     *     redirect_url:string,
     *     system_on:int,
     *     country_filter_mode:string,
     *     country_filter_list:string,
     *     postback_url:string,
     *     postback_token:string,
     *     updated_at:int
     * }
     */
    private static function getDefaults(): array
    {
        return [
            'redirect_url' => '',
            'system_on' => 0,
            'country_filter_mode' => 'all',
            'country_filter_list' => '',
            'postback_url' => '',
            'postback_token' => '',
            'updated_at' => 0,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{
     *     redirect_url:string,
     *     system_on:int,
     *     country_filter_mode:string,
     *     country_filter_list:string,
     *     postback_url:string,
     *     postback_token:string,
     *     updated_at:int
     * }
     */
    private static function normalizeRow(array $row): array
    {
        return [
            'redirect_url' => self::readStringValue($row, 'redirect_url'),
            'system_on' => self::readIntValue($row, 'system_on'),
            'country_filter_mode' => self::readStringValue($row, 'country_filter_mode', 'all'),
            'country_filter_list' => self::readStringValue($row, 'country_filter_list'),
            'postback_url' => self::readStringValue($row, 'postback_url'),
            'postback_token' => self::readStringValue($row, 'postback_token'),
            'updated_at' => self::readIntValue($row, 'updated_at'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function readStringValue(array $row, string $key, string $default = ''): string
    {
        $value = $row[$key] ?? $default;
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function readIntValue(array $row, string $key, int $default = 0): int
    {
        $value = $row[$key] ?? $default;
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }

        return (int) $value;
    }

    private static function validatePostbackToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (!preg_match('/^[a-f0-9]{8,64}$/i', $token)) {
            throw new \InvalidArgumentException('Invalid postback token format');
        }
        return strtolower($token);
    }

    private static function validatePostbackUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid postback_url format');
        }

        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Postback URL must use HTTP or HTTPS');
        }

        if (strlen($url) > 2048) {
            throw new \InvalidArgumentException('Postback URL too long');
        }

        return $url;
    }

    private static function validateUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid redirect_url format');
        }

        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || $parsed['scheme'] !== 'https') {
            throw new \InvalidArgumentException('Redirect URL must use HTTPS');
        }

        if (!isset($parsed['host']) || !preg_match('/^[a-z0-9.-]+$/i', (string) $parsed['host'])) {
            throw new \InvalidArgumentException('Invalid redirect_url host');
        }

        if (strlen($url) > 2048) {
            throw new \InvalidArgumentException('Redirect URL too long');
        }

        return $url;
    }
}
