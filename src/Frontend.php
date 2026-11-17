<?php
declare(strict_types=1);

namespace FloCMS\Frontend;

use FloCMS\Core\App;
use FloCMS\Core\Config;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Components\Directives;
use FloCMS\Frontend\Http\AssetController;
use FloCMS\Frontend\Http\StarterController;
use FloCMS\Frontend\Http\UiController;
use FloCMS\Frontend\Seo\Sitemap;

/**
 * The package's entry point: boot (from bootstrap.php), its settings
 * (config/frontend.php) and its folders.
 */
final class Frontend
{
    public const VERSION = '0.1.4';

    /** The package's addresses on the default route. A site controller of the same name wins. */
    public const CONTROLLERS = [
        'site-assets' => AssetController::class,
        'site-ui' => UiController::class,
        'site-starter' => StarterController::class,
    ];

    private static bool $booted = false;
    private static ?FrontendConfig $config = null;
    private static ?string $root = null;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        App::resolveControllersUsing(static fn (string $controller, string $route): ?string => self::resolve($controller, $route));
        Directives::register();
        // /sitemap.xml when flocms-admin doesn't serve it (D12)
        if (method_exists(App::class, 'fallbackUsing')) {
            App::fallbackUsing(static fn (string $path, \FloCMS\Core\Http\Request $request): ?\FloCMS\Core\Http\Response => Sitemap::fallback($path, $request));
        }
        if (class_exists(\FloCMS\Admin\Admin::class) && method_exists(\FloCMS\Admin\Admin::class, 'extend')) {
            Admin\AdminBridge::register();
        }
        // The starter picker / "coming soon" at / of a new site (skeleton 2.1+). A site's own hook wins.
        if (Config::get('home.fallback') === null) {
            Config::set('home.fallback', static fn (?\FloCMS\Core\Http\Request $request = null): ?\FloCMS\Core\Http\Response => Starters\Home::fallback($request));
        }
    }

    public static function resolve(string $controller, string $route): ?string
    {
        if ($route !== (string) Config::get('default_route', 'default')) {
            return null;
        }
        return self::CONTROLLERS[strtolower($controller)] ?? null;
    }

    public static function config(): FrontendConfig
    {
        if (self::$config === null) {
            $file = self::root() . '/config/frontend.php';
            $values = [];
            if (is_file($file)) {
                $loaded = (static fn (string $__file): mixed => require $__file)($file);
                $values = is_array($loaded) ? $loaded : [];
            }
            self::$config = new FrontendConfig($values);
        }
        return self::$config;
    }

    /** Settings for tests and commands, instead of config/frontend.php. */
    public static function useConfig(array $values, ?string $root = null): void
    {
        if ($root !== null) {
            self::$root = rtrim($root, '/\\');
        }
        self::$config = new FrontendConfig($values);
        self::forgetCaches();
    }

    public static function setRoot(?string $root): void
    {
        self::$root = $root === null ? null : rtrim($root, '/\\');
        self::$config = null;
        self::forgetCaches();
    }

    /** The site's folder (ROOT). */
    public static function root(): string
    {
        if (self::$root !== null) {
            return self::$root;
        }
        return defined('ROOT') ? rtrim((string) constant('ROOT'), '/\\') : (string) getcwd();
    }

    /** A path inside this package. */
    public static function path(string $relative = ''): string
    {
        return dirname(__DIR__) . ($relative === '' ? '' : '/' . ltrim($relative, '/'));
    }

    /** storage/cache/frontend: the generated style and the pages' component files. */
    public static function cachePath(): string
    {
        $configured = Config::get('frontend.cache_path');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/\\');
        }
        return self::root() . '/storage/cache/frontend';
    }

    /** The site's address without a trailing slash. */
    public static function siteUri(): string
    {
        return defined('SITE_URI') ? rtrim((string) constant('SITE_URI'), '/') : '';
    }

    /** The path part of the site's address ('' or e.g. '/site'), without a trailing slash. */
    public static function sitePath(): string
    {
        return rtrim((string) parse_url(self::siteUri(), PHP_URL_PATH), '/');
    }

    /**
     * When the page is being viewed on another address than APP_URL (e.g.
     * APP_URL=http://localhost on https://mysite.localhost): the APP_URL this
     * request suggests. null when they match or there is no request.
     */
    public static function suggestedAppUrl(): ?string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '' || !defined('SITE_URI') || preg_match('/^[a-z0-9.\-]+(:\d+)?$|^\[[0-9a-f:.]+\](:\d+)?$/', $host) !== 1) {
            return null;
        }
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        $scheme = $https ? 'https' : 'http';
        $host = preg_replace($https ? '/:443$/' : '/:80$/', '', $host);
        $site = parse_url(self::siteUri());
        $siteHost = strtolower((string) ($site['host'] ?? '')) . (isset($site['port']) ? ':' . $site['port'] : '');
        if (strtolower((string) ($site['scheme'] ?? '')) === $scheme && $siteHost === $host) {
            return null;
        }
        return $scheme . '://' . $host . self::sitePath();
    }

    public static function debug(): bool
    {
        $debug = \FloCMS\Core\Env::get('APP_DEBUG');
        return in_array($debug, [true, 1, '1', 'true'], true);
    }

    /** Forget everything read during this request (tests, long-running workers). */
    public static function reset(): void
    {
        self::$config = null;
        self::$root = null;
        self::forgetCaches();
    }

    private static function forgetCaches(): void
    {
        Components::reset();
        Assets\Assets::reset();
        Style\Style::reset();
        Support\FrontendLang::reset();
        Seo\Seo::reset();
        Seo\Sitemap::reset();
        Seo\Scripts::reset();
        Menus\Menus::reset();
    }
}
