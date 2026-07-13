<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lightweight geo reference data: countries (with their primary currency and
 * timezone, used for the country → currency/timezone cascade), currencies,
 * and IANA timezones. Codes match the storage format used across the app:
 * ISO 3166-1 alpha-2 country codes and ISO 4217 currency codes.
 */
final class Geo
{
    /**
     * @return list<array{code: string, name: string, currency: string, timezone: string}>
     */
    public static function countries(): array
    {
        $countries = [
            ['NG', 'Nigeria', 'NGN', 'Africa/Lagos'],
            ['GH', 'Ghana', 'GHS', 'Africa/Accra'],
            ['KE', 'Kenya', 'KES', 'Africa/Nairobi'],
            ['ZA', 'South Africa', 'ZAR', 'Africa/Johannesburg'],
            ['GB', 'United Kingdom', 'GBP', 'Europe/London'],
            ['US', 'United States', 'USD', 'America/New_York'],
            ['CA', 'Canada', 'CAD', 'America/Toronto'],
            ['EG', 'Egypt', 'EGP', 'Africa/Cairo'],
            ['DZ', 'Algeria', 'DZD', 'Africa/Algiers'],
            ['AO', 'Angola', 'AOA', 'Africa/Luanda'],
            ['BJ', 'Benin', 'XOF', 'Africa/Porto-Novo'],
            ['BW', 'Botswana', 'BWP', 'Africa/Gaborone'],
            ['BF', 'Burkina Faso', 'XOF', 'Africa/Ouagadougou'],
            ['BI', 'Burundi', 'BIF', 'Africa/Bujumbura'],
            ['CM', 'Cameroon', 'XAF', 'Africa/Douala'],
            ['CV', 'Cape Verde', 'CVE', 'Atlantic/Cape_Verde'],
            ['CF', 'Central African Republic', 'XAF', 'Africa/Bangui'],
            ['TD', 'Chad', 'XAF', 'Africa/Ndjamena'],
            ['KM', 'Comoros', 'KMF', 'Indian/Comoro'],
            ['CG', 'Congo (Brazzaville)', 'XAF', 'Africa/Brazzaville'],
            ['CD', 'Congo (Kinshasa)', 'CDF', 'Africa/Kinshasa'],
            ['CI', "Côte d'Ivoire", 'XOF', 'Africa/Abidjan'],
            ['DJ', 'Djibouti', 'DJF', 'Africa/Djibouti'],
            ['GQ', 'Equatorial Guinea', 'XAF', 'Africa/Malabo'],
            ['ER', 'Eritrea', 'ERN', 'Africa/Asmara'],
            ['SZ', 'Eswatini', 'SZL', 'Africa/Mbabane'],
            ['ET', 'Ethiopia', 'ETB', 'Africa/Addis_Ababa'],
            ['GA', 'Gabon', 'XAF', 'Africa/Libreville'],
            ['GM', 'Gambia', 'GMD', 'Africa/Banjul'],
            ['GN', 'Guinea', 'GNF', 'Africa/Conakry'],
            ['GW', 'Guinea-Bissau', 'XOF', 'Africa/Bissau'],
            ['LS', 'Lesotho', 'LSL', 'Africa/Maseru'],
            ['LR', 'Liberia', 'LRD', 'Africa/Monrovia'],
            ['LY', 'Libya', 'LYD', 'Africa/Tripoli'],
            ['MG', 'Madagascar', 'MGA', 'Indian/Antananarivo'],
            ['MW', 'Malawi', 'MWK', 'Africa/Blantyre'],
            ['ML', 'Mali', 'XOF', 'Africa/Bamako'],
            ['MR', 'Mauritania', 'MRU', 'Africa/Nouakchott'],
            ['MU', 'Mauritius', 'MUR', 'Indian/Mauritius'],
            ['MA', 'Morocco', 'MAD', 'Africa/Casablanca'],
            ['MZ', 'Mozambique', 'MZN', 'Africa/Maputo'],
            ['NA', 'Namibia', 'NAD', 'Africa/Windhoek'],
            ['NE', 'Niger', 'XOF', 'Africa/Niamey'],
            ['RW', 'Rwanda', 'RWF', 'Africa/Kigali'],
            ['ST', 'São Tomé and Príncipe', 'STN', 'Africa/Sao_Tome'],
            ['SN', 'Senegal', 'XOF', 'Africa/Dakar'],
            ['SC', 'Seychelles', 'SCR', 'Indian/Mahe'],
            ['SL', 'Sierra Leone', 'SLE', 'Africa/Freetown'],
            ['SO', 'Somalia', 'SOS', 'Africa/Mogadishu'],
            ['SS', 'South Sudan', 'SSP', 'Africa/Juba'],
            ['SD', 'Sudan', 'SDG', 'Africa/Khartoum'],
            ['TZ', 'Tanzania', 'TZS', 'Africa/Dar_es_Salaam'],
            ['TG', 'Togo', 'XOF', 'Africa/Lome'],
            ['TN', 'Tunisia', 'TND', 'Africa/Tunis'],
            ['UG', 'Uganda', 'UGX', 'Africa/Kampala'],
            ['ZM', 'Zambia', 'ZMW', 'Africa/Lusaka'],
            ['ZW', 'Zimbabwe', 'ZWL', 'Africa/Harare'],
            ['IE', 'Ireland', 'EUR', 'Europe/Dublin'],
            ['FR', 'France', 'EUR', 'Europe/Paris'],
            ['DE', 'Germany', 'EUR', 'Europe/Berlin'],
            ['ES', 'Spain', 'EUR', 'Europe/Madrid'],
            ['IT', 'Italy', 'EUR', 'Europe/Rome'],
            ['PT', 'Portugal', 'EUR', 'Europe/Lisbon'],
            ['NL', 'Netherlands', 'EUR', 'Europe/Amsterdam'],
            ['BE', 'Belgium', 'EUR', 'Europe/Brussels'],
            ['CH', 'Switzerland', 'CHF', 'Europe/Zurich'],
            ['SE', 'Sweden', 'SEK', 'Europe/Stockholm'],
            ['NO', 'Norway', 'NOK', 'Europe/Oslo'],
            ['DK', 'Denmark', 'DKK', 'Europe/Copenhagen'],
            ['FI', 'Finland', 'EUR', 'Europe/Helsinki'],
            ['PL', 'Poland', 'PLN', 'Europe/Warsaw'],
            ['AT', 'Austria', 'EUR', 'Europe/Vienna'],
            ['GR', 'Greece', 'EUR', 'Europe/Athens'],
            ['RU', 'Russia', 'RUB', 'Europe/Moscow'],
            ['TR', 'Türkiye', 'TRY', 'Europe/Istanbul'],
            ['UA', 'Ukraine', 'UAH', 'Europe/Kyiv'],
            ['AE', 'United Arab Emirates', 'AED', 'Asia/Dubai'],
            ['SA', 'Saudi Arabia', 'SAR', 'Asia/Riyadh'],
            ['QA', 'Qatar', 'QAR', 'Asia/Qatar'],
            ['KW', 'Kuwait', 'KWD', 'Asia/Kuwait'],
            ['IL', 'Israel', 'ILS', 'Asia/Jerusalem'],
            ['IN', 'India', 'INR', 'Asia/Kolkata'],
            ['PK', 'Pakistan', 'PKR', 'Asia/Karachi'],
            ['BD', 'Bangladesh', 'BDT', 'Asia/Dhaka'],
            ['CN', 'China', 'CNY', 'Asia/Shanghai'],
            ['HK', 'Hong Kong', 'HKD', 'Asia/Hong_Kong'],
            ['JP', 'Japan', 'JPY', 'Asia/Tokyo'],
            ['KR', 'South Korea', 'KRW', 'Asia/Seoul'],
            ['SG', 'Singapore', 'SGD', 'Asia/Singapore'],
            ['MY', 'Malaysia', 'MYR', 'Asia/Kuala_Lumpur'],
            ['ID', 'Indonesia', 'IDR', 'Asia/Jakarta'],
            ['TH', 'Thailand', 'THB', 'Asia/Bangkok'],
            ['VN', 'Vietnam', 'VND', 'Asia/Ho_Chi_Minh'],
            ['PH', 'Philippines', 'PHP', 'Asia/Manila'],
            ['AU', 'Australia', 'AUD', 'Australia/Sydney'],
            ['NZ', 'New Zealand', 'NZD', 'Pacific/Auckland'],
            ['BR', 'Brazil', 'BRL', 'America/Sao_Paulo'],
            ['AR', 'Argentina', 'ARS', 'America/Argentina/Buenos_Aires'],
            ['MX', 'Mexico', 'MXN', 'America/Mexico_City'],
            ['CL', 'Chile', 'CLP', 'America/Santiago'],
            ['CO', 'Colombia', 'COP', 'America/Bogota'],
            ['PE', 'Peru', 'PEN', 'America/Lima'],
        ];

        return array_map(
            static fn (array $c): array => ['code' => $c[0], 'name' => $c[1], 'currency' => $c[2], 'timezone' => $c[3]],
            $countries,
        );
    }

