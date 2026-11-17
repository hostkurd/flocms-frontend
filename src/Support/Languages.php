<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Support;

use FloCMS\Core\App;
use FloCMS\Core\Config;
use FloCMS\Core\Env;
use FloCMS\Core\Lang;
use FloCMS\Frontend\Frontend;

/**
 * The site's languages (LANGUAGES / DEFAULT_LANG, as everywhere in FloCMS),
 * the page's language and direction, and addresses in each language.
 */
final class Languages
{
    /** Languages written right to left, when the site's lang file doesn't say (lng.dir). */
    public const RTL = ['ar', 'ku', 'fa', 'he', 'ur', 'ckb', 'ps', 'sd', 'yi', 'dv'];

    /** Languages written in Arabic script (their font is the style's "arabic" font). */
    public const ARABIC_SCRIPT = ['ar', 'ku', 'fa', 'ur', 'ckb', 'ps', 'sd'];

    private static ?string $override = null;

    /** @return list<string> */
    public static function all(): array
    {
        $languages = Config::get('languages');
        if (!is_array($languages) || $languages === []) {
            $languages = explode(',', (string) Env::get('LANGUAGES', 'en'));
        }
        $clean = [];
        foreach ($languages as $lang) {
            $lang = strtolower(trim((string) $lang));
            if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $lang) === 1 && !in_array($lang, $clean, true)) {
                $clean[] = $lang;
            }
        }
        return $clean === [] ? ['en'] : $clean;
    }

    public static function default(): string
    {
        $default = strtolower((string) (Config::get('default_language') ?: Env::get('DEFAULT_LANG', '')));
        $all = self::all();
        return in_array($default, $all, true) ? $default : $all[0];
    }

    /** The page's language (the URL's language segment). */
    public static function current(): string
    {
        if (self::$override !== null) {
            return self::$override;
        }
        $lang = defined('ACTIVE_LANG') ? strtolower((string) constant('ACTIVE_LANG')) : '';
        return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $lang) === 1 ? $lang : self::default();
    }

    /** For tests and for building a page in another language (null: the page's). */
    public static function use(?string $lang): void
    {
        self::$override = $lang;
    }

    public static function isRtl(?string $lang = null): bool
    {
        $lang ??= self::current();
        if (self::$override === null && $lang === self::current() && Lang::get('lng.dir', null) !== null) {
            return Lang::isRTL();
        }
        return in_array(explode('-', $lang)[0], self::RTL, true);
    }

    public static function dir(?string $lang = null): string
    {
        return self::isRtl($lang) ? 'rtl' : 'ltr';
    }

    /** "arabic" for Arabic-script languages, else "latin": which of the style's fonts the page uses. */
    public static function script(?string $lang = null): string
    {
        $lang ??= self::current();
        return in_array(explode('-', $lang)[0], self::ARABIC_SCRIPT, true) ? 'arabic' : 'latin';
    }

    /** A language's own name ("English", "العربية", "کوردی"). */
    public static function name(string $lang): string
    {
        $names = ['en' => 'English', 'ar' => 'العربية', 'ku' => 'کوردی', 'fa' => 'فارسی', 'tr' => 'Türkçe', 'el' => 'Ελληνικά', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español'];
        $site = Lang::get('frontend.language.' . $lang, null);
        return is_string($site) && $site !== '' ? $site : ($names[$lang] ?? strtoupper($lang));
    }

    /**
     * An address on the site: "/services" becomes SITE_URI/<lang>/services.
     * Full addresses (https://…), "#…", "mailto:" and "tel:" stay as they are.
     */
    public static function url(string $path, ?string $lang = null): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return Frontend::siteUri() . '/' . ($lang ?? self::current()) . '/';
        }
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\#|\?)#i', $path) === 1) {
            return $path;
        }
        return Frontend::siteUri() . '/' . ($lang ?? self::current()) . '/' . ltrim($path, '/');
    }

    /**
     * Like url(), but from the site's root ("/en/services"): for forms and
     * redirects that must stay on the address the visitor is using.
     */
    public static function path(string $path, ?string $lang = null): string
    {
        $url = self::url($path, $lang);
        $base = Frontend::siteUri();
        return $base !== '' && str_starts_with($url, $base . '/') ? Frontend::sitePath() . substr($url, strlen($base)) : $url;
    }

    /**
     * The path of the page being shown, without the site's base and language
     * ("services/hosting"), or '' on the home page.
     */
    public static function currentPath(): string
    {
        if (App::hasRouter()) {
            $uri = trim((string) App::getRouter()->getUri(), '/');
            $parts = $uri === '' ? [] : explode('/', $uri);
            if ($parts !== [] && in_array(strtolower($parts[0]), self::all(), true)) {
                array_shift($parts);
            }
            return implode('/', $parts);
        }
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
        $base = trim((string) parse_url(Frontend::siteUri(), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path . '/', $base . '/')) {
            $path = trim(substr($path, strlen($base)), '/');
        }
        $parts = $path === '' ? [] : explode('/', $path);
        if ($parts !== [] && in_array(strtolower($parts[0]), self::all(), true)) {
            array_shift($parts);
        }
        return implode('/', $parts);
    }

    /** The page being shown, in another language (for hreflang and the language switcher). */
    public static function switchUrl(string $lang): string
    {
        $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        $path = self::currentPath();
        return Frontend::siteUri() . '/' . $lang . '/' . $path . ($query !== '' ? '?' . $query : '');
    }
}
