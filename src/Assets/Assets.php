<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Assets;

use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Style\Style;

/**
 * The CSS and JavaScript of the components on a page (D5).
 *
 * The package's own components' CSS is part of the one Site Style file, so
 * it is cached once for the whole site. The CSS of the site's and other
 * packages' components, and the JavaScript of every component, is collected
 * while the page renders and linked as one combined file per set of
 * components (versioned name, long cache). A page that needs no JavaScript
 * gets no script at all.
 *
 * site_head() links the style and the CSS of the components rendered so far
 * (the page's content); site_foot() links what the layout's own components
 * added after it, and the combined script.
 */
final class Assets
{
    /** @var array<string, true> component names used on this page, in order */
    private static array $used = [];

    /** @var array<string, true> components whose CSS is already linked */
    private static array $linked = [];

    private static bool $headPrinted = false;

    public static function used(string $name): void
    {
        self::$used[$name] = true;
    }

    /** @return list<string> */
    public static function usedNames(): array
    {
        return array_keys(self::$used);
    }

    /**
     * Components printed by flocms-admin's Builder from its cache don't
     * render again, so they are taken from the list of printed blocks
     * (site_<name>) instead.
     */
    public static function syncFromBlocks(): void
    {
        if (!class_exists(\FloCMS\Admin\Blocks\BlockRenderer::class) || !method_exists(\FloCMS\Admin\Blocks\BlockRenderer::class, 'printed')) {
            return;
        }
        $printed = array_flip(\FloCMS\Admin\Blocks\BlockRenderer::printed());
        foreach (array_keys(Components::all()) as $name) {
            if (isset($printed['site_' . str_replace('-', '_', $name)])) {
                self::used($name);
            }
        }
    }

    /** The <link> tags for the head: the Site Style file, then the page's component CSS. */
    public static function head(): string
    {
        self::syncFromBlocks();
        self::$headPrinted = true;
        $html = '<link rel="stylesheet" href="' . htmlspecialchars(Style::url(), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        return $html . self::cssLink();
    }

    /** What the end of the body needs: component CSS added after the head, and the combined script. */
    public static function foot(): string
    {
        self::syncFromBlocks();
        $html = self::$headPrinted ? self::cssLink() : '';
        $files = [];
        foreach (array_keys(self::$used) as $name) {
            $file = Components::file($name, 'js');
            if ($file !== null) { $files[$name] = $file; }
        }
        if ($files !== []) {
            $url = self::bundle($files, 'js');
            if ($url !== null) {
                $html .= '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
            }
        }
        return $html;
    }

    /** The CSS of components used since the last call that the style file doesn't hold. */
    private static function cssLink(): string
    {
        $files = [];
        foreach (array_keys(self::$used) as $name) {
            if (isset(self::$linked[$name])) { continue; }
            self::$linked[$name] = true;
            if (Components::cssInStyle($name)) { continue; }
            $file = Components::file($name, 'css');
            if ($file !== null) { $files[$name] = $file; }
        }
        if ($files === []) { return ''; }
        $url = self::bundle($files, 'css');
        return $url === null ? '' : '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }

    /**
     * Write the files as one, named by what is in it, and return its address.
     * When the cache folder can't be written, the address lists the
     * components (?c=…) and the file is built on each request instead.
     *
     * @param array<string, string> $files component name => file
     */
    public static function bundle(array $files, string $extension): ?string
    {
        $name = self::bundleName($files, $extension);
        $path = Frontend::cachePath() . '/' . $name;
        if (is_file($path)) {
            // Still in use: kept by the weekly clean-up of old files
            if ((int) @filemtime($path) < time() - 86400) { @touch($path); }
        } elseif (!CacheFile::write($path, self::bundleContents($files, $extension))) {
            return self::url($name) . '?c=' . implode(',', array_keys($files));
        }
        return self::url($name);
    }

    /** @param array<string, string> $files */
    public static function bundleName(array $files, string $extension): string
    {
        $key = [Frontend::VERSION, $extension];
        foreach ($files as $name => $file) {
            $key[] = $name . '|' . $file . '|' . (int) @filemtime($file) . '|' . (int) @filesize($file);
        }
        return 'bundle-' . substr(sha1(implode("\n", $key)), 0, 16) . '.' . $extension;
    }

    /** @param array<string, string> $files */
    public static function bundleContents(array $files, string $extension): string
    {
        $parts = [];
        foreach ($files as $component => $file) {
            $source = (string) @file_get_contents($file);
            $parts[] = $extension === 'js'
                ? "/* $component */\n;(function(){\n" . $source . "\n})();"
                : "/* $component */\n" . $source;
        }
        return implode("\n", $parts) . "\n";
    }

    /**
     * The files of a bundle from its components' names (an address with ?c=…),
     * or null when they don't make that bundle.
     *
     * @param list<string> $names
     * @return array<string, string>|null
     */
    public static function filesFor(array $names, string $extension, string $bundle): ?array
    {
        $files = [];
        foreach ($names as $name) {
            $file = Components::exists($name) ? Components::file($name, $extension) : null;
            if ($file === null) { return null; }
            $files[$name] = $file;
        }
        return $files !== [] && self::bundleName($files, $extension) === $bundle ? $files : null;
    }

    /** The address of a generated file. */
    public static function url(string $file): string
    {
        return Frontend::siteUri() . '/site-assets/file/' . $file;
    }

    /**
     * The address of one of the package's fonts, from the site's root: the
     * cached style works on every address the site answers on (http, https, www).
     */
    public static function fontUrl(string $file): string
    {
        return rtrim((string) parse_url(Frontend::siteUri(), PHP_URL_PATH), '/') . '/site-assets/font/' . $file;
    }

    public static function reset(): void
    {
        self::$used = [];
        self::$linked = [];
        self::$headPrinted = false;
    }
}
