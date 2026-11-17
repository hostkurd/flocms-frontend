<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Style\Colour;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Style\StyleCss;
use FloCMS\Frontend\Style\Theme;
use FloCMS\Frontend\Tests\TestCase;

final class StyleTest extends TestCase
{
    public function testColourMaths(): void
    {
        foreach (['#0a7fa8', '#38bbe0', '#0c2233', '#ffffff', '#000000', '#7a3e9d'] as $hex) {
            [$l, $c, $h] = Colour::oklch($hex);
            self::assertSame($hex, Colour::fromOklch($l, $c, $h), 'round trip of ' . $hex);
        }
        self::assertEqualsWithDelta(21.0, Colour::contrast('#000000', '#FFFFFF'), 0.01);
        self::assertEqualsWithDelta(4.55, Colour::contrast('#FFFFFF', '#0A7FA8'), 0.01, 'the approved button: white on #0A7FA8');
        self::assertSame('#FFFFFF', Colour::on('#0A7FA8'));
        self::assertSame('#06212B', Colour::on('#38BBE0', '#06212B'), 'logo blue gets dark text');
        $scale = Colour::scale('#0A7FA8');
        self::assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900], array_keys($scale));
        $lightness = array_map(static fn (string $c): float => Colour::oklch($c)[0], $scale);
        $previous = 2.0;
        foreach ($lightness as $step => $value) { self::assertLessThan($previous, $value, 'step ' . $step . ' is darker'); $previous = $value; }
        foreach ($scale as $step) { self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $step); }
    }

    public function testTheDefaultsAreTheApprovedLookAndReadWell(): void
    {
        $values = Style::values();
        self::assertSame('#0A7FA8', $values['colours']['brand']);
        self::assertSame('#0C2233', $values['colours']['band']);
        self::assertSame('switch', $values['dark']);
        self::assertSame('small', $values['radius']);
        self::assertSame([], Style::contrastProblems());
        self::assertSame([], Style::problems());
        $palette = Style::palette();
        self::assertSame('#FFFFFF', $palette['light']['on_brand']);
        self::assertSame('#38BBE0', $palette['dark']['brand']);
        self::assertSame('#06212B', $palette['dark']['on_brand']);
    }

    public function testTheTemplatesStyleFileAndWrongValues(): void
    {
        $this->write('templates/default/style.php', "<?php return ['colours' => ['brand' => '#7a3e9d', 'nope' => '#fff', 'text' => 'red'], 'dark' => 'night', 'radius' => 'large', 'width' => 99, 'fonts' => ['latin' => 'Inter; }']];");
        Style::reset();
        $values = Style::values();
        self::assertSame('#7A3E9D', $values['colours']['brand']);
        self::assertSame('#0E1A24', $values['colours']['text'], 'a wrong colour keeps the default');
        self::assertSame('large', $values['radius']);
        self::assertSame('switch', $values['dark']);
        self::assertSame(1200, $values['width']);
        self::assertSame('Instrument Sans', $values['fonts']['latin']);
        self::assertSame([
            'colours.nope is not a style colour', 'colours.text must be a colour like #0A7FA8', 'dark must be auto, light, dark, switch',
            'fonts.latin must be a font family name', 'width must be a number of pixels from 640 to 2400',
        ], Style::problems());
    }

    public function testContrastProblemsAreReported(): void
    {
        Style::useValues(['colours' => ['brand' => '#7FD3F0', 'muted' => '#AAB2BA']]);
        $problems = array_map(static fn (array $p): string => $p['mode'] . ' ' . $p['text'] . '/' . $p['background'], Style::contrastProblems());
        self::assertContains('light muted/background', $problems);
        self::assertContains('light link/background', $problems);
        self::assertNotContains('light on_brand/brand', $problems, 'the text on a light button turns dark');
    }

    public function testTheCss(): void
    {
        $css = StyleCss::build();
        self::assertStringContainsString(':root{--fe-brand:#0a7fa8;', $css);
        self::assertStringContainsString('--fe-brand-500:', $css);
        self::assertStringContainsString('--fe-radius:4px;', $css);
        self::assertStringContainsString('@font-face{font-family:"Noto Sans Arabic";', $css);
        self::assertStringContainsString('src:url(/site-assets/font/noto-sans-arabic-v1.woff2)', $css);
        self::assertStringContainsString('@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--fe-background:#0b0f14;', $css);
        self::assertMatchesRegularExpression('/:root\[data-theme="dark"\]\{[^}]*--fe-brand:#38bbe0;[^}]*--fe-on-brand:#06212b;/', $css);
        self::assertStringContainsString(':root[data-theme="dark"]{', $css);
        self::assertStringContainsString('.fe-script-arabic{--fe-font:"Noto Sans Arabic","Instrument Sans"', $css);
        self::assertStringContainsString('html:root{--flo-brand:var(--fe-brand);', $css, 'flocms-admin\'s blocks follow the style');
        self::assertStringContainsString('/* slider */', $css);
        self::assertDoesNotMatchRegularExpression('/margin-left|margin-right|padding-left|padding-right|\bleft:|\bright:|text-align:(left|right)/', (string) preg_replace('#/\*.*?\*/#s', '', $css), 'logical properties only');

        Style::useValues(['dark' => 'light']);
        self::assertStringNotContainsString(':root:not([data-theme="light"]){--fe-', StyleCss::build());
        Style::useValues(['dark' => 'dark']);
        self::assertStringContainsString('color-scheme:dark}', StyleCss::build());
    }

    public function testTheFileNameFollowsTheValuesAndIsWritten(): void
    {
        $first = Style::fileName();
        self::assertMatchesRegularExpression('/^style-[a-f0-9]{16}\.css$/', $first);
        self::assertSame('https://example.test/site-assets/file/' . $first, Style::url());
        self::assertFileExists(ROOT . '/storage/cache/frontend/' . $first);
        Style::useValues(['colours' => ['brand' => '#7a3e9d']]);
        self::assertNotSame($first, Style::fileName());
    }

    public function testTheVisitorsThemeChoice(): void
    {
        self::assertNull(Theme::chosen());
        $_COOKIE['fe_theme'] = 'dark';
        self::assertSame('dark', Theme::chosen());
        $_COOKIE['fe_theme'] = 'purple';
        self::assertNull(Theme::chosen());
        Style::useValues(['dark' => 'light']);
        $_COOKIE['fe_theme'] = 'dark';
        self::assertNull(Theme::chosen(), 'a style that is always light has no choice');
        self::assertFalse(Theme::hasSwitch());
    }
}
