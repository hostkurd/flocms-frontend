<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Menus\Menu;
use FloCMS\Frontend\Menus\Menus;
use FloCMS\Frontend\Page;
use FloCMS\Frontend\Seo\Scripts;
use FloCMS\Frontend\Seo\Seo;
use FloCMS\Frontend\Seo\Sitemap;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

final class NavigationTest extends TestCase
{
    private function site(array $config = []): void
    {
        Frontend::useConfig($config + ['menus' => [
            'main' => fn (Menu $m) => $m->link('frontend.menu.home', '/')
                ->link('Services', '/services', children: fn (Menu $c) => $c->link('Hosting', '/services/hosting'))
                ->link('Account', '/account', members: true)
                ->link('Sign in', '/login', members: false)
                ->link('Docs', 'https://docs.example.com'),
        ]]);
    }

    public function testLanguages(): void
    {
        self::assertSame(['en', 'ar', 'ku'], Languages::all());
        self::assertSame('https://example.test/en/services', Languages::url('/services'));
        self::assertSame('https://example.test/ar/', Languages::url('/', 'ar'));
        self::assertSame('mailto:a@b.c', Languages::url('mailto:a@b.c'));
        self::assertTrue(Languages::isRtl('ku'));
        self::assertSame('arabic', Languages::script('ku'));
        self::assertSame('latin', Languages::script('en'));
        $_SERVER['REQUEST_URI'] = '/ar/services/hosting?page=2';
        self::assertSame('services/hosting', Languages::currentPath());
        self::assertSame('https://example.test/ku/services/hosting?page=2', Languages::switchUrl('ku'));
    }

    public function testMenusMarkTheCurrentPageAndItsParents(): void
    {
        $this->site();
        $_SERVER['REQUEST_URI'] = '/en/services/hosting';
        $items = Menus::get('main');
        self::assertSame(['Home', 'Services', 'Sign in', 'Docs'], array_column($items, 'label'), 'a guest');
        self::assertTrue($items[1]['active']);
        self::assertFalse($items[1]['current']);
        self::assertTrue($items[1]['children'][0]['current']);
        self::assertSame('https://example.test/en/services/hosting', $items[1]['children'][0]['url']);
        self::assertSame('https://docs.example.com', $items[3]['url']);
        Languages::use('ar');
        Menus::reset();
        self::assertSame('الرئيسية', Menus::get('main')[0]['label']);
        self::assertSame(Menus::get('main'), Menus::get('footer'), 'the footer shows the main menu by default');
        self::assertSame([], Menus::get('missing'));
    }

    public function testTheHeaderShowsTheMenu(): void
    {
        $this->site();
        $_SERVER['REQUEST_URI'] = '/en/services';
        $html = Components::render('header', ['button' => ['url' => '/contact', 'label' => 'Contact us']]);
        self::assertStringContainsString('<a class="fe-skip" href="#main">Skip to the content</a>', $html);
        self::assertStringContainsString('<summary class="fe-header__link is-active">Services', $html);
        self::assertStringContainsString('href="https://example.test/en/services" aria-current="page">Services</a>', $html);
        self::assertStringContainsString('<a class="fe-button fe-header__cta" href="https://example.test/en/contact">Contact us</a>', $html);
        self::assertStringContainsString('hreflang="ku"', $html, 'the language switcher');
        self::assertStringContainsString('name="theme" value="dark"', $html, 'the light/dark switch');
    }

    public function testThePageHead(): void
    {
        $this->site(['site' => ['name' => 'FloCMS', 'description' => 'Homes', 'logo' => '/img/logo.svg', 'social' => ['instagram' => 'https://instagram.com/flocms', 'bad' => 'javascript:x']]]);
        $_SERVER['REQUEST_URI'] = '/en/services?utm_source=x';
        Seo::title('Our services')->description("Design <b>and</b>\n building")->type('article');
        $head = Page::head();
        self::assertStringContainsString('<title>Our services – FloCMS</title>', $head);
        self::assertStringContainsString('<meta name="description" content="Design and building">', $head);
        self::assertStringContainsString('<link rel="canonical" href="https://example.test/en/services">', $head);
        self::assertStringContainsString('<link rel="alternate" hreflang="ar" href="https://example.test/ar/services">', $head);
        self::assertStringContainsString('<link rel="alternate" hreflang="x-default" href="https://example.test/en/services">', $head);
        self::assertStringContainsString('<meta property="og:image" content="https://example.test/img/logo.svg">', $head);
        self::assertStringContainsString('"sameAs":["https://instagram.com/flocms"]', $head);
        self::assertStringContainsString('<meta name="theme-color" content="#0B0F14" media="(prefers-color-scheme: dark)">', $head);
        Seo::page()->noindex();
        $head = Page::head();
        self::assertStringContainsString('<meta name="robots" content="noindex, follow">', $head);
        self::assertStringNotContainsString('hreflang', $head);
        Languages::use('ku');
        $_COOKIE['fe_theme'] = 'dark';
        self::assertSame("<!doctype html>\n<html lang=\"ku\" dir=\"rtl\" class=\"fe-no-js fe-script-arabic\" data-theme=\"dark\">", Page::html());
    }

