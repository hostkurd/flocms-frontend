<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Support;

use FloCMS\Frontend\Frontend;

/**
 * Numbers, prices, dates and times in the page's language (D9, D10).
 *
 * PHP's intl is used when the server has it; otherwise the package's own
 * tables for en, ar and ku (common on shared hosting), so pages look the
 * same either way. Digits are Latin by default and Arabic-Indic per
 * language when config/frontend.php says 'digits' => ['ku' => 'arabic'].
 */
final class Format
{
    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** Month names when intl is missing (Gregorian; ar and ku as used in Iraq and the Kurdistan Region). */
    private const MONTHS = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'ar' => ['كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران', 'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول'],
        'ku' => ['کانوونی دووەم', 'شوبات', 'ئازار', 'نیسان', 'ئایار', 'حوزەیران', 'تەممووز', 'ئاب', 'ئەیلوول', 'تشرینی یەکەم', 'تشرینی دووەم', 'کانوونی یەکەم'],
    ];

    private const WEEKDAYS = [
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'ar' => ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'],
        'ku' => ['یەکشەممە', 'دووشەممە', 'سێشەممە', 'چوارشەممە', 'پێنجشەممە', 'هەینی', 'شەممە'],
    ];

    /** Currency signs and where they go: before (en) or after the number (ar, ku), as in the approved pages. */
    private const CURRENCIES = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'TRY' => '₺', 'IQD' => ['en' => 'IQD', 'ar' => 'د.ع', 'ku' => 'د.ع'], 'AED' => ['en' => 'AED', 'ar' => 'د.إ', 'ku' => 'د.إ']];

    private static ?bool $intl = null;

    /** Force intl on or off (tests); null: use it when the server has it. */
    public static function useIntl(?bool $intl): void
    {
        self::$intl = $intl;
    }

    public static function number(int|float|string|null $value, int $decimals = 0, ?string $lang = null): string
    {
        $lang ??= Languages::current();
        $number = is_numeric($value) ? (float) $value : 0.0;
        $decimals = max(0, min(10, $decimals));
        $arabic = self::arabicDigits($lang);
        if (self::intl()) {
            $formatter = new \NumberFormatter(self::locale($lang, $arabic), \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $out = $formatter->format($number);
            if (is_string($out)) { return self::stripMarks($out); }
        }
        $out = number_format($number, $decimals, $arabic ? '٫' : '.', $arabic ? '٬' : ',');
        return $arabic ? self::toArabicDigits($out) : $out;
    }

    /**
     * A price: "$2,400" in English, "2,400 $" in Arabic and Kurdish. Whole
     * amounts have no decimals unless $decimals says so.
     */
    public static function money(int|float|string|null $amount, string $currency = 'USD', ?int $decimals = null, ?string $lang = null): string
    {
        $lang ??= Languages::current();
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $decimals ??= floor($value) === $value ? 0 : 2;
        $number = self::number($value, $decimals, $lang);
        $code = strtoupper($currency);
        $sign = self::CURRENCIES[$code] ?? $code;
        if (is_array($sign)) { $sign = $sign[$lang] ?? $sign['en']; }
        $rtl = Languages::isRtl($lang);
        if ($rtl) {
            return $number . ' ' . $sign;
        }
        return mb_strlen($sign) === 1 ? $sign . $number : $number . ' ' . $sign;
    }

    /**
     * A date: style "long" (13 November 2026), "medium" (13 Nov 2026),
     * "short" (13/11/2026) or "full" (with the weekday).
     */
    public static function date(mixed $date, string $style = 'long', ?string $lang = null): string
    {
        $lang ??= Languages::current();
        $time = self::timestamp($date);
        if ($time === null) { return ''; }
        $arabic = self::arabicDigits($lang);
        if (self::intl()) {
            $styles = ['full' => \IntlDateFormatter::FULL, 'long' => \IntlDateFormatter::LONG, 'medium' => \IntlDateFormatter::MEDIUM, 'short' => \IntlDateFormatter::SHORT];
            $formatter = new \IntlDateFormatter(self::locale($lang, $arabic), $styles[$style] ?? \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, date_default_timezone_get(), \IntlDateFormatter::GREGORIAN);
            if ($style === 'short') { $formatter->setPattern('dd/MM/y'); }
            $out = $formatter->format($time);
            if (is_string($out)) { return self::stripMarks($out); }
        }
        $base = in_array($lang, ['ar', 'ku'], true) ? $lang : 'en';
        $day = date('j', $time);
        $month = (int) date('n', $time) - 1;
        $year = date('Y', $time);
        $out = match ($style) {
            'short' => date('d/m/Y', $time),
            'medium' => $base === 'en' ? $day . ' ' . substr(self::MONTHS['en'][$month], 0, 3) . ' ' . $year : $day . ' ' . self::MONTHS[$base][$month] . ' ' . $year,
            'full' => self::WEEKDAYS[$base][(int) date('w', $time)] . ($base === 'en' ? ', ' : '، ') . $day . ' ' . self::MONTHS[$base][$month] . ' ' . $year,
            default => $day . ' ' . self::MONTHS[$base][$month] . ' ' . $year,
        };
        return $arabic ? self::toArabicDigits($out) : $out;
    }

    /** A time of day: 14:30 (24-hour; the same in every language, apart from the digits). */
    public static function time(mixed $date, ?string $lang = null): string
    {
        $lang ??= Languages::current();
        $time = self::timestamp($date);
        if ($time === null) { return ''; }
        $out = date('H:i', $time);
        return self::arabicDigits($lang) ? self::toArabicDigits($out) : $out;
    }

    /** The date in ISO 8601, for <time datetime="…"> and JSON-LD. */
    public static function iso(mixed $date): string
    {
        $time = self::timestamp($date);
        return $time === null ? '' : date('c', $time);
    }

    public static function toArabicDigits(string $text): string
    {
        return strtr($text, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }

    public static function toLatinDigits(string $text): string
    {
        $map = array_flip(self::ARABIC_DIGITS);
        $map += ['۰' => 0, '۱' => 1, '۲' => 2, '۳' => 3, '۴' => 4, '۵' => 5, '۶' => 6, '۷' => 7, '۸' => 8, '۹' => 9];
        return strtr($text, array_map('strval', $map) + ['٬' => ',', '٫' => '.']);
    }

    private static function timestamp(mixed $date): ?int
    {
        if ($date instanceof \DateTimeInterface) { return $date->getTimestamp(); }
        if (is_int($date) || (is_string($date) && ctype_digit($date))) { return (int) $date; }
        if (is_string($date) && trim($date) !== '') {
            $time = strtotime($date);
            return $time === false ? null : $time;
        }
        return null;
    }

    private static function arabicDigits(string $lang): bool
    {
        return Frontend::config()->digits($lang) === 'arabic';
    }

    private static function intl(): bool
    {
        return (self::$intl ?? true) && class_exists(\NumberFormatter::class) && class_exists(\IntlDateFormatter::class);
    }

    private static function locale(string $lang, bool $arabic): string
    {
        return Frontend::config()->locale($lang) . '@numbers=' . ($arabic ? 'arab' : 'latn');
    }

    /** intl adds direction marks around some numbers; the page's direction already handles that. */
    private static function stripMarks(string $text): string
    {
        return str_replace(["\u{200E}", "\u{200F}", "\u{061C}", "\u{202A}", "\u{202B}", "\u{202C}"], '', $text);
    }
}
