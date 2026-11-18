<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

use FloCMS\Core\Http\Response;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Seo\Seo;
use FloCMS\Frontend\Seo\Sitemap;
use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Support\Languages;

/** RSS 2.0 per language (D41), and the collections' sitemap entries. */
final class Feed
{
    public const ITEMS = 20;

    public static function response(Collection $collection): Response
    {
        return Response::make(self::xml($collection), 200)
            ->header('Content-Type', 'application/rss+xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=900');
    }

    public static function xml(Collection $collection): string
    {
        $lang = Languages::current();
        $site = Frontend::config()->site();
        // The newest items (one query, never every item), those written in this language
        $query = new Query();
        $query->perPage = self::ITEMS * 3;
        $items = array_slice(array_values(array_filter($collection->source()->query($query)->items, static fn (Item $i): bool => $i->hasLanguage($lang))), 0, self::ITEMS);
        // Characters XML 1.0 doesn't allow are dropped
        $x = static fn (string $s): string => htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n";
        $out .= '<title>' . $x($collection::label() . ' – ' . $site['name']) . '</title>' . "\n";
        $out .= '<link>' . $x(Seo::absolute($collection::url())) . '</link>' . "\n";
        $out .= '<atom:link href="' . $x(Seo::absolute($collection::url() . '/feed.xml')) . '" rel="self" type="application/rss+xml"/>' . "\n";
        $out .= '<description>' . $x($collection::lead() !== '' ? $collection::lead() : (string) $site['description']) . '</description>' . "\n";
        $out .= '<language>' . $x($lang) . '</language>' . "\n";
        if ($items !== [] && $items[0]->date() !== null) {
            $out .= '<lastBuildDate>' . date(DATE_RSS, (int) strtotime((string) $items[0]->date())) . '</lastBuildDate>' . "\n";
        }
        foreach ($items as $item) {
            $url = Seo::absolute($item->url($lang));
            $out .= '<item><title>' . $x($item->title()) . '</title><link>' . $x($url) . '</link><guid isPermaLink="true">' . $x($url) . '</guid>';
            if ($item->date() !== null) {
                $out .= '<pubDate>' . date(DATE_RSS, (int) strtotime((string) $item->date())) . '</pubDate>';
            }
            $text = $item->get('excerpt') ?: $item->get('summary') ?: '';
            if (is_string($text) && $text !== '') {
                $out .= '<description>' . $x($text) . '</description>';
            }
            $out .= "</item>\n";
        }
        return $out . '</channel></rss>' . "\n";
    }

    /** Every visible item of every collection with pages, in each language it is written in. */
    public static function registerSitemap(): void
    {
        Sitemap::add(static function (): array {
            $entries = [];
            foreach (Collections::public() as $collection) {
                $entries[] = ['loc' => '/' . $collection::path(Languages::default()), 'alternates' => self::listAlternates($collection)];
                foreach ($collection->source()->all() as $item) {
                    if (($item->raw('noindex') ?? 0) == 1) { continue; }
                    $languages = $item->languages();
                    if ($languages === []) { continue; }
                    $main = in_array(Languages::default(), $languages, true) ? Languages::default() : $languages[0];
                    $entry = ['loc' => $item->url($main)];
                    $updated = $item->raw('updated_at') ?? $item->date();
                    if (is_string($updated) && strtotime($updated) !== false) {
                        $entry['lastmod'] = date('Y-m-d', (int) strtotime($updated));
                    }
                    if (count($languages) > 1) {
                        foreach ($languages as $l) { $entry['alternates'][$l] = $item->url($l); }
                    }
                    $entries[] = $entry;
                }
            }
            return $entries;
        });
    }

    /** @return array<string, string> */
    private static function listAlternates(Collection $collection): array
    {
        $out = [];
        foreach (Languages::all() as $lang) {
            $out[$lang] = '/' . $collection::path($lang);
        }
        return $out;
    }
}
