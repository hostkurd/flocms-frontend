<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Menus;

use FloCMS\Core\Session;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Log;

/**
 * The site's menus from config/frontend.php. Without a 'main' menu, the main
 * menu is the home page plus flocms-admin's pages; without a 'footer' menu,
 * the footer shows the main one.
 */
final class Menus
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $items = [];

    /** @return list<array{label: string, url: string, current: bool, active: bool, icon: ?string, new_tab: bool, children: list<array<string, mixed>>}> */
    public static function get(string $name = 'main'): array
    {
        if (isset(self::$items[$name])) {
            return self::$items[$name];
        }
        $menus = Frontend::config()->menus();
        $definition = $menus[$name] ?? match ($name) {
            'main' => static fn (Menu $menu) => $menu->link('frontend.menu.home', '/')->pages(),
            'footer' => $menus['main'] ?? static fn (Menu $menu) => $menu->link('frontend.menu.home', '/')->pages(),
            default => null,
        };
        if ($definition === null) {
            Log::warning('There is no menu "' . $name . '" in config/frontend.php.');
            return self::$items[$name] = [];
        }
        $menu = new Menu();
        try {
            $definition($menu);
        } catch (\Throwable $error) {
            Log::warning('The menu "' . $name . '" failed: ' . $error->getMessage());
            return self::$items[$name] = [];
        }
        return self::$items[$name] = $menu->items();
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_values(array_unique(array_merge(['main', 'footer'], array_keys(Frontend::config()->menus()))));
    }

    /** Whether the visitor is signed in on the site. */
    public static function isMember(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_SESSION)) {
            return false;
        }
        try {
            return (bool) Session::get('isloggedin');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$items = [];
    }
}
