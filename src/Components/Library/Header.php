<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Menus\Menus;
use FloCMS\Frontend\Style\Theme;
use FloCMS\Frontend\Support\Languages;

/**
 * The site's header: logo, main menu, language and light/dark switches, one
 * button. On phones the menu opens without JavaScript (<details>); with it,
 * it gets keyboard and focus handling.
 */
final class Header extends Component
{
    protected static string $name = 'header';
    protected static string $icon = 'menu';
    protected static string $category = 'navigation';
    protected static bool $cacheable = false;

    public function props(): array
    {
        return [
            Prop::text('menu')->default('main'),
            Prop::text('name'),
            Prop::image('logo'),
            Prop::link('button'),
            Prop::toggle('languages')->default(true),
            Prop::toggle('theme')->default(true),
            Prop::toggle('sticky')->default(false),
        ];
    }

    public function data(array $props): array
    {
        $site = Frontend::config()->site();
        $logo = $props['logo']['src'] !== '' ? $props['logo'] : null;
        if ($logo === null && $site['logo'] !== null) {
            $logo = ['src' => $site['logo'], 'alt' => '', 'width' => null, 'height' => null];
        }
        return [
            'items' => Menus::get($props['menu'] !== '' ? $props['menu'] : 'main'),
            'site_name' => $props['name'] !== '' ? $props['name'] : $site['name'],
            'logo_image' => $logo,
            'home' => Languages::url('/'),
            'show_languages' => $props['languages'] && count(Languages::all()) > 1,
            'show_theme' => $props['theme'] && Theme::hasSwitch(),
        ];
    }
}
