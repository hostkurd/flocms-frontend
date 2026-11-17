<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Core\Config;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Starters\Home;
use FloCMS\Frontend\Starters\StarterSetup;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

/** 0.1.1: the home page of a new site, through skeleton 2.1's config 'home.fallback'. */
final class HomeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->write(Home::VIEW, (string) file_get_contents(dirname(__DIR__) . '/Fixtures/skeleton-welcome.html'));
        $_ENV['APP_ENV'] = 'production';
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_ENV']);
        $_SESSION = [];
        Config::set('permissions', null);
        parent::tearDown();
    }

    private function signInAsAdministrator(): void
    {
        Config::set('permissions', [3 => ['*']]);
        $_SESSION = ['admin_access' => true, 'isloggedin' => true, 'role' => 3];
    }

    public function testBootSetsTheHookButKeepsTheSitesOwn(): void
    {
        $hook = Config::get('home.fallback');
        self::assertIsCallable($hook, 'set by Frontend::boot() in the tests bootstrap');
        self::assertFalse(is_string($hook));

        $own = static fn (): ?string => null;
        Config::set('home.fallback', $own);
        $reflection = new \ReflectionProperty(Frontend::class, 'booted');
        $reflection->setValue(null, false);
        try {
            Frontend::boot();
            self::assertSame($own, Config::get('home.fallback'));
        } finally {
            Config::set('home.fallback', $hook);
        }
    }

    public function testVisitorsSeeComingSoon(): void
    {
        $response = Home::fallback();

        self::assertNotNull($response);
        self::assertSame(200, $response->status());
        $html = $response->body();
        self::assertStringContainsString('Coming soon', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex', $html);
        self::assertStringContainsString('href="https://example.test/en/admin/users/login"', $html);
        self::assertStringNotContainsString('site-starter/choose', $html);
        self::assertSame('noindex', $response->headers()['X-Robots-Tag'] ?? null);
    }

    public function testComingSoonInArabic(): void
    {
        Languages::use('ar');
        $html = (string) Home::fallback()?->body();

        self::assertStringContainsString('dir="rtl"', $html);
        self::assertStringContainsString('قريباً', $html);
        self::assertStringContainsString('https://example.test/ar/admin/users/login', $html);
    }

    public function testAdministratorsSeeThePicker(): void
    {
        $this->signInAsAdministrator();

        $html = (string) Home::fallback()?->body();

        self::assertStringContainsString('site-starter/choose', $html);
        self::assertStringContainsString('Choose how your site starts', $html);
    }

    public function testLocalDevelopmentSeesThePicker(): void
    {
        $_ENV['APP_ENV'] = 'local';

        self::assertStringContainsString('site-starter/choose', (string) Home::fallback()?->body());
    }

    public function testNothingOnceAStarterIsChosen(): void
    {
        $this->write('views/pages/index.html', '<p>Welcome</p>');
        StarterSetup::apply('blank');

        self::assertNull(Home::fallback());
    }

    public function testNothingWhenTheSiteHasItsOwnHomePage(): void
    {
        $this->write(Home::VIEW, '<h1>Our own home page</h1>');
        self::assertNull(Home::fallback());

        // A welcome page someone edited counts as their own.
        $this->write(Home::VIEW, (string) file_get_contents(dirname(__DIR__) . '/Fixtures/skeleton-welcome.html') . '<!-- edited -->');
        self::assertNull(Home::fallback());

        unlink(ROOT . '/' . Home::VIEW);
        self::assertNull(Home::fallback());
    }

    public function testWindowsLineEndingsAndTheSetupHomePage(): void
    {
        $welcome = (string) file_get_contents(dirname(__DIR__) . '/Fixtures/skeleton-welcome.html');
        $this->write(Home::VIEW, str_replace("\n", "\r\n", $welcome));
        self::assertTrue(Home::isNew(), 'a checkout with CRLF');

        $this->write(Home::VIEW, "<section style=\"max-width:40rem;margin:4rem auto;padding:0 1rem;font-family:system-ui,sans-serif\">\n"
            . "    <h1>Welcome</h1>\n"
            . "    <p>Your website starts here. Edit views/pages/index.html to change this page.</p>\n"
            . "</section>\n");
        self::assertTrue(Home::isNew(), 'what `php flo setup` writes');
    }

    public function testNothingWhenTheStarterIsSwitchedOff(): void
    {
        Frontend::useConfig(['starter' => false]);

        self::assertNull(Home::fallback());
    }
}
