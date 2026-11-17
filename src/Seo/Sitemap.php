<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Seo;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Support\Log;

/**
 * The site's own addresses in /sitemap.xml (D12): one sitemap.
 *
 *     Sitemap::add(fn () => [
 *         ['loc' => '/services', 'lastmod' => '2026-11-01'],
 *         ['loc' => '/services/hosting', 'alternates' => true],   // the same path in every language
 *     ]);
 *
 * With flocms-admin's Pages and sitemap on, these addresses join the admin's
 * sitemap; without it the package serves /sitemap.xml itself.
 */
final class Sitemap
{
    /** @var list<callable(): iterable<array<string, mixed>>> */
    private static array $lists = [];

    public static function add(callable $list): void
    {
        self::$lists[] = $list;
    }

    /**
     * The entries, ready for the admin's sitemap or this one: paths joined to
     * the site's address in the default language, 'alternates' => true
     * expanded to every language.
     *
     * @return list<array{loc: string, lastmod?: string, alternates?: array<string, string>}>
     */
    public static function entries(): array
    {
        $entries = [];
        foreach (self::$lists as $list) {
            try {
                foreach ($list() as $entry) {
                    if (is_string($entry)) { $entry = ['loc' => $entry]; }
                    if (!is_array($entry) || !is_string($entry['loc'] ?? null)) { continue; }
                    $loc = $entry['loc'];
                    $isPath = str_starts_with($loc, '/') && !str_starts_with($loc, '//');
                    $clean = ['loc' => $isPath ? Languages::url($loc, Languages::default()) : $loc];
                    if (isset($entry['lastmod'])) { $clean['lastmod'] = (string) $entry['lastmod']; }
                    if (($entry['alternates'] ?? null) === true && $isPath) {
                        foreach (Languages::all() as $lang) { $clean['alternates'][$lang] = Languages::url($loc, $lang); }
                    } elseif (is_array($entry['alternates'] ?? null)) {
                        foreach ($entry['alternates'] as $lang => $url) {
                            if (is_string($lang) && is_string($url)) {
                                $clean['alternates'][$lang] = str_starts_with($url, '/') && !str_starts_with($url, '//') ? Languages::url($url, $lang) : $url;
                            }
                        }
                    }
                    $entries[] = $clean;
                }
            } catch (\Throwable $error) {
                Log::warning('A sitemap list failed: ' . $error->getMessage());
            }
        }
        return $entries;
    }

    /** /sitemap.xml when the admin doesn't serve one (core's fallback, 2.6). */
    public static function fallback(string $path, Request $request): ?Response
    {
        if (trim($path, '/') !== 'sitemap.xml' || !$request->isMethod('GET', 'HEAD')) {
            return null;
        }
        if (self::adminServes() || self::$lists === []) {
            return null;
        }
        return Response::make(self::xml())->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /** Whether flocms-admin's Pages serve /sitemap.xml (then the entries are added there). */
    public static function adminServes(): bool
    {
        if (!class_exists(\FloCMS\Admin\Pages\Pages::class)) { return false; }
        try {
            return \FloCMS\Admin\Pages\Pages::enabled() && \FloCMS\Admin\Admin::config()->pages()['sitemap'];
        } catch (\Throwable) {
            return false;
        }
    }

    public static function xml(): string
    {
        $x = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach (self::entries() as $entry) {
            if (preg_match('#^https?://[^\x00-\x20\x7F<>"]+$#iDu', $entry['loc']) !== 1) { continue; }
            $xml .= "  <url>\n    <loc>" . $x($entry['loc']) . "</loc>\n";
            if (isset($entry['lastmod'])) {
                $time = strtotime($entry['lastmod']);
                if ($time !== false) { $xml .= '    <lastmod>' . date('Y-m-d', $time) . "</lastmod>\n"; }
            }
            foreach ($entry['alternates'] ?? [] as $lang => $url) {
                if (preg_match('/^(?:[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*|x-default)$/D', $lang) === 1 && preg_match('#^https?://[^\x00-\x20\x7F<>"]+$#iDu', $url) === 1) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $x($lang) . '" href="' . $x($url) . '"/>' . "\n";
                }
            }
            $xml .= "  </url>\n";
        }
        return $xml . '</urlset>' . "\n";
    }

    public static function reset(): void
    {
        // The lists are registered at boot (bootstrap files): they stay
    }

    /** For tests: forget the lists. */
    public static function clear(): void
    {
        self::$lists = [];
    }
}
