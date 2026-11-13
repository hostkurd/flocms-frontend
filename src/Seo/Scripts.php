<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Seo;

use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Html;

/**
 * Scripts that need the visitor's consent, and the consent itself (the
 * cookie notice's choice).
 *
 *     site_script('statistics', 'https://stats.example.com/script.js');
 *     site_script('marketing', '<script>…</script>');
 *
 * "needed" scripts load at once. "statistics" and "marketing" load only
 * after the visitor allows that category: until then they are kept in
 * <template> tags that the notice's script turns on when the visitor agrees,
 * without reloading the page.
 */
final class Scripts
{
    public const CATEGORIES = ['needed', 'statistics', 'marketing'];
    public const COOKIE = 'fe_consent';
    /** Bump when the categories change, so visitors are asked again. */
    public const VERSION = '1';

    /** @var list<array{category: string, html: string}> */
    private static array $scripts = [];

    public static function add(string $category, string $script): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('site_script(): the category is "needed", "statistics" or "marketing", not "' . $category . '".');
        }
        $script = trim($script);
        if ($script === '') { return; }
        if (preg_match('#^(?:https?:)?//\S+$|^/(?!/)\S+$#i', $script) === 1) {
            $html = '<script src="' . Html::e($script) . '" defer></script>';
        } elseif (str_starts_with($script, '<')) {
            $html = $script; // the developer's own markup
        } else {
            $html = '<script>' . $script . '</script>';
        }
        self::$scripts[] = ['category' => $category, 'html' => $html];
    }

    /** Whether any script waits for consent (the notice then shows when the setting is "auto"). */
    public static function needsConsent(): bool
    {
        foreach (self::$scripts as $script) {
            if ($script['category'] !== 'needed') { return true; }
        }
        return false;
    }

    /** The scripts, for the end of the body. */
    public static function html(): string
    {
        $html = '';
        $allowed = self::allowed();
        foreach (self::$scripts as $script) {
            if ($script['category'] === 'needed' || in_array($script['category'], $allowed ?? [], true)) {
                $html .= $script['html'] . "\n";
            } else {
                $html .= '<template data-fe-consent="' . $script['category'] . '">' . $script['html'] . '</template>' . "\n";
            }
        }
        return $html;
    }

    /** @return list<string>|null the categories the visitor allowed; null when they haven't chosen yet */
    public static function allowed(): ?array
    {
        $value = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($value) || preg_match('/^v(\d+):([a-z,]*)$/', $value, $m) !== 1 || $m[1] !== self::VERSION) {
            return null;
        }
        return array_values(array_intersect(explode(',', $m[2]), Frontend::config()->cookies()['categories']));
    }

    /** The cookie's value for a choice. @param list<string> $categories */
    public static function cookieValue(array $categories): string
    {
        return 'v' . self::VERSION . ':' . implode(',', array_values(array_intersect($categories, Frontend::config()->cookies()['categories'])));
    }

    /** Whether the cookie notice is shown on this page. */
    public static function showNotice(): bool
    {
        $setting = Frontend::config()->cookies()['notice'];
        if ($setting === 'off') { return false; }
        if ($setting === 'auto' && !self::needsConsent()) { return false; }
        return self::allowed() === null || isset($_GET['fe-cookies']);
    }

    public static function reset(): void
    {
        self::$scripts = [];
    }
}
