<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Style;

use FloCMS\Core\Template;
use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Assets\CacheFile;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Log;

/**
 * The Site Style (D6): the whole look of a site from one short list of
 * values in the active template's style.php, turned into one CSS file in PHP.
 * No Node or build step, so it works on plain shared hosting.
 *
 *     // templates/<active>/style.php
 *     return [
 *         'colours' => ['brand' => '#0A7FA8', 'accent' => '#38BBE0', 'text' => '#0E1A24', 'background' => '#FFFFFF'],
 *         'dark'    => 'switch',        // switch | auto | light | dark
 *         'fonts'   => ['latin' => 'Instrument Sans', 'arabic' => 'Noto Sans Arabic'],
 *         'radius'  => 'small',         // none | small | medium | large | round
 *         'spacing' => 'comfortable',   // compact | comfortable | roomy
 *         'width'   => 1200,
 *     ];
 *
 * The defaults are the approved default look (Option 2 of the mockups).
 * flocms-admin's Settings → Site style overrides the file's values.
 */
final class Style
{
    /** The default look: white content, cool grey rows, one navy closing band, FloCMS blue for buttons and links. */
    public const DEFAULTS = [
        'colours' => [
            'brand' => '#0A7FA8',
            'accent' => '#38BBE0',
            'text' => '#0E1A24',
            'muted' => '#56616C',
            'background' => '#FFFFFF',
            'surface' => '#F6F8FA',
            'band' => '#0C2233',
            'line' => '#E4E7EB',
            'line_strong' => '#CDD3DA',
            'success' => '#1E7A4F',
            'warning' => '#9A5B00',
            'danger' => '#C0352B',
        ],
        // Worked out from the colours above when not set (null)
        'dark_colours' => [
            'brand' => '#38BBE0',
            'link' => '#5FCBEA',
            'text' => null,
            'muted' => null,
            'background' => '#0B0F14',
            'surface' => '#10151B',
            'band' => '#070A0E',
            'line' => null,
            'line_strong' => null,
        ],
        // The approved header has a light/dark switch; 'auto' follows the device without one
        'dark' => 'switch',
        'fonts' => ['latin' => 'Instrument Sans', 'arabic' => 'Noto Sans Arabic', 'headings' => null],
        // Fonts of the site's own, in public/themes/<template>/: ['family' => 'Vazirmatn', 'file' => 'fonts/vazirmatn.woff2', 'weight' => '100 900', 'range' => 'U+0600-06FF']
        'font_files' => [],
        'radius' => 'small',
        'spacing' => 'comfortable',
        'width' => 1200,
    ];

    public const DARK_MODES = ['auto', 'light', 'dark', 'switch'];
    public const RADII = ['none' => 0, 'small' => 4, 'medium' => 8, 'large' => 12, 'round' => 999];
    public const SPACINGS = ['compact' => 0.8, 'comfortable' => 1.0, 'roomy' => 1.2];

