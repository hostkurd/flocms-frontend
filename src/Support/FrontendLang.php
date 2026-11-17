<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Support;

use FloCMS\Core\Lang;
use FloCMS\Frontend\Frontend;

/**
 * The package's own words ("Main menu", "Next slide", the cookie notice).
 *
 * Looked up as "frontend.<key>" in the site's lang/<code>.php first, so a site
 * can change any of them or add a language, then in this package's
 * resources/lang/<code>.php (en, ar, ku), then in English.
 */
final class FrontendLang
{
    /** @var array<string, array<string, string>> */
    private static array $files = [];

    /** @param array<string, string|int|float> $replace */
    public static function get(string $key, array $replace = []): string
    {
        $line = self::site('frontend.' . $key);
        if ($line === null) {
            $lang = Languages::current();
            $line = self::file($lang)[$key] ?? self::file('en')[$key] ?? $key;
        }
        return $replace === [] ? $line : Lang::replace($line, array_map('strval', $replace));
    }

    /** Whether the package or the site has a word for $key. */
    public static function has(string $key): bool
    {
        return self::site('frontend.' . $key) !== null || isset(self::file(Languages::current())[$key]) || isset(self::file('en')[$key]);
    }

    /**
     * A label written by a developer: "frontend.<key>" is one of the package's
     * words, a key of the site's lang file is translated, other text is shown
     * as written.
     */
    public static function label(string $text): string
    {
        if (str_starts_with($text, 'frontend.') && self::has(substr($text, 9))) {
            return self::get(substr($text, 9));
        }
        return self::site($text) ?? $text;
    }

    /** @return array<string, string> the package's words in a language ('' when it has none) */
    public static function file(string $lang): array
    {
        if (!isset(self::$files[$lang])) {
            $words = [];
            if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $lang) === 1) {
                $file = Frontend::path('resources/lang/' . $lang . '.php');
                if (is_file($file)) {
                    $loaded = require $file;
                    $words = is_array($loaded) ? array_filter($loaded, 'is_string') : [];
                }
            }
            self::$files[$lang] = $words;
        }
        return self::$files[$lang];
    }

    public static function reset(): void
    {
        self::$files = [];
    }

    private static function site(string $key): ?string
    {
        $line = Lang::get($key, null);
        return is_string($line) && $line !== '' ? $line : null;
    }
}
