<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Http;

use FloCMS\Core\Controller;
use FloCMS\Core\HttpException;
use FloCMS\Core\Http\Response;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Seo\Scripts;
use FloCMS\Frontend\Style\Theme;
use FloCMS\Frontend\Support\Languages;

/**
 * The visitor's choices without JavaScript (with it, the components' script
 * sets the same cookies and nothing is sent): POST /site-ui/consent (the
 * cookie notice) and POST /site-ui/theme (the light/dark switch). Core checks
 * the CSRF token. Both go back to the page they were sent from.
 */
final class UiController extends Controller
{
    public function consent(): Response
    {
        $this->postOnly();
        $choice = (string) $this->request->input('choice', '');
        $categories = Frontend::config()->cookies()['categories'];
        $allowed = match ($choice) {
            'all' => $categories,
            'needed' => [],
            default => array_values(array_intersect(array_filter((array) $this->request->input('categories', []), 'is_string'), $categories)),
        };
        $response = $this->back();
        return $response->appendHeader('Set-Cookie', self::cookie(Scripts::COOKIE, Scripts::cookieValue($allowed), Frontend::config()->cookies()['days']));
    }

    public function theme(): Response
    {
        $this->postOnly();
        $theme = (string) $this->request->input('theme', '');
        $response = $this->back();
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            return $response;
        }
        return $response->appendHeader('Set-Cookie', $theme === 'auto' ? self::cookie(Theme::COOKIE, '', -1) : self::cookie(Theme::COOKIE, $theme, 365));
    }

    private function postOnly(): void
    {
        if (!$this->request->isMethod('POST')) {
            throw new HttpException(405);
        }
    }

    /** Back to the page the form was on: a path of this site only, never another site. */
    private function back(): Response
    {
        return Response::make('', 303)->header('Location', self::returnUrl((string) $this->request->input('return', ''), Frontend::siteUri()))->header('Cache-Control', 'no-store');
    }

    /** The full address for a path sent by a form ("/site/ar/about" on a site in /site); the home page for anything else. */
    public static function returnUrl(string $return, string $siteUri): string
    {
        if (preg_match('#^/(?![/\\\\])[^\s\\\\]*$#', $return) !== 1) {
            return Languages::url('/');
        }
        $base = rtrim((string) parse_url($siteUri, PHP_URL_PATH), '/');
        $origin = preg_match('#^(https?://[^/]+)#i', $siteUri, $m) === 1 ? $m[1] : '';
        $inBase = $base !== '' && ($return === $base || str_starts_with($return, $base . '/') || str_starts_with($return, $base . '?'));
        return $origin . ($base !== '' && !$inBase ? $base : '') . $return;
    }

    /** A cookie the visitor's browser keeps (readable by the page's script, which sets the same one). */
    public static function cookie(string $name, string $value, int $days): string
    {
        $path = self::cookiePath();
        $secure = str_starts_with(Frontend::siteUri(), 'https://') ? '; Secure' : '';
        $expires = $days < 0 ? '; Max-Age=0' : '; Max-Age=' . ($days * 86400);
        return $name . '=' . rawurlencode($value) . '; Path=' . $path . $expires . '; SameSite=Lax' . $secure;
    }

    /** The site's folder: the visitor's choices count for the whole site. */
    public static function cookiePath(): string
    {
        $path = rtrim((string) parse_url(Frontend::siteUri(), PHP_URL_PATH), '/');
        return $path === '' ? '/' : $path . '/';
    }
}