    public function testJsonLdIsSafeInsideAScriptTag(): void
    {
        Frontend::useConfig(['site' => ['name' => '</script><script>alert(1)</script>']]);
        $head = Seo::tags();
        self::assertStringNotContainsString('</script><script>alert', $head);
        self::assertStringContainsString('</script>', $head);
    }

    public function testBreadcrumbsFromTheMenuAddJsonLd(): void
    {
        $this->site();
        $_SERVER['REQUEST_URI'] = '/en/services/hosting';
        $html = Components::render('breadcrumbs');
        self::assertStringContainsString('<a href="https://example.test/en/">Home</a>', $html);
        self::assertStringContainsString('<a href="https://example.test/en/services">Services</a>', $html);
        self::assertStringContainsString('<span aria-current="page">Hosting</span>', $html);
        self::assertStringContainsString('"@type":"BreadcrumbList"', Seo::tags());
    }

    public function testScriptsWaitForConsent(): void
    {
        site_script('statistics', 'https://stats.example.com/s.js');
        site_script('needed', 'console.log(1)');
        self::assertTrue(Scripts::showNotice(), 'auto: there is a script that needs consent');
        $html = Scripts::html();
        self::assertStringContainsString('<template data-fe-consent="statistics"><script src="https://stats.example.com/s.js" defer></script></template>', $html);
        self::assertStringContainsString('<script>console.log(1)</script>', $html);
        $_COOKIE['fe_consent'] = 'v1:statistics';
        self::assertSame(['statistics'], Scripts::allowed());
        self::assertFalse(Scripts::showNotice());
        self::assertStringContainsString("<script src=\"https://stats.example.com/s.js\" defer></script>\n", Scripts::html());
        $_COOKIE['fe_consent'] = 'v0:statistics';
        self::assertNull(Scripts::allowed(), 'asked again when the categories change');
        $_COOKIE['fe_consent'] = 'v1:';
        $_GET['fe-cookies'] = '1';
        self::assertTrue(Scripts::showNotice(), 'the footer link opens it again');
        $foot = Page::foot();
        self::assertStringContainsString('data-fe="cookie-notice"', $foot);
        self::assertStringContainsString('<details class="fe-cookie__settings" open', $foot);
        $this->expectException(\InvalidArgumentException::class);
        site_script('ads', 'x');
    }

    public function testTheNoticeIsOffWithoutScriptsUnlessTurnedOn(): void
    {
        self::assertFalse(Scripts::showNotice());
        Frontend::useConfig(['cookies' => ['notice' => 'on']]);
        self::assertTrue(Scripts::showNotice());
        Frontend::useConfig(['cookies' => ['notice' => 'off']]);
        site_script('marketing', 'x()');
        self::assertFalse(Scripts::showNotice());
    }

    public function testTheSitemap(): void
    {
        Sitemap::add(fn () => [['loc' => '/services', 'lastmod' => '2026-11-01', 'alternates' => true], 'https://example.test/en/about', ['loc' => 'javascript:x'], 'nope']);
        Sitemap::add(fn () => throw new \RuntimeException('broken list'));
        $entries = Sitemap::entries();
        self::assertSame('https://example.test/en/services', $entries[0]['loc']);
        self::assertSame('https://example.test/ku/services', $entries[0]['alternates']['ku']);
        $xml = Sitemap::xml();
        self::assertStringContainsString("<loc>https://example.test/en/services</loc>\n    <lastmod>2026-11-01</lastmod>", $xml);
        self::assertStringContainsString('<xhtml:link rel="alternate" hreflang="ar" href="https://example.test/ar/services"/>', $xml);
        self::assertStringNotContainsString('javascript', $xml);
        self::assertSame(2, substr_count($xml, '<url>'));
        $request = new \FloCMS\Core\Http\Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sitemap.xml'], [], []);
        self::assertSame(200, Sitemap::fallback('sitemap.xml', $request)?->status());
        self::assertNull(Sitemap::fallback('other', $request));
    }
}