    /**
     * @return array<string, string> ISO 4217 code => display name
     */
    public static function currencies(): array
    {
        return [
            'NGN' => 'Nigerian Naira (₦)', 'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)', 'GBP' => 'British Pound (£)',
            'GHS' => 'Ghanaian Cedi (₵)', 'KES' => 'Kenyan Shilling', 'ZAR' => 'South African Rand', 'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar', 'NZD' => 'New Zealand Dollar', 'CHF' => 'Swiss Franc', 'CNY' => 'Chinese Yuan',
            'JPY' => 'Japanese Yen', 'INR' => 'Indian Rupee', 'AED' => 'UAE Dirham', 'SAR' => 'Saudi Riyal',
            'QAR' => 'Qatari Riyal', 'KWD' => 'Kuwaiti Dinar', 'EGP' => 'Egyptian Pound', 'MAD' => 'Moroccan Dirham',
            'DZD' => 'Algerian Dinar', 'TND' => 'Tunisian Dinar', 'XOF' => 'West African CFA Franc', 'XAF' => 'Central African CFA Franc',
            'TZS' => 'Tanzanian Shilling', 'UGX' => 'Ugandan Shilling', 'RWF' => 'Rwandan Franc', 'ZMW' => 'Zambian Kwacha',
            'BWP' => 'Botswana Pula', 'MZN' => 'Mozambican Metical', 'NAD' => 'Namibian Dollar', 'MUR' => 'Mauritian Rupee',
            'ETB' => 'Ethiopian Birr', 'SLE' => 'Sierra Leonean Leone', 'LRD' => 'Liberian Dollar', 'GMD' => 'Gambian Dalasi',
            'AOA' => 'Angolan Kwanza', 'CDF' => 'Congolese Franc', 'SEK' => 'Swedish Krona', 'NOK' => 'Norwegian Krone',
            'DKK' => 'Danish Krone', 'PLN' => 'Polish Złoty', 'RUB' => 'Russian Ruble', 'TRY' => 'Turkish Lira',
            'UAH' => 'Ukrainian Hryvnia', 'ILS' => 'Israeli Shekel', 'PKR' => 'Pakistani Rupee', 'BDT' => 'Bangladeshi Taka',
            'HKD' => 'Hong Kong Dollar', 'KRW' => 'South Korean Won', 'SGD' => 'Singapore Dollar', 'MYR' => 'Malaysian Ringgit',
            'IDR' => 'Indonesian Rupiah', 'THB' => 'Thai Baht', 'VND' => 'Vietnamese Đồng', 'PHP' => 'Philippine Peso',
            'BRL' => 'Brazilian Real', 'ARS' => 'Argentine Peso', 'MXN' => 'Mexican Peso', 'CLP' => 'Chilean Peso',
            'COP' => 'Colombian Peso', 'PEN' => 'Peruvian Sol',
        ];
    }

    /**
     * @return list<string> IANA timezone identifiers
     */
    public static function timezones(): array
    {
        return \DateTimeZone::listIdentifiers();
    }
}
