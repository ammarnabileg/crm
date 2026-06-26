<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Seeds global reference data (D0): currencies, languages, timezones, countries.
 * Not tenant-scoped — referenced by FK across the platform (multi-currency money,
 * multi-language locales, multi-timezone, addresses). Fully idempotent (keyed by
 * the natural code/name) so the installer can re-run it safely. The lists are a
 * production-useful baseline; more rows can be toggled on via `is_active` without
 * code changes.
 *
 * Returned as an anonymous object (loaded via require, like migrations/seeders) so
 * it works during installation before any autoloaded seeder namespace exists.
 */
return new class {
    public function run(Database $db): void
    {
        $this->seedCurrencies($db);
        $this->seedLanguages($db);
        $this->seedCountries($db);   // resolves default_currency_id from currencies
        $this->seedTimezones($db);   // resolves country_id from countries
    }

    private function seedCurrencies(Database $db): void
    {
        // [code, numeric, name, symbol, decimals]
        $rows = [
            ['SAR', '682', 'Saudi Riyal', '﷼', 2],
            ['USD', '840', 'US Dollar', '$', 2],
            ['EUR', '978', 'Euro', '€', 2],
            ['GBP', '826', 'Pound Sterling', '£', 2],
            ['AED', '784', 'UAE Dirham', 'د.إ', 2],
            ['KWD', '414', 'Kuwaiti Dinar', 'د.ك', 3],
            ['BHD', '048', 'Bahraini Dinar', '.د.ب', 3],
            ['QAR', '634', 'Qatari Riyal', 'ر.ق', 2],
            ['OMR', '512', 'Omani Rial', 'ر.ع.', 3],
            ['EGP', '818', 'Egyptian Pound', 'ج.م', 2],
            ['JOD', '400', 'Jordanian Dinar', 'د.ا', 3],
            ['LBP', '422', 'Lebanese Pound', 'ل.ل', 2],
            ['TRY', '949', 'Turkish Lira', '₺', 2],
            ['INR', '356', 'Indian Rupee', '₹', 2],
            ['PKR', '586', 'Pakistani Rupee', '₨', 2],
            ['CNY', '156', 'Chinese Yuan', '¥', 2],
            ['JPY', '392', 'Japanese Yen', '¥', 0],
            ['CAD', '124', 'Canadian Dollar', '$', 2],
            ['AUD', '036', 'Australian Dollar', '$', 2],
            ['CHF', '756', 'Swiss Franc', 'CHF', 2],
            ['SEK', '752', 'Swedish Krona', 'kr', 2],
            ['NOK', '578', 'Norwegian Krone', 'kr', 2],
            ['ZAR', '710', 'South African Rand', 'R', 2],
            ['NGN', '566', 'Nigerian Naira', '₦', 2],
            ['MYR', '458', 'Malaysian Ringgit', 'RM', 2],
            ['IDR', '360', 'Indonesian Rupiah', 'Rp', 2],
            ['SGD', '702', 'Singapore Dollar', '$', 2],
            ['BRL', '986', 'Brazilian Real', 'R$', 2],
            ['RUB', '643', 'Russian Ruble', '₽', 2],
            ['MAD', '504', 'Moroccan Dirham', 'د.م.', 2],
        ];
        $now = now();
        $order = 0;
        foreach ($rows as [$code, $numeric, $name, $symbol, $decimals]) {
            $order++;
            if ($db->table('currencies')->where('code', '=', $code)->exists()) {
                continue;
            }
            $db->table('currencies')->insert([
                'uuid'           => $this->uuid($db),
                'code'           => $code,
                'numeric_code'   => $numeric,
                'name'           => $name,
                'symbol'         => $symbol,
                'decimal_places' => $decimals,
                'is_active'      => 1,
                'sort_order'     => $order,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }

    private function seedLanguages(Database $db): void
    {
        // [code, name, native, direction, is_default]
        $rows = [
            ['en', 'English', 'English', 'ltr', 1],
            ['ar', 'Arabic', 'العربية', 'rtl', 0],
            ['fr', 'French', 'Français', 'ltr', 0],
            ['es', 'Spanish', 'Español', 'ltr', 0],
            ['de', 'German', 'Deutsch', 'ltr', 0],
            ['tr', 'Turkish', 'Türkçe', 'ltr', 0],
            ['ur', 'Urdu', 'اردو', 'rtl', 0],
            ['fa', 'Persian', 'فارسی', 'rtl', 0],
            ['hi', 'Hindi', 'हिन्दी', 'ltr', 0],
            ['zh', 'Chinese', '中文', 'ltr', 0],
            ['ru', 'Russian', 'Русский', 'ltr', 0],
            ['pt', 'Portuguese', 'Português', 'ltr', 0],
            ['id', 'Indonesian', 'Bahasa Indonesia', 'ltr', 0],
            ['ms', 'Malay', 'Bahasa Melayu', 'ltr', 0],
            ['bn', 'Bengali', 'বাংলা', 'ltr', 0],
            ['it', 'Italian', 'Italiano', 'ltr', 0],
        ];
        $now = now();
        $order = 0;
        foreach ($rows as [$code, $name, $native, $direction, $isDefault]) {
            $order++;
            if ($db->table('languages')->where('code', '=', $code)->exists()) {
                continue;
            }
            $db->table('languages')->insert([
                'uuid'        => $this->uuid($db),
                'code'        => $code,
                'name'        => $name,
                'native_name' => $native,
                'direction'   => $direction,
                'is_active'   => 1,
                'is_default'  => $isDefault,
                'sort_order'  => $order,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    private function seedCountries(Database $db): void
    {
        // [iso2, iso3, numeric, name, phone, currency_code, region, flag]
        $rows = [
            ['SA', 'SAU', '682', 'Saudi Arabia', '+966', 'SAR', 'Middle East', '🇸🇦'],
            ['AE', 'ARE', '784', 'United Arab Emirates', '+971', 'AED', 'Middle East', '🇦🇪'],
            ['KW', 'KWT', '414', 'Kuwait', '+965', 'KWD', 'Middle East', '🇰🇼'],
            ['BH', 'BHR', '048', 'Bahrain', '+973', 'BHD', 'Middle East', '🇧🇭'],
            ['QA', 'QAT', '634', 'Qatar', '+974', 'QAR', 'Middle East', '🇶🇦'],
            ['OM', 'OMN', '512', 'Oman', '+968', 'OMR', 'Middle East', '🇴🇲'],
            ['EG', 'EGY', '818', 'Egypt', '+20', 'EGP', 'Africa', '🇪🇬'],
            ['JO', 'JOR', '400', 'Jordan', '+962', 'JOD', 'Middle East', '🇯🇴'],
            ['LB', 'LBN', '422', 'Lebanon', '+961', 'LBP', 'Middle East', '🇱🇧'],
            ['IQ', 'IRQ', '368', 'Iraq', '+964', 'USD', 'Middle East', '🇮🇶'],
            ['SY', 'SYR', '760', 'Syria', '+963', 'USD', 'Middle East', '🇸🇾'],
            ['YE', 'YEM', '887', 'Yemen', '+967', 'USD', 'Middle East', '🇾🇪'],
            ['PS', 'PSE', '275', 'Palestine', '+970', 'USD', 'Middle East', '🇵🇸'],
            ['MA', 'MAR', '504', 'Morocco', '+212', 'MAD', 'Africa', '🇲🇦'],
            ['DZ', 'DZA', '012', 'Algeria', '+213', 'USD', 'Africa', '🇩🇿'],
            ['TN', 'TUN', '788', 'Tunisia', '+216', 'USD', 'Africa', '🇹🇳'],
            ['LY', 'LBY', '434', 'Libya', '+218', 'USD', 'Africa', '🇱🇾'],
            ['SD', 'SDN', '729', 'Sudan', '+249', 'USD', 'Africa', '🇸🇩'],
            ['TR', 'TUR', '792', 'Turkey', '+90', 'TRY', 'Europe', '🇹🇷'],
            ['US', 'USA', '840', 'United States', '+1', 'USD', 'Americas', '🇺🇸'],
            ['GB', 'GBR', '826', 'United Kingdom', '+44', 'GBP', 'Europe', '🇬🇧'],
            ['CA', 'CAN', '124', 'Canada', '+1', 'CAD', 'Americas', '🇨🇦'],
            ['DE', 'DEU', '276', 'Germany', '+49', 'EUR', 'Europe', '🇩🇪'],
            ['FR', 'FRA', '250', 'France', '+33', 'EUR', 'Europe', '🇫🇷'],
            ['ES', 'ESP', '724', 'Spain', '+34', 'EUR', 'Europe', '🇪🇸'],
            ['IT', 'ITA', '380', 'Italy', '+39', 'EUR', 'Europe', '🇮🇹'],
            ['NL', 'NLD', '528', 'Netherlands', '+31', 'EUR', 'Europe', '🇳🇱'],
            ['SE', 'SWE', '752', 'Sweden', '+46', 'SEK', 'Europe', '🇸🇪'],
            ['NO', 'NOR', '578', 'Norway', '+47', 'NOK', 'Europe', '🇳🇴'],
            ['CH', 'CHE', '756', 'Switzerland', '+41', 'CHF', 'Europe', '🇨🇭'],
            ['RU', 'RUS', '643', 'Russia', '+7', 'RUB', 'Europe', '🇷🇺'],
            ['IN', 'IND', '356', 'India', '+91', 'INR', 'Asia', '🇮🇳'],
            ['PK', 'PAK', '586', 'Pakistan', '+92', 'PKR', 'Asia', '🇵🇰'],
            ['BD', 'BGD', '050', 'Bangladesh', '+880', 'USD', 'Asia', '🇧🇩'],
            ['CN', 'CHN', '156', 'China', '+86', 'CNY', 'Asia', '🇨🇳'],
            ['JP', 'JPN', '392', 'Japan', '+81', 'JPY', 'Asia', '🇯🇵'],
            ['KR', 'KOR', '410', 'South Korea', '+82', 'USD', 'Asia', '🇰🇷'],
            ['ID', 'IDN', '360', 'Indonesia', '+62', 'IDR', 'Asia', '🇮🇩'],
            ['MY', 'MYS', '458', 'Malaysia', '+60', 'MYR', 'Asia', '🇲🇾'],
            ['SG', 'SGP', '702', 'Singapore', '+65', 'SGD', 'Asia', '🇸🇬'],
            ['PH', 'PHL', '608', 'Philippines', '+63', 'USD', 'Asia', '🇵🇭'],
            ['AU', 'AUS', '036', 'Australia', '+61', 'AUD', 'Oceania', '🇦🇺'],
            ['BR', 'BRA', '076', 'Brazil', '+55', 'BRL', 'Americas', '🇧🇷'],
            ['ZA', 'ZAF', '710', 'South Africa', '+27', 'ZAR', 'Africa', '🇿🇦'],
            ['NG', 'NGA', '566', 'Nigeria', '+234', 'NGN', 'Africa', '🇳🇬'],
            ['KE', 'KEN', '404', 'Kenya', '+254', 'USD', 'Africa', '🇰🇪'],
        ];
        $now = now();
        $order = 0;
        foreach ($rows as [$iso2, $iso3, $numeric, $name, $phone, $currencyCode, $region, $flag]) {
            $order++;
            if ($db->table('countries')->where('iso2', '=', $iso2)->exists()) {
                continue;
            }
            $currencyId = $db->table('currencies')->where('code', '=', $currencyCode)->value('id');
            $db->table('countries')->insert([
                'uuid'                => $this->uuid($db),
                'iso2'                => $iso2,
                'iso3'                => $iso3,
                'numeric_code'        => $numeric,
                'name'                => $name,
                'phone_code'          => $phone,
                'default_currency_id' => $currencyId !== null ? (int) $currencyId : null,
                'region'              => $region,
                'flag_emoji'          => $flag,
                'is_active'           => 1,
                'sort_order'          => $order,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }
    }

    private function seedTimezones(Database $db): void
    {
        // [name, abbreviation, utc_offset, offset_minutes, country_iso2]
        $rows = [
            ['Asia/Riyadh', 'AST', '+03:00', 180, 'SA'],
            ['Asia/Dubai', 'GST', '+04:00', 240, 'AE'],
            ['Asia/Kuwait', 'AST', '+03:00', 180, 'KW'],
            ['Asia/Bahrain', 'AST', '+03:00', 180, 'BH'],
            ['Asia/Qatar', 'AST', '+03:00', 180, 'QA'],
            ['Asia/Muscat', 'GST', '+04:00', 240, 'OM'],
            ['Africa/Cairo', 'EET', '+02:00', 120, 'EG'],
            ['Asia/Amman', 'EET', '+02:00', 120, 'JO'],
            ['Asia/Beirut', 'EET', '+02:00', 120, 'LB'],
            ['Africa/Casablanca', 'WET', '+01:00', 60, 'MA'],
            ['Europe/Istanbul', 'TRT', '+03:00', 180, 'TR'],
            ['Europe/London', 'GMT', '+00:00', 0, 'GB'],
            ['Europe/Paris', 'CET', '+01:00', 60, 'FR'],
            ['Europe/Berlin', 'CET', '+01:00', 60, 'DE'],
            ['Europe/Madrid', 'CET', '+01:00', 60, 'ES'],
            ['Europe/Moscow', 'MSK', '+03:00', 180, 'RU'],
            ['America/New_York', 'EST', '-05:00', -300, 'US'],
            ['America/Chicago', 'CST', '-06:00', -360, 'US'],
            ['America/Los_Angeles', 'PST', '-08:00', -480, 'US'],
            ['America/Toronto', 'EST', '-05:00', -300, 'CA'],
            ['America/Sao_Paulo', 'BRT', '-03:00', -180, 'BR'],
            ['Asia/Karachi', 'PKT', '+05:00', 300, 'PK'],
            ['Asia/Kolkata', 'IST', '+05:30', 330, 'IN'],
            ['Asia/Dhaka', 'BST', '+06:00', 360, 'BD'],
            ['Asia/Shanghai', 'CST', '+08:00', 480, 'CN'],
            ['Asia/Tokyo', 'JST', '+09:00', 540, 'JP'],
            ['Asia/Singapore', 'SGT', '+08:00', 480, 'SG'],
            ['Asia/Jakarta', 'WIB', '+07:00', 420, 'ID'],
            ['Asia/Kuala_Lumpur', 'MYT', '+08:00', 480, 'MY'],
            ['Australia/Sydney', 'AEST', '+10:00', 600, 'AU'],
            ['Africa/Lagos', 'WAT', '+01:00', 60, 'NG'],
            ['Africa/Johannesburg', 'SAST', '+02:00', 120, 'ZA'],
            ['UTC', 'UTC', '+00:00', 0, null],
        ];
        $now = now();
        $order = 0;
        foreach ($rows as [$name, $abbr, $offset, $minutes, $countryIso2]) {
            $order++;
            if ($db->table('timezones')->where('name', '=', $name)->exists()) {
                continue;
            }
            $countryId = null;
            if ($countryIso2 !== null) {
                $cid = $db->table('countries')->where('iso2', '=', $countryIso2)->value('id');
                $countryId = $cid !== null ? (int) $cid : null;
            }
            $db->table('timezones')->insert([
                'uuid'           => $this->uuid($db),
                'name'           => $name,
                'abbreviation'   => $abbr,
                'utc_offset'     => $offset,
                'offset_minutes' => $minutes,
                'country_id'     => $countryId,
                'is_active'      => 1,
                'sort_order'     => $order,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }

    private function uuid(Database $db): string
    {
        return (string) $db->scalar('SELECT UUID()');
    }
};