    /** The fonts the package ships (self-hosted, SIL OFL 1.1). */
    public const PACKAGE_FONTS = [
        'Instrument Sans' => [
            ['file' => 'instrument-sans-latin-v1.woff2', 'weight' => '400 700', 'range' => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD'],
            ['file' => 'instrument-sans-latin-ext-v1.woff2', 'weight' => '400 700', 'range' => 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF'],
        ],
        'Noto Sans Arabic' => [
            ['file' => 'noto-sans-arabic-v1.woff2', 'weight' => '100 900', 'range' => 'U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08E1,U+08E3-08FF,U+200C-200E,U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE70-FE74,U+FE76-FEFC'],
        ],
    ];

    /** @var array<string, mixed>|null */
    private static ?array $values = null;
    /** @var array<string, mixed>|null for tests */
    private static ?array $override = null;
    /** @var list<string> */
    private static array $problems = [];
    private static ?string $fileName = null;

    /**
     * The style values: the defaults, then the template's style.php, then the
     * admin's Site style settings. Wrong values fall back to the default.
     *
     * @return array{colours: array<string, string>, dark_colours: array<string, ?string>, dark: string, fonts: array{latin: string, arabic: string, headings: ?string}, font_files: list<array{family: string, file: string, weight: string, range: ?string}>, radius: string, spacing: string, width: int}
     */
    public static function values(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }
        self::$problems = [];
        $raw = self::$override ?? array_replace_recursive(self::fileValues(), self::adminValues());
        $values = self::DEFAULTS;

        foreach (['colours', 'dark_colours'] as $group) {
            foreach ((array) ($raw[$group] ?? []) as $key => $colour) {
                if (!array_key_exists($key, self::DEFAULTS[$group]) && !($group === 'dark_colours' && in_array($key, ['accent', 'success', 'warning', 'danger'], true))) {
                    self::$problems[] = "$group.$key is not a style colour";
                    continue;
                }
                if ($colour === null || $colour === '') { continue; }
                if (!Colour::isHex($colour)) {
                    self::$problems[] = "$group.$key must be a colour like #0A7FA8";
                    continue;
                }
                $values[$group][$key] = strtoupper(trim((string) $colour));
            }
        }
        $dark = $raw['dark'] ?? 'switch';
        $values['dark'] = in_array($dark, self::DARK_MODES, true) ? $dark : self::wrong('dark', 'switch', self::DARK_MODES);
        foreach (['latin', 'arabic', 'headings'] as $script) {
            $font = $raw['fonts'][$script] ?? null;
            if (is_string($font) && preg_match('/^[A-Za-z0-9 _-]{1,60}$/', $font) === 1) {
                $values['fonts'][$script] = trim($font);
            } elseif ($font !== null && $font !== '') {
                self::$problems[] = "fonts.$script must be a font family name";
            }
        }
        $values['font_files'] = [];
        foreach ((array) ($raw['font_files'] ?? []) as $font) {
            if (!is_array($font) || !is_string($font['family'] ?? null) || !is_string($font['file'] ?? null) || preg_match('/^[A-Za-z0-9 _-]{1,60}$/', $font['family']) !== 1 || preg_match('#^[A-Za-z0-9_./-]+\.(woff2|woff|ttf|otf)$#', $font['file']) !== 1 || str_contains($font['file'], '..')) {
                self::$problems[] = 'font_files: each font needs a family and a file like fonts/name.woff2';
                continue;
            }
            $range = is_string($font['range'] ?? null) && preg_match('/^U\+[0-9A-Fa-f?, U+-]+$/', $font['range']) === 1 ? $font['range'] : null;
            $weight = is_string($font['weight'] ?? null) && preg_match('/^\d{3}( \d{3})?$/', $font['weight']) === 1 ? $font['weight'] : '400';
            $values['font_files'][] = ['family' => $font['family'], 'file' => $font['file'], 'weight' => $weight, 'range' => $range];
        }
        $radius = $raw['radius'] ?? 'small';
        $values['radius'] = isset(self::RADII[$radius]) ? $radius : self::wrong('radius', 'small', array_keys(self::RADII));
        $spacing = $raw['spacing'] ?? 'comfortable';
        $values['spacing'] = isset(self::SPACINGS[$spacing]) ? $spacing : self::wrong('spacing', 'comfortable', array_keys(self::SPACINGS));
        $width = $raw['width'] ?? 1200;
        $values['width'] = is_numeric($width) && (int) $width >= 640 && (int) $width <= 2400 ? (int) $width : (int) self::wrong('width', 1200, ['a number of pixels from 640 to 2400']);
        return self::$values = $values;
    }

    /**
     * Every colour the CSS uses, light and dark, the derived ones worked out.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function palette(): array
    {
        $v = self::values();
        $c = $v['colours'];
        $light = $c + [
            'on_brand' => Colour::on($c['brand'], $c['text']),
            'on_accent' => Colour::on($c['accent'], '#06212B'),
            'brand_hover' => Colour::shift($c['brand'], -0.06),
            'accent_hover' => Colour::shift($c['accent'], 0.05),
            'link' => $c['brand'],
            'link_hover' => Colour::shift($c['brand'], -0.08),
            'band_text' => Colour::on($c['band']),
            'band_muted' => Colour::mix($c['band'], '#FFFFFF', 0.68),
            'band_line' => Colour::mix($c['band'], '#FFFFFF', 0.14),
            'focus' => Colour::withLightness($c['brand'], 0.62),
            'success_bg' => Colour::mix($c['background'], $c['success'], 0.09),
            'warning_bg' => Colour::mix($c['background'], $c['warning'], 0.09),
            'danger_bg' => Colour::mix($c['background'], $c['danger'], 0.08),
            'info_bg' => Colour::mix($c['background'], $c['brand'], 0.08),
            'scrim' => '#0B1620',
        ];

        $d = array_filter($v['dark_colours'], static fn (?string $value): bool => $value !== null);
        $background = $d['background'] ?? Colour::withLightness($c['text'], 0.17, 0.4);
        $dark = [
            'background' => $background,
            'surface' => $d['surface'] ?? Colour::shift($background, 0.03),
            'text' => $d['text'] ?? Colour::withLightness($c['text'], 0.93, 0.3),
            'muted' => $d['muted'] ?? Colour::withLightness($c['muted'], 0.74, 0.6),
            'line' => $d['line'] ?? Colour::shift($background, 0.09),
            'line_strong' => $d['line_strong'] ?? Colour::shift($background, 0.16),
            'band' => $d['band'] ?? Colour::shift($background, -0.04),
            'brand' => $d['brand'] ?? $c['accent'],
            'accent' => $d['accent'] ?? $c['accent'],
            'link' => $d['link'] ?? Colour::shift($d['brand'] ?? $c['accent'], 0.07),
            'success' => $d['success'] ?? Colour::withLightness($c['success'], 0.74),
            'warning' => $d['warning'] ?? Colour::withLightness($c['warning'], 0.78),
            'danger' => $d['danger'] ?? Colour::withLightness($c['danger'], 0.72),
        ];
        $dark += [
            'on_brand' => Colour::on($dark['brand'], '#06212B'),
            'on_accent' => Colour::on($dark['accent'], '#06212B'),
            'brand_hover' => Colour::shift($dark['brand'], 0.05),
            'accent_hover' => Colour::shift($dark['accent'], 0.05),
            'link_hover' => Colour::shift($dark['link'], 0.06),
            'band_text' => Colour::on($dark['band']),
            'band_muted' => Colour::mix($dark['band'], '#FFFFFF', 0.66),
            'band_line' => Colour::mix($dark['band'], '#FFFFFF', 0.12),
            'focus' => Colour::withLightness($dark['brand'], 0.78),
            'success_bg' => Colour::mix($dark['background'], $dark['success'], 0.12),
            'warning_bg' => Colour::mix($dark['background'], $dark['warning'], 0.12),
            'danger_bg' => Colour::mix($dark['background'], $dark['danger'], 0.12),
            'info_bg' => Colour::mix($dark['background'], $dark['brand'], 0.12),
            'scrim' => '#000000',
        ];
        return ['light' => $light, 'dark' => $dark];
    }

    /**
     * Pairs of text and background that don't reach WCAG AA (4.5 : 1 for
     * text, 3 : 1 for large text and lines that must be seen). For
     * php flo site:style --check and the admin's Site style page.
     *
     * @return list<array{mode: string, text: string, background: string, ratio: float, needed: float}>
     */
    public static function contrastProblems(): array
    {
        $pairs = [
            ['text', 'background', 4.5], ['muted', 'background', 4.5], ['text', 'surface', 4.5], ['muted', 'surface', 4.5],
            ['link', 'background', 4.5], ['on_brand', 'brand', 4.5], ['on_accent', 'accent', 4.5],
            ['band_text', 'band', 4.5], ['band_muted', 'band', 4.5], ['on_accent', 'accent', 4.5],
            ['focus', 'background', 3.0],
        ];
        $problems = [];
        foreach (self::palette() as $mode => $colours) {
            if ($mode === 'dark' && self::values()['dark'] === 'light') { continue; }
            foreach ($pairs as [$text, $background, $needed]) {
                if (!isset($colours[$text], $colours[$background])) { continue; }
                $ratio = Colour::contrast($colours[$text], $colours[$background]);
                if ($ratio + 0.005 < $needed) {
                    $problems[$mode . $text . $background] = ['mode' => $mode, 'text' => $text, 'background' => $background, 'ratio' => round($ratio, 2), 'needed' => $needed];
                }
            }
        }
        return array_values($problems);
    }

    /** @return list<string> what style.php or the admin's settings have wrong */
    public static function problems(): array
    {
        self::values();
        return self::$problems;
    }

    /** The CSS file's name: style-<16 hex>.css, from the values, the package version and the files it is made of. */
    public static function fileName(): string
    {
        if (self::$fileName !== null) {
            return self::$fileName;
        }
        $key = [Frontend::VERSION, json_encode(self::values())];
        foreach (StyleCss::sources() as $file) {
            $key[] = $file . '|' . (int) @filemtime($file) . '|' . (int) @filesize($file);
        }
        return self::$fileName = 'style-' . substr(sha1(implode("\n", $key)), 0, 16) . '.css';
    }

    /** The address of the CSS file, written now when it isn't there yet. */
    public static function url(): string
    {
        $name = self::fileName();
        $path = Frontend::cachePath() . '/' . $name;
        if (!is_file($path)) {
            // When the folder can't be written, the asset address builds the CSS on each request instead
            CacheFile::write($path, StyleCss::build());
        } elseif ((int) @filemtime($path) < time() - 86400) {
            @touch($path);
        }
        return Assets::url($name);
    }

    /** For tests: use these values instead of style.php and the admin's settings (null: stop). */
    public static function useValues(?array $values): void
    {
        self::$override = $values;
        self::reset();
    }

    public static function reset(): void
    {
        self::$values = null;
        self::$fileName = null;
    }

    /** The active template's style.php. @return array<string, mixed> */
    public static function fileValues(): array
    {
        $file = self::file();
        if ($file === null) { return []; }
        try {
            $values = (static fn (string $__file): mixed => require $__file)($file);
        } catch (\Throwable $error) {
            Log::warning('The style file ' . $file . ' failed: ' . $error->getMessage());
            return [];
        }
        return is_array($values) ? $values : [];
    }

    /** templates/<active>/style.php, else templates/default/style.php, else null. */
    public static function file(): ?string
    {
        $root = Frontend::root() . '/templates/';
        $template = self::template();
        foreach (array_unique([$template, 'default']) as $name) {
            if (is_file($root . $name . '/style.php')) {
                return $root . $name . '/style.php';
            }
        }
        return null;
    }

    /** The active template's name (ACTIVE_TEMPLATE on a page, APP_TEMPLATE in commands). */
    public static function template(): string
    {
        if (defined('ACTIVE_TEMPLATE')) {
            return Template::getActiveTemplate();
        }
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) \FloCMS\Core\Env::get('APP_TEMPLATE', 'default'));
        return $name === '' || $name === null ? 'default' : $name;
    }

    /** The admin's Settings → Site style values (flocms-admin), when saved. @return array<string, mixed> */
    private static function adminValues(): array
    {
        if (!class_exists(\FloCMS\Admin\Admin::class) || !function_exists('settings')) {
            return [];
        }
        try {
            $saved = \FloCMS\Frontend\Admin\StyleSettings::values();
        } catch (\Throwable) {
            return [];
        }
        return $saved;
    }

    /** @param list<mixed> $allowed */
    private static function wrong(string $key, mixed $default, array $allowed): mixed
    {
        self::$problems[] = $key . ' must be ' . implode(', ', array_map('strval', $allowed));
        return $default;
    }
}
