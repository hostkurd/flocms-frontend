<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Seo\Scripts;
use FloCMS\Frontend\Support\Languages;

/**
 * The cookie notice, docked to the bottom of the screen on the first visit
 * (a bar on wide screens, a sheet on phones). site_foot() shows it; the
 * footer's "Cookie settings" link opens it again.
 */
final class CookieNotice extends Component
{
    protected static string $name = 'cookie-notice';
    protected static string $icon = 'cookie';
    protected static string $category = 'site';
    protected static bool $cacheable = false;

    public function data(array $props): array
    {
        $cookies = Frontend::config()->cookies();
        $allowed = Scripts::allowed() ?? [];
        return [
            'categories' => $cookies['categories'],
            'allowed' => $allowed,
            'policy' => $cookies['policy'] !== null ? Languages::url($cookies['policy']) : null,
            'days' => $cookies['days'],
            'cookie_name' => Scripts::COOKIE,
            'cookie_version' => Scripts::VERSION,
            'return_to' => (string) preg_replace('/([?&])fe-cookies(=[^&]*)?&?/', '$1', (string) ($_SERVER['REQUEST_URI'] ?? '/')),
            'reopened' => isset($_GET['fe-cookies']),
        ];
    }
}
