<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Admin;

use FloCMS\Admin\Admin;
use FloCMS\Admin\Extension;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Seo\Sitemap;

/**
 * What the package adds to flocms-admin (0.43+, Admin::extend(), D21), with
 * no lines in config/admin.php:
 *
 *   - every component with $block = true as a Builder block ("site_<name>"),
 *   - Settings → Site style (overrides the template's style.php),
 *   - the site's own sitemap addresses in the admin's /sitemap.xml (D12).
 */
final class AdminBridge
{
    public static function register(): void
    {
        Admin::extend(static function (Extension $panel): void {
            $blocks = [];
            foreach (Components::all() as $name => $class) {
                if ($class::isBlock()) {
                    $blocks[] = new ComponentBlock($name);
                }
            }
            $panel->blocks($blocks);
            $panel->settings([StyleSettings::page()]);
            $panel->sitemap(static fn (): array => Sitemap::entries());
        });
    }
}
