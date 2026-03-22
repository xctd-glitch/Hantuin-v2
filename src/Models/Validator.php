<?php

declare(strict_types=1);

namespace SRP\Models;

class Validator
{
    public static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function sanitizeString(string $input, int $maxLength = 255): string
    {
        // Strip null bytes and non-printable ASCII control characters
        // Preserves tab (0x09), LF (0x0A), CR (0x0D); removes everything else below 0x20 and DEL (0x7F)
        $clean = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        return substr(trim($clean), 0, $maxLength);
    }

    /** @var array<string,int>|null */
    private static ?array $countrySet = null;

    public static function isValidCountryCode(string $code): bool
    {
        if (self::$countrySet === null) {
            self::$countrySet = array_flip([
                'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT',
                'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI',
                'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY',
                'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
                'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM',
                'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK',
                'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL',
                'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM',
                'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR',
                'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN',
                'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS',
                'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
                'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW',
                'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP',
                'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM',
                'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW',
                'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM',
                'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF',
                'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW',
                'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
                'VN', 'VU', 'WF', 'WS', 'XX', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
            ]);
        }

        return isset(self::$countrySet[strtoupper($code)]);
    }

    /**
     * Check whether a country code is allowed given filter settings.
     * Accepts the raw values from Settings::get() to avoid a second DB/cache round-trip.
     */
    public static function isCountryAllowed(string $countryCode, string $filterMode = 'all', string $filterList = ''): bool
    {
        $cc   = strtoupper($countryCode);
        $list = $filterList !== '' ? array_map('strtoupper', explode(',', $filterList)) : [];

        return match ($filterMode) {
            'whitelist' => !empty($list) && in_array($cc, $list, true),
            'blacklist'  => empty($list) || !in_array($cc, $list, true),
            default      => true,
        };
    }
}
