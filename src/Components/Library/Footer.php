<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Menus\Menus;
use FloCMS\Frontend\Seo\Scripts;
use FloCMS\Frontend\Support\FrontendLang;
use FloCMS\Frontend\Support\Languages;

/** The site's footer on the navy band: about, menu, contact, social links and the cookie settings link. */
final class Footer extends Component
{
    protected static string $name = 'footer';
    protected static string $icon = 'columns';
    protected static string $category = 'navigation';
    protected static bool $cacheable = false;

    public function props(): array
    {
        return [
            Prop::text('menu')->default('footer'),
            Prop::textarea('about')->translatable(),
            Prop::toggle('contact')->default(true),
            Prop::text('copyright')->translatable(),
            Prop::toggle('compact')->default(false),
        ];
    }

    public function data(array $props): array
    {
        $site = Frontend::config()->site();
        $cookies = Frontend::config()->cookies();
        return [
            'items' => Menus::get($props['menu'] !== '' ? $props['menu'] : 'footer'),
            'site' => $site,
            'about_text' => $props['about'] !== '' ? $props['about'] : $site['description'],
            'copyright_text' => $props['copyright'] !== '' ? $props['copyright'] : FrontendLang::get('footer.copyright', ['year' => date('Y'), 'name' => $site['name']]),
            'home' => Languages::url('/'),
            'cookie_link' => $cookies['notice'] !== 'off' && ($cookies['notice'] === 'on' || Scripts::needsConsent()),
            'policy' => $cookies['policy'] !== null ? Languages::url($cookies['policy']) : null,
        ];
    }
}
