<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Core\Http\Request;
use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Components\ComponentException;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Http\AssetController;
use FloCMS\Frontend\Http\UiController;
use FloCMS\Frontend\Page;
use FloCMS\Frontend\Starters\StarterSetup;
use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Tests\TestCase;

/** The independent review's findings (0.1.0), each kept fixed. */
final class ReviewFixesTest extends TestCase
{
    private function controller(string $class, string $method = 'GET', array $params = [], array $post = [], array $get = []): object
    {
        $controller = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(\FloCMS\Core\Controller::class, 'params');
        $property->setAccessible(true);
        $property->setValue($controller, $params);
        $controller->setRequest(new Request($get, $post, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []));
        return $controller;
    }

    public function testAPageFromTheBuildersCacheKeepsItsComponentScripts(): void
    {
        \FloCMS\Admin\Admin::useConfig([], true, ROOT);
        $blocks = [['id' => 'b1', 'type' => 'site_slider', 'data' => ['slides' => [['heading' => 'One'], ['heading' => 'Two']]], 'settings' => []]];
        \FloCMS\Admin\Blocks\BlockRenderer::render($blocks, 'en');
        self::assertStringContainsString('<script src=', Assets::foot());
        // The next page view: the HTML comes from the cache, the component doesn't render
        Assets::reset();
        \FloCMS\Admin\Blocks\BlockRenderer::reset();
        $html = \FloCMS\Admin\Blocks\BlockRenderer::render($blocks, 'en');
        self::assertStringContainsString('data-fe="slider"', $html);
        self::assertSame([], Assets::usedNames(), 'from the cache');
        self::assertStringContainsString('<script src=', Assets::foot());
    }

    public function testWithoutAWritableCacheTheBundleIsBuiltOnRequest(): void
    {
        \FloCMS\Core\Config::set('frontend.cache_path', '/proc/not-writable');
        try {
            Components::render('slider', ['slides' => [['heading' => 'a']]]);
            Components::render('dialog', ['label' => 'a', 'title' => 'b']);
            preg_match('#site-assets/file/(bundle-[a-f0-9]{16}\.js)\?c=([a-z,-]+)#', Assets::foot(), $m);
            self::assertSame('slider,dialog', $m[2]);
            $body = $this->controller(AssetController::class, 'GET', [$m[1]], [], ['c' => $m[2]])->file()->body();
            self::assertStringContainsString('/* dialog */', $body);
            $this->expectException(\FloCMS\Core\HttpException::class);
            $this->controller(AssetController::class, 'GET', [$m[1]], [], ['c' => 'slider'])->file();
        } finally {
            \FloCMS\Core\Config::set('frontend.cache_path', null);
        }
    }

    public function testCleaningKeepsTextAfterAStrayClosingTag(): void
    {
        self::assertSame('<p>one</p><p>two</p>', Html::clean('<p>one</p></div><p>two</p>'));
        self::assertSame('#', Html::safeUrl('/\\evil.com'));
        self::assertSame('#', Html::safeUrl("java\tscript:alert(1)"));
    }

    public function testAnUnclosedComponentIsReported(): void
    {
        \FloCMS\Frontend\Components\Slots::start('alert', ['text' => 'x']);
        echo 'lost';
        $this->debug(true);
        $this->expectException(ComponentException::class);
        $this->expectExceptionMessage("@component('alert') has no @endcomponent");
        Page::head();
    }

    public function testLinksInBreadcrumbsAndPaginationAreSafe(): void
    {
        self::assertStringNotContainsString('javascript:', Components::render('breadcrumbs', ['items' => [['label' => 'x', 'url' => 'javascript:alert(1)']], 'current' => 'y']));
        self::assertStringNotContainsString('javascript:', Components::render('pagination', ['page' => 1, 'pages' => 3, 'url' => 'javascript:go(:page)']));
    }

    public function testBackToThePageOfASiteInAFolder(): void
    {
        self::assertSame('https://x.test/site', UiController::returnUrl('/site', 'https://x.test/site'));
        self::assertSame('https://x.test/site?a=1', UiController::returnUrl('/site?a=1', 'https://x.test/site'));
        self::assertSame('https://x.test/site/ar/about', UiController::returnUrl('/site/ar/about', 'https://x.test/site'));
        self::assertSame('https://x.test/site/ar/about', UiController::returnUrl('/ar/about', 'https://x.test/site'));
        self::assertSame('https://x.test/sitemap', UiController::returnUrl('/sitemap', 'https://x.test'));
        self::assertSame('https://example.test/en/', UiController::returnUrl('//evil.test', 'https://x.test'));
        self::assertSame('https://example.test/en/', UiController::returnUrl('/\\evil.test', 'https://x.test'));
    }

    public function testThePickerNeedsASignedInAdministrator(): void
    {
        $_ENV['APP_ENV'] = 'production';
        try {
            $_SESSION = ['admin_access' => true];
            self::assertFalse(StarterSetup::pickerAllowed(), 'an old session without isloggedin');
            \FloCMS\Core\Config::set('permissions', [0 => [], 3 => ['*']]);
            $_SESSION = ['admin_access' => true, 'isloggedin' => true, 'role' => 0];
            self::assertFalse(StarterSetup::pickerAllowed(), 'a role without settings.manage');
            $_SESSION['role'] = 3;
            self::assertTrue(StarterSetup::pickerAllowed());
        } finally {
            $_SESSION = [];
            unset($_ENV['APP_ENV']);
            \FloCMS\Core\Config::set('permissions', null);
        }
    }

    public function testIdsComeFromTheContent(): void
    {
        $one = Components::render('tabs', ['items' => [['label' => 'A', 'content' => 'a']]]);
        $two = Components::render('tabs', ['items' => [['label' => 'B', 'content' => 'b']]]);
        $again = Components::render('tabs', ['items' => [['label' => 'A', 'content' => 'a']]]);
        preg_match('/id="(fe-tabs-[^"]+)"/', $one, $a);
        preg_match('/id="(fe-tabs-[^"]+)"/', $two, $b);
        preg_match('/id="(fe-tabs-[^"]+)"/', $again, $c);
        self::assertNotSame($a[1], $b[1]);
        self::assertSame($a[1] . '-2', $c[1], 'the same content twice on a page');
    }
}
