<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Core\HttpException;
use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Http\AssetController;
use FloCMS\Frontend\Http\StarterController;
use FloCMS\Frontend\Http\UiController;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Tests\TestCase;

final class HttpTest extends TestCase
{
    /** @param list<string> $params */
    private function controller(string $class, string $method = 'GET', array $params = [], array $post = []): object
    {
        $controller = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(\FloCMS\Core\Controller::class, 'params');
        $property->setAccessible(true);
        $property->setValue($controller, $params);
        $controller->setRequest(new Request([], $post, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []));
        return $controller;
    }

    public function testThePackagesAddressesOnTheDefaultRouteOnly(): void
    {
        self::assertSame(AssetController::class, Frontend::resolve('site-assets', 'default'));
        self::assertSame(UiController::class, Frontend::resolve('Site-UI', 'default'));
        self::assertNull(Frontend::resolve('site-assets', 'admin'));
        self::assertNull(Frontend::resolve('pages', 'default'));
    }

    public function testGeneratedFilesAndFonts(): void
    {
        $name = basename(parse_url(Style::url(), PHP_URL_PATH));
        $response = $this->controller(AssetController::class, 'GET', [$name])->file();
        self::assertSame(200, $response->status());
        self::assertStringContainsString('--fe-brand:#0a7fa8', $response->body());
        self::assertSame('public, max-age=31536000, immutable', $response->headers()['Cache-Control'] ?? null);
        Components::render('slider', ['slides' => [['heading' => 'a']]]);
        preg_match('#bundle-[a-f0-9]{16}\.js#', Assets::foot(), $m);
        self::assertStringContainsString('data-fe="slider"', $this->controller(AssetController::class, 'GET', [$m[0]])->file()->body());
        self::assertSame('font/woff2', $this->controller(AssetController::class, 'GET', ['noto-sans-arabic-v1.woff2'])->font()->headers()['Content-Type'] ?? null);
        foreach ([['file', '../config.php'], ['file', 'style-0000000000000000.css'], ['file', 'bundle-zz.js'], ['font', '../../composer.json']] as [$action, $param]) {
            try {
                $this->controller(AssetController::class, 'GET', [$param])->$action();
                self::fail($param);
            } catch (HttpException $error) {
                self::assertSame(404, $error->getCode() ?: 404, $param);
            }
        }
    }

    public function testTheStyleIsBuiltWhenTheCacheCantBeWritten(): void
    {
        \FloCMS\Core\Config::set('frontend.cache_path', '/proc/not-writable');
        try {
            Style::reset();
            $response = $this->controller(AssetController::class, 'GET', [Style::fileName()])->file();
            self::assertStringContainsString('--fe-brand', $response->body());
            self::assertSame('public, max-age=300', $response->headers()['Cache-Control'] ?? null);
        } finally {
            \FloCMS\Core\Config::set('frontend.cache_path', null);
        }
    }

    public function testTheVisitorsChoicesWithoutJavascript(): void
    {
        $response = $this->controller(UiController::class, 'POST', [], ['choice' => 'all', 'return' => '/ar/about?x=1'])->consent();
        self::assertSame(303, $response->status());
        self::assertSame('https://example.test/ar/about?x=1', $response->headers()['Location'] ?? null);
        self::assertStringContainsString('fe_consent=v1%3Astatistics%2Cmarketing; Path=/; Max-Age=15552000; SameSite=Lax; Secure', implode("\n", (array) ($response->headers()['Set-Cookie'] ?? [])));
        $response = $this->controller(UiController::class, 'POST', [], ['choice' => 'custom', 'categories' => ['marketing', 'evil'], 'return' => '//evil.example'])->consent();
        self::assertSame('https://example.test/en/', $response->headers()['Location'] ?? null, 'never another site');
        self::assertStringContainsString('fe_consent=v1%3Amarketing;', implode("\n", (array) ($response->headers()['Set-Cookie'] ?? [])));
        $response = $this->controller(UiController::class, 'POST', [], ['theme' => 'dark', 'return' => '/en/'])->theme();
        self::assertStringContainsString('fe_theme=dark; Path=/; Max-Age=31536000', implode("\n", (array) ($response->headers()['Set-Cookie'] ?? [])));
        $response = $this->controller(UiController::class, 'POST', [], ['theme' => 'auto'])->theme();
        self::assertStringContainsString('fe_theme=; Path=/; Max-Age=0', implode("\n", (array) ($response->headers()['Set-Cookie'] ?? [])));
        $this->expectException(HttpException::class);
        $this->controller(UiController::class, 'GET')->theme();
    }

    public function testTheStarterPickerIsForAdministratorsOnly(): void
    {
        $_ENV['APP_ENV'] = 'production';
        try {
            $this->controller(StarterController::class)->index();
            self::fail('a visitor');
        } catch (HttpException) {
            self::assertTrue(true);
        }
        $_ENV['APP_ENV'] = 'local';
        try {
            $html = $this->controller(StarterController::class)->index()->body();
            self::assertStringContainsString('Choose how your site starts', $html);
            self::assertSame(6, substr_count($html, 'name="starter"'));
            self::assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
            $response = $this->controller(StarterController::class, 'POST', [], ['starter' => 'landing'])->choose();
            self::assertSame('/en/', $response->headers()['Location'] ?? null);
            self::assertFileExists(ROOT . '/views/pages/index.html');
            try {
                $this->controller(StarterController::class)->index();
                self::fail('shown once only');
            } catch (HttpException) {
                self::assertTrue(true);
            }
        } finally {
            unset($_ENV['APP_ENV']);
        }
    }
}
