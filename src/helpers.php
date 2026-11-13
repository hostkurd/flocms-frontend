<?php
declare(strict_types=1);

/*
 * Template helpers of flocms-frontend. All start with site_ (and component()),
 * so they never clash with core's or flocms-admin's helpers. Each is only
 * defined when the site hasn't defined one of the same name.
 */

use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Menus\Menus;
use FloCMS\Frontend\Page;
use FloCMS\Frontend\Seo\Scripts;
use FloCMS\Frontend\Seo\Seo;
use FloCMS\Frontend\Support\Format;
use FloCMS\Frontend\Support\FrontendLang;
use FloCMS\Frontend\Support\Icons;
use FloCMS\Frontend\Support\Languages;

if (!function_exists('component')) {
    /**
     * A component without slots: {!! component('button', ['label' => 'Contact us', 'url' => '/contact']) !!}
     *
     * @param array<string, mixed> $props
     * @param array<string, string> $slots
     */
    function component(string $name, array $props = [], array $slots = []): string
    {
        return Components::render($name, $props, $slots);
    }
}

if (!function_exists('site_html')) {
    /** The <html> tag with lang, dir and the visitor's light/dark choice. @param array<string, mixed> $attributes */
    function site_html(array $attributes = []): string
    {
        return Page::html($attributes);
    }
}

if (!function_exists('site_head')) {
    /** Everything the page's <head> needs: SEO tags, the Site Style, the components' CSS. */
    function site_head(): string
    {
        return Page::head();
    }
}

if (!function_exists('site_foot')) {
    /** The end of <body>: cookie notice, consented scripts, the components' script. */
    function site_foot(): string
    {
        return Page::foot();
    }
}

if (!function_exists('site_seo')) {
    /** The page's SEO settings: site_seo()->title('Our plans')->description('…'). */
    function site_seo(): Seo
    {
        return Seo::page();
    }
}

if (!function_exists('site_text')) {
    /** One of the package's words ("frontend.<key>" in the site's lang files changes it). @param array<string, string|int|float> $replace */
    function site_text(string $key, array $replace = []): string
    {
        return FrontendLang::get($key, $replace);
    }
}

if (!function_exists('site_label')) {
    /** A label: a word key ("frontend.menu.home", or one of the site's) is translated, other text is kept. */
    function site_label(string $text): string
    {
        return FrontendLang::label($text);
    }
}

if (!function_exists('site_url')) {
    /** An address in the page's language: site_url('/services') → https://site/<lang>/services */
    function site_url(string $path = '/', ?string $lang = null): string
    {
        return Languages::url($path, $lang);
    }
}

if (!function_exists('site_icon')) {
    /** One of the package's icons as inline SVG. @param array{size?: int, label?: string, class?: string, stroke?: float|int} $options */
    function site_icon(string $name, array $options = []): string
    {
        return Icons::svg($name, $options);
    }
}

if (!function_exists('site_menu')) {
    /** A menu from config/frontend.php, for this visitor and page. @return list<array<string, mixed>> */
    function site_menu(string $name = 'main'): array
    {
        return Menus::get($name);
    }
}

if (!function_exists('site_script')) {
    /** A script that loads once the visitor allows its category: needed, statistics or marketing. */
    function site_script(string $category, string $script): string
    {
        Scripts::add($category, $script);
        return '';
    }
}

if (!function_exists('site_number')) {
    function site_number(int|float|string|null $value, int $decimals = 0, ?string $lang = null): string
    {
        return Format::number($value, $decimals, $lang);
    }
}

if (!function_exists('site_money')) {
    function site_money(int|float|string|null $amount, string $currency = 'USD', ?int $decimals = null, ?string $lang = null): string
    {
        return Format::money($amount, $currency, $decimals, $lang);
    }
}

if (!function_exists('site_date')) {
    /** A date in the page's language: style long (default), medium, short or full. */
    function site_date(mixed $date, string $style = 'long', ?string $lang = null): string
    {
        return Format::date($date, $style, $lang);
    }
}

if (!function_exists('site_time')) {
    function site_time(mixed $date, ?string $lang = null): string
    {
        return Format::time($date, $lang);
    }
}

if (!function_exists('site_lang')) {
    /** The page's language code (en, ar, ku …). */
    function site_lang(): string
    {
        return Languages::current();
    }
}

if (!function_exists('site_is_rtl')) {
    function site_is_rtl(): bool
    {
        return Languages::isRtl();
    }
}
