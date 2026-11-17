<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Style\Theme;

/** The light/dark switch ('dark' => 'switch' in the style). Remembers the choice in a cookie; works without JavaScript. */
final class ThemeSwitch extends Component
{
    protected static string $name = 'theme-switch';
    protected static string $icon = 'moon';
    protected static string $category = 'navigation';
    protected static bool $cacheable = false;

    public function data(array $props): array
    {
        return ['chosen' => Theme::chosen(), 'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? '/'), 'cookie_path' => \FloCMS\Frontend\Http\UiController::cookiePath()];
    }
}
