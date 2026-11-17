<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Admin\Admin;
use FloCMS\Admin\Blocks\BlockRegistry;
use FloCMS\Admin\Blocks\BlockRenderer;
use FloCMS\Frontend\Admin\ComponentBlock;
use FloCMS\Frontend\Admin\StyleSettings;
use FloCMS\Frontend\Seo\Sitemap;
use FloCMS\Frontend\Starters\Starters;
use FloCMS\Frontend\Tests\TestCase;

/** flocms-admin (0.43, a dev dependency) gets the components as blocks, the Site style page and the sitemap. */
final class AdminBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Admin::useConfig(['pages' => true], true, ROOT);
    }

    public function testComponentsMarkedAsBlocksAreBuilderBlocks(): void
    {
        $types = array_keys(array_filter(BlockRegistry::all(), static fn ($block): bool => $block instanceof ComponentBlock));
        self::assertSame(['site_hero', 'site_slider', 'site_cards', 'site_button', 'site_alert', 'site_accordion', 'site_tabs', 'site_gallery', 'site_call_to_action', 'site_pricing', 'site_faq', 'site_features'], $types);
        self::assertSame([], Admin::extension()->getProblems());
        $hero = BlockRegistry::find('site_hero');
        self::assertSame('Hero', $hero->blockLabel());
        self::assertSame('site', $hero->blockCategory());
        self::assertSame('full', $hero->defaultWidth());
        self::assertTrue($hero->cacheable());
        $names = array_keys(\FloCMS\Admin\Form\Fields\Builder::make('content')->blockForm($hero)->fields());
        foreach (['eyebrow', 'heading', 'text', 'primary_label', 'primary_url', 'secondary_label', 'secondary_url', 'image', 'layout'] as $name) {
            self::assertContains($name, $names);
        }
        $plans = \FloCMS\Admin\Form\Fields\Builder::make('content')->blockForm(BlockRegistry::find('site_pricing'))->fields()['plans'];
        self::assertInstanceOf(\FloCMS\Admin\Form\Fields\Repeater::class, $plans);
        self::assertSame(['name', 'price', 'period', 'features', 'button_label', 'button_url', 'highlight'], array_keys($plans->fields()));
    }

    public function testTheWebsiteShowsTheComponentsOwnMarkup(): void
    {
        $html = BlockRenderer::render(['en' => Starters::blocks('studio')['en']], 'en');
        self::assertStringContainsString('data-block="site_hero"', $html);
        self::assertStringContainsString('<h1>Homes designed around the way you live</h1>', $html);
        self::assertStringContainsString('<a class="fe-button fe-button--large" href="https://example.test/en/projects">See our projects</a>', $html);
        self::assertStringContainsString('class="fe-pricing__plan is-highlighted"', $html);
        self::assertStringContainsString('<span class="fe-badge fe-badge--outline fe-pricing__label">Most chosen</span>', $html);
        self::assertContains('site_faq', BlockRenderer::printed());
    }

    public function testTheBlocksAnchorBecomesTheComponentsId(): void
    {
        $html = BlockRenderer::render([['id' => 'b1', 'type' => 'site_call_to_action', 'data' => ['heading' => 'Ready?'], 'settings' => ['anchor' => 'contact']]], 'en');
        self::assertMatchesRegularExpression('/<section class="fe-section fe-section--band fe-cta" id="contact">/', $html);
    }

    public function testTheSiteStyleSettingsPage(): void
    {
        self::assertNotNull(Admin::settings()->find('site_style'));
        $fields = array_keys(StyleSettings::page()->form()->fields());
        self::assertContains('brand', $fields);
        self::assertContains('font_arabic', $fields);
        self::assertSame('#0a7fa8', StyleSettings::page()->form()->fields()['brand']->getPlaceholder());
    }

    public function testTheSitesAddressesJoinTheAdminsSitemap(): void
    {
        Sitemap::add(fn () => ['/services']);
        self::assertTrue(Sitemap::adminServes() || !\FloCMS\Admin\Pages\Pages::enabled());
        self::assertNull(Sitemap::fallback('sitemap.xml', new \FloCMS\Core\Http\Request([], [], ['REQUEST_METHOD' => 'GET'], [], [])), 'the admin serves it');
        $entries = [];
        foreach (Admin::extension()->getSitemaps() as $list) { $entries = array_merge($entries, $list()); }
        self::assertSame('https://example.test/en/services', $entries[0]['loc']);
    }
}
