<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Components\ComponentException;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

final class ComponentsTest extends TestCase
{
    public function testEveryBuiltInComponentRendersInEachDirection(): void
    {
        self::assertCount(24, Components::all());
        self::assertSame([], Components::problems());
        $samples = ['hero' => ['heading' => 'H'], 'slider' => ['slides' => [['heading' => 'One'], ['heading' => 'Two']]], 'card' => ['heading' => 'C'],
            'button' => ['label' => 'Go', 'url' => '/go'], 'dialog' => ['label' => 'Open', 'title' => 'T'], 'call-to-action' => ['heading' => 'Ready?'],
            'tabs' => ['items' => [['label' => 'A', 'content' => '<p>a</p>']]]];
        foreach (['en', 'ar'] as $lang) {
            Languages::use($lang);
            foreach (array_keys(Components::all()) as $name) {
                $html = Components::render($name, $samples[$name] ?? []);
                self::assertIsString($html, $name);
                self::assertStringNotContainsString('<?', $html, $name);
            }
        }
    }

    public function testValuesAreEscaped(): void
    {
        $html = Components::render('hero', ['heading' => '<b>Hi</b> & "you"', 'primary' => ['url' => '/a', 'label' => '<i>x</i>']]);
        self::assertStringContainsString('<h1>&lt;b&gt;Hi&lt;/b&gt; &amp; &quot;you&quot;</h1>', $html);
        self::assertStringContainsString('&lt;i&gt;x&lt;/i&gt;</a>', $html);
        self::assertStringContainsString('href="https://example.test/en/a"', $html);
    }

    public function testWrongPropsAreErrorsInDebugAndLoggedOtherwise(): void
    {
        self::assertStringContainsString('<h1></h1>', Components::render('hero', ['nope' => 1]), 'production: rendered without');
        $this->debug(true);
        try {
            Components::render('hero', ['heading' => 'x', 'nope' => 1]);
            self::fail('an unknown prop');
        } catch (ComponentException $error) {
            self::assertSame('The component "hero" has no prop "nope".', $error->getMessage());
        }
        try {
            Components::render('hero', []);
            self::fail('a missing required prop');
        } catch (ComponentException $error) {
            self::assertSame('Component "hero": the prop "heading" is required.', $error->getMessage());
        }
        $this->expectExceptionMessage('There is no component "nothing".');
        Components::render('nothing');
    }

    public function testCommonPropsIdAndClass(): void
    {
        $html = Components::render('call-to-action', ['heading' => 'x', 'id' => 'contact-band', 'class' => 'is-special" onclick="x']);
        self::assertStringContainsString('id="contact-band"', $html);
        self::assertStringContainsString('fe-cta is-special onclickx', $html);
        self::assertStringNotContainsString('id=', Components::render('call-to-action', ['heading' => 'x', 'id' => '1 bad']));
    }

    public function testDirectivesWithSlots(): void
    {
        $html = $this->template("@component('section', ['heading' => \$title, 'tone' => 'surface'])\n<p>Inside {{ \$name }}</p>\n@slot('actions')<a href=\"/x\">All</a>@endslot\n@endcomponent\n@icon('arrow-right') {!! component('button', ['label' => 'Go', 'url' => '/go']) !!} admin@component.test", ['title' => 'Our work', 'name' => '<me>']);
        self::assertStringContainsString('<section class="fe-section fe-section--surface">', $html);
        self::assertStringContainsString('<h2 class="fe-h2">Our work</h2>', $html);
        self::assertStringContainsString('</div><a href="/x">All</a></div>', $html, 'the named slot');
        self::assertStringContainsString('<p>Inside &lt;me&gt;</p>', $html, 'the default slot');
        self::assertStringContainsString('fe-icon--flip', $html);
        self::assertStringContainsString('<span>Go</span>', $html);
        self::assertStringContainsString('admin@component.test', $html, 'an email address is not a directive');
    }

    public function testNestedComponentsInSlots(): void
    {
        $html = $this->template("@component('section')\n@component('grid', ['columns' => 2])\n@component('card', ['heading' => 'A'])@endcomponent\n@component('card', ['heading' => 'B'])@endcomponent\n@endcomponent\n@endcomponent");
        self::assertSame(1, substr_count($html, 'class="fe-grid'));
        self::assertSame(2, substr_count($html, 'class="fe-card"'));
        self::assertMatchesRegularExpression('/<div class="fe-grid[^>]*>.*fe-card.*fe-card.*<\/div>/s', $html);
    }

    public function testTheSitesFolderReplacesOrRestylesAComponent(): void
    {
        $this->write('components/hero/hero.html', '<div class="my-hero">{{ $heading }}</div>');
        self::assertSame('<div class="my-hero">Hi</div>', Components::render('hero', ['heading' => 'Hi']));
        self::assertFalse(Components::cssInStyle('hero') === false && Components::file('hero', 'css') === null);
        $this->write('components/hero/hero.css', '.my-hero{color:red}');
        Components::reset();
        self::assertFalse(Components::cssInStyle('hero'), 'the site\'s CSS is linked per page');
    }

    public function testSiteComponentClasses(): void
    {
        $this->write('components/team-members/TeamMembers.php', "<?php\nnamespace App\\Components;\nuse FloCMS\\Frontend\\Components\\{Component, Prop};\nfinal class TeamMembers extends Component {\n protected static string \$name = 'team-members';\n protected static bool \$block = true;\n public function props(): array { return [Prop::text('heading')->required()]; }\n public function data(array \$props): array { return ['upper' => strtoupper(\$props['heading'])]; }\n}\n");
        $this->write('components/team-members/team-members.html', '<p>{{ $upper }}</p>');
        $this->write('components/team-members/team-members.css', '.x{}');
        $this->write('components/team-members/team-members.js', 'var x = 1;');
        $this->write('components/broken/Broken.php', "<?php\nnamespace App\\Components;\nfinal class Wrong {}\n");
        Components::reset();
        self::assertSame('App\\Components\\TeamMembers', Components::all()['team-members']);
        self::assertSame('<p>ANNA</p>', Components::render('team-members', ['heading' => 'Anna']));
        self::assertSame(['components/broken/Broken.php must declare App\\Components\\Broken'], Components::problems());
        $head = Assets::head();
        self::assertMatchesRegularExpression('#site-assets/file/bundle-[a-f0-9]{16}\.css#', $head);
        $foot = Assets::foot();
        self::assertMatchesRegularExpression('#<script src="https://example\.test/site-assets/file/bundle-[a-f0-9]{16}\.js" defer></script>#', $foot);
        preg_match('#bundle-[a-f0-9]{16}\.js#', $foot, $m);
        self::assertStringContainsString("/* team-members */\n;(function(){\nvar x = 1;\n})();", (string) file_get_contents(Frontend::cachePath() . '/' . $m[0]));
    }

    public function testAssetsLinkOnlyWhatThePageUses(): void
    {
        Components::render('hero', ['heading' => 'x']);
        $head = Assets::head();
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="https://example\.test/site-assets/file/style-[a-f0-9]{16}\.css">#', $head);
        self::assertSame(1, substr_count($head, '<link'), 'the package\'s own CSS is in the style file');
        self::assertSame('', Assets::foot(), 'no script for a page that needs none');
        Components::render('slider', ['slides' => [['heading' => 'a']]]);
        self::assertStringContainsString('<script src=', Assets::foot());
        self::assertSame(['hero', 'slider'], Assets::usedNames());
    }
}
