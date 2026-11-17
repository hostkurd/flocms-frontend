<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Starters\StarterSetup;
use FloCMS\Frontend\Starters\Starters;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

final class StartersTest extends TestCase
{
    public function testEveryStartersTemplateRendersInEveryLanguage(): void
    {
        $texts = Starters::texts();
        self::assertSame(array_keys($texts['en']), array_keys($texts['ar']), 'every Arabic text');
        self::assertSame(array_keys($texts['en']), array_keys($texts['ku']), 'every Kurdish text');
        foreach (Starters::NAMES as $name) {
            $template = Starters::template($name);
            foreach (['en' => 'en', 'ar' => 'ar', 'ku' => 'ku'] as $lang) {
                Languages::use($lang);
                $html = $this->template($template);
                self::assertStringNotContainsString('t:', strip_tags($html), $name);
                if ($name === 'studio') {
                    self::assertStringContainsString(htmlspecialchars($texts[$lang]['h1'], ENT_QUOTES), $html, $name . ' ' . $lang);
                }
            }
        }
    }

    public function testBlocksForFlocmsAdmin(): void
    {
        $blocks = Starters::blocks('studio');
        self::assertSame(['en', 'ar', 'ku'], array_keys($blocks));
        self::assertSame(['site_hero', 'site_cards', 'site_pricing', 'site_faq', 'site_call_to_action'], array_column($blocks['en'], 'type'));
        $hero = $blocks['ar'][0]['data'];
        self::assertSame('شاهد مشاريعنا', $hero['primary_label']);
        self::assertSame('/projects', $hero['primary_url']);
        self::assertSame('1', $blocks['en'][2]['data']['plans'][1]['highlight']);
        self::assertSame('Choose this package', $blocks['en'][2]['data']['plans'][1]['button_label']);
    }

    public function testWithoutTheAdminTheStarterIsAView(): void
    {
        $this->write('views/pages/index.html', '<p>Welcome</p>');
        $result = StarterSetup::apply('slider');
        self::assertSame(['target' => 'view', 'where' => 'views/pages/index.html'], array_intersect_key($result, ['target' => 1, 'where' => 1]));
        self::assertStringContainsString("@component('slider',", (string) file_get_contents(ROOT . '/views/pages/index.html'));
        self::assertSame('<p>Welcome</p>', file_get_contents(ROOT . '/views/pages/index.welcome.html'), 'the welcome page is kept');
        self::assertSame('slider', StarterSetup::chosen()['name']);
        try {
            StarterSetup::apply('studio');
            self::fail('once only');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('already chosen (slider)', $error->getMessage());
        }
        StarterSetup::apply('blank', true);
        self::assertSame("<div class=\"fe-section\"></div>\n", file_get_contents(ROOT . '/views/pages/index.html'));
        self::assertSame('<p>Welcome</p>', file_get_contents(ROOT . '/views/pages/index.welcome.html'), 'the first copy stays');
        $this->expectExceptionMessage('There is no starter "nope"');
        StarterSetup::apply('nope', true);
    }
}
