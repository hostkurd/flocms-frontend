<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Starters;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Core\TemplateEngine;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Http\StarterController;

/**
 * The home page of a new site (skeleton 2.1's config 'home.fallback').
 *
 * While no starter is chosen and views/pages/index.html is still the
 * skeleton's welcome page, / shows the starter picker to administrators (and
 * on APP_ENV=local) and a short "coming soon" page to everyone else. Once a
 * starter is chosen, or the site has a home page of its own, it does nothing.
 */
final class Home
{
    /** The skeleton's home view, which PagesController::index() renders. */
    public const VIEW = 'views/pages/index.html';

    /** sha256 (LF line endings) of welcome pages the skeleton ships or writes. */
    public const WELCOME = [
        'c301b903c127616f8707fadf2d7b94a858e76e7404ec643a26bb490123739a59', // skeleton 2.0 and 2.1
    ];

    /** For the skeleton's PagesController: a Response, or null to keep the site's own home page. */
    public static function fallback(?Request $request = null): ?Response
    {
        if (!self::isNew()) {
            return null;
        }
        if (StarterSetup::pickerAllowed()) {
            return Response::html(StarterController::page())->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
        }
        return Response::html(self::comingSoon())->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    /** No starter chosen yet (and the picker is on), and the home view is still the skeleton's welcome page. */
    public static function isNew(): bool
    {
        if (!Frontend::config()->starter() || StarterSetup::chosen() !== null) {
            return false;
        }
        return self::isWelcome(Frontend::root() . '/' . self::VIEW);
    }

    public static function isWelcome(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }
        $html = str_replace("\r\n", "\n", (string) file_get_contents($file));
        if (in_array(hash('sha256', $html), self::WELCOME, true)) {
            return true;
        }
        // The page `php flo setup` writes when an admin-only project becomes a website again
        return trim($html) === trim(self::setupHomeView());
    }

    public static function comingSoon(): string
    {
        return TemplateEngine::renderFile(Frontend::path('resources/views/coming-soon.html'), [
            'site' => Frontend::config()->site(),
            'sign_in' => \FloCMS\Frontend\Support\Languages::url('/admin/users/login'),
        ]);
    }

    /** What skeleton 2.x's SetupCommand::writeHomeView() writes. */
    private static function setupHomeView(): string
    {
        return "<section style=\"max-width:40rem;margin:4rem auto;padding:0 1rem;font-family:system-ui,sans-serif\">\n"
            . "    <h1>Welcome</h1>\n"
            . "    <p>Your website starts here. Edit " . self::VIEW . " to change this page.</p>\n"
            . "</section>\n";
    }
}
