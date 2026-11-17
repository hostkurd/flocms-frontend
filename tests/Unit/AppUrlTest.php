<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Http\StarterController;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

/**
 * 0.1.2: the site works when it is viewed on another address than APP_URL
 * (SITE_URI is https://example.test in the tests).
 */
final class AppUrlTest extends TestCase
{
    private array $server = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    private function viewOn(string $host, bool $https): void
    {
        $_SERVER['HTTP_HOST'] = $host;
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['HTTPS'] = $https ? 'on' : '';
        $_SERVER['SERVER_PORT'] = $https ? '443' : '80';
    }

    public function testFilesAndFontsAreLoadedFromTheSitesRoot(): void
    {
        self::assertSame('/site-assets/file/style-abc.css', Assets::url('style-abc.css'));
        self::assertSame('/site-assets/font/x.woff2', Assets::fontUrl('x.woff2'));
    }

    public function testPathsForFormsAndRedirects(): void
    {
        self::assertSame('/en/', Languages::path('/'));
        self::assertSame('/ar/site-starter/choose', Languages::path('/site-starter/choose', 'ar'));
        self::assertSame('https://elsewhere.test/x', Languages::path('https://elsewhere.test/x'));
        self::assertSame('https://example.test/en/', Languages::url('/'), 'links keep APP_URL');
    }

    public function testTheSuggestedAppUrl(): void
    {
        $this->viewOn('example.test', true);
        self::assertNull(Frontend::suggestedAppUrl(), 'the same address');
        $this->viewOn('example.test:443', true);
        self::assertNull(Frontend::suggestedAppUrl(), 'the default port');

        $this->viewOn('example.test', false);
        self::assertSame('http://example.test', Frontend::suggestedAppUrl(), 'http instead of https');
        $this->viewOn('flocms-2.localhost', true);
        self::assertSame('https://flocms-2.localhost', Frontend::suggestedAppUrl());
        $this->viewOn('localhost:8080', false);
        self::assertSame('http://localhost:8080', Frontend::suggestedAppUrl());

        $this->viewOn('evil.test"><script>', true);
        self::assertNull(Frontend::suggestedAppUrl(), 'not a host name');
        unset($_SERVER['HTTP_HOST']);
        self::assertNull(Frontend::suggestedAppUrl(), 'no request (console)');
    }

    public function testThePickerSaysSo(): void
    {
        $this->viewOn('flocms-2.localhost', true);
        $html = StarterController::page();

        self::assertStringContainsString('APP_URL in .env is https://example.test, but you are viewing the site at https://flocms-2.localhost', $html);
        self::assertStringContainsString('action="/en/site-starter/choose"', $html);
        self::assertMatchesRegularExpression('#href="/site-assets/file/style-[a-f0-9]{16}\.css"#', $html);

        $this->viewOn('example.test', true);
        self::assertStringNotContainsString('APP_URL in .env', StarterController::page());
    }
}
