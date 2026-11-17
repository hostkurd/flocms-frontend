<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Style;

use FloCMS\Core\Template;
use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Frontend;

/**
 * Builds the one Site Style CSS file: the fonts, the --fe-* variables (light
 * and dark), the variables flocms-admin's blocks read (so blocks and
 * components look alike on one page), the base styles and the CSS of the
 * package's components. Logical properties throughout, so one file serves
 * both directions.
 */
final class StyleCss
{
    /** Fallback fonts after the style's own. */
    public const SYSTEM_FONTS = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

    /** @return list<string> the files the CSS is made of (their changes give the file a new name) */
    public static function sources(): array
    {
        $files = [Frontend::path('resources/css/base.css')];
        foreach (array_keys(Components::all()) as $name) {
            if (Components::cssInStyle($name)) {
                $files[] = (string) Components::file($name, 'css');
            }
        }
        $style = Style::file();
        if ($style !== null) { $files[] = $style; }
        return $files;
    }

    public static function build(): string
    {
        $values = Style::values();
        $palette = Style::palette();
        $css = '/* flocms-frontend ' . Frontend::VERSION . ': the Site Style, generated from the template\'s style.php. Change the values there, not this file. */' . "\n";
        $css .= self::fonts($values);
        $css .= ':root{' . self::variables($palette['light']) . self::layout($values) . 'color-scheme:light}' . "\n";
        $dark = self::variables($palette['dark']) . 'color-scheme:dark';
        switch ($values['dark']) {
            case 'dark':
                $css .= ':root{' . $dark . '}' . "\n";
                break;
            case 'auto':
            case 'switch':
                $css .= '@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){' . $dark . '}}' . "\n";
                $css .= ':root[data-theme="dark"]{' . $dark . '}' . "\n";
                break;
        }
        $css .= self::scripts($values);
        // flocms-admin's blocks read these (0.29+); html:root wins over its own :root defaults in any order
        $css .= 'html:root{--flo-brand:var(--fe-brand);--flo-brand-text:var(--fe-on-brand);--flo-radius:var(--fe-radius);--flo-muted:var(--fe-muted);'
            . '--flo-dark:var(--fe-band);--flo-light:var(--fe-surface);--flo-container:var(--fe-width);--flo-gutter:var(--fe-gutter);'
            . '--flo-block-gap:var(--fe-space-7);--flo-space-sm:var(--fe-space-4);--flo-space-md:var(--fe-space-6);--flo-space-lg:var(--fe-space-7);--flo-space-xl:var(--fe-section)}' . "\n";
        $css .= (string) @file_get_contents(Frontend::path('resources/css/base.css')) . "\n";
        foreach (array_keys(Components::all()) as $name) {
            if (Components::cssInStyle($name)) {
                $css .= '/* ' . $name . " */\n" . (string) @file_get_contents((string) Components::file($name, 'css')) . "\n";
            }
        }
        return $css;
    }

    /** @param array<string, string> $colours */
    private static function variables(array $colours): string
    {
        $out = '';
        foreach ($colours as $name => $value) {
            $out .= '--fe-' . str_replace('_', '-', $name) . ':' . strtolower($value) . ';';
        }
        foreach (Colour::scale($colours['brand']) as $step => $value) {
            $out .= '--fe-brand-' . $step . ':' . $value . ';';
        }
        return $out;
    }

    /** @param array<string, mixed> $values */
    private static function layout(array $values): string
    {
        $radius = Style::RADII[$values['radius']];
        $factor = Style::SPACINGS[$values['spacing']];
        $out = '--fe-radius:' . $radius . 'px;--fe-radius-lg:' . ($radius >= 999 ? 24 : min(24, $radius * 2)) . 'px;--fe-width:' . $values['width'] . 'px;--fe-gutter:clamp(20px,4vw,48px);';
        foreach ([1 => 4, 2 => 8, 3 => 12, 4 => 16, 5 => 24, 6 => 32, 7 => 48, 8 => 64] as $step => $px) {
            $out .= '--fe-space-' . $step . ':' . round($px * $factor) . 'px;';
        }
        // Space above and below a section: 104 px on a wide screen in the default look, less on a phone
        $out .= '--fe-section:clamp(' . round(64 * $factor) . 'px,' . round(8 * $factor, 1) . 'vw,' . round(104 * $factor) . 'px);';
        $latin = self::family($values['fonts']['latin']);
        $arabic = self::family($values['fonts']['arabic']);
        $headings = $values['fonts']['headings'] !== null ? self::family($values['fonts']['headings']) . ',' : '';
        $out .= '--fe-font:' . $latin . ',' . $arabic . ',' . self::SYSTEM_FONTS . ';';
        $out .= '--fe-font-headings:' . $headings . 'var(--fe-font);';
        $out .= '--fe-font-mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;';
        $out .= '--fe-size:1rem;--fe-leading:1.6;--fe-heading-leading:1.15;--fe-tracking:-0.02em;';
        return $out;
    }

    /** Arabic-script pages (Arabic, Kurdish Sorani): their font first, a slightly larger size and line height. */
    private static function scripts(array $values): string
    {
        $latin = self::family($values['fonts']['latin']);
        $arabic = self::family($values['fonts']['arabic']);
        return '.fe-script-arabic{--fe-font:' . $arabic . ',' . $latin . ',' . self::SYSTEM_FONTS . ';--fe-size:1.0625rem;--fe-leading:1.8;--fe-heading-leading:1.4;--fe-tracking:0}' . "\n";
    }

    /** @param array<string, mixed> $values */
    private static function fonts(array $values): string
    {
        $css = '';
        $used = array_unique(array_filter([$values['fonts']['latin'], $values['fonts']['arabic'], $values['fonts']['headings']]));
        foreach ($used as $family) {
            foreach (Style::PACKAGE_FONTS[$family] ?? [] as $font) {
                $css .= '@font-face{font-family:' . self::family($family) . ';font-style:normal;font-weight:' . $font['weight'] . ';font-display:swap;src:url(' . Assets::fontUrl($font['file']) . ') format("woff2");unicode-range:' . $font['range'] . '}' . "\n";
            }
        }
        foreach ($values['font_files'] as $font) {
            $url = defined('SITE_URI') ? Template::asset($font['file']) : $font['file'];
            $format = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'][strtolower(pathinfo($font['file'], PATHINFO_EXTENSION))] ?? 'woff2';
            $css .= '@font-face{font-family:' . self::family($font['family']) . ';font-style:normal;font-weight:' . $font['weight'] . ';font-display:swap;src:url("' . str_replace(['"', '\\', "\n"], '', $url) . '") format("' . $format . '")' . ($font['range'] !== null ? ';unicode-range:' . $font['range'] : '') . '}' . "\n";
        }
        return $css;
    }

    private static function family(string $name): string
    {
        return '"' . str_replace(['"', '\\', ';', '{', '}'], '', $name) . '"';
    }
}
