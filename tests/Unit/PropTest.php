<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

final class PropTest extends TestCase
{
    public function testTextIsTrimmedOnOneLineAndCut(): void
    {
        self::assertSame('Hello world', Prop::text('a')->clean("  Hello \n world "));
        self::assertSame("a\nb", Prop::textarea('a')->clean("a\nb"));
        self::assertSame('abc', Prop::text('a')->max(3)->clean('abcdef'));
        self::assertSame('', Prop::text('a')->clean(['x']));
        self::assertSame('Hi', Prop::text('a')->default('Hi')->clean(null));
    }

    public function testTranslatableValuesPickThePagesLanguageThenTheDefault(): void
    {
        $prop = Prop::text('heading')->translatable();
        $value = ['en' => 'Home', 'ar' => 'الرئيسية'];
        self::assertSame('Home', $prop->clean($value));
        Languages::use('ar');
        self::assertSame('الرئيسية', $prop->clean($value));
        Languages::use('ku');
        self::assertSame('Home', $prop->clean($value), 'the default language stands in');
        self::assertSame('Only', $prop->clean(['ar' => 'Only']), 'then the first one given');
        self::assertSame('Plain', $prop->clean('Plain'));
        self::assertSame('سەرەکی', Prop::text('x')->clean(['en' => 'Home', 'ku' => 'سەرەکی']), 'a language map works without translatable() too');
    }

    public function testNumbersTogglesAndSelects(): void
    {
        self::assertSame(4, Prop::number('n')->max(4)->clean('9'));
        self::assertSame(1, Prop::number('n')->min(1)->clean(-3));
        self::assertSame(2.5, Prop::number('n')->clean('2.5'));
        self::assertNull(Prop::number('n')->clean('abc'));
        self::assertSame(3, Prop::number('n')->default(3)->clean('abc'));
        self::assertTrue(Prop::toggle('t')->clean('1'));
        self::assertFalse(Prop::toggle('t')->clean(null));
        $select = Prop::select('tone', ['plain' => 'Plain', 'band' => 'Band'])->default('plain');
        self::assertSame('band', $select->clean('band'));
        self::assertSame('plain', $select->clean('nope'));
        self::assertSame('the prop "tone" must be one of: plain, band', $select->problem('nope'));
    }

    public function testLinksGetThePagesLanguageAndSafeAddresses(): void
    {
        self::assertSame(['url' => 'https://example.test/en/contact', 'label' => 'Contact', 'new_tab' => false], Prop::link('l')->clean(['url' => '/contact', 'label' => 'Contact']));
        Languages::use('ar');
        self::assertSame('https://example.test/ar/contact', Prop::link('l')->clean('/contact')['url']);
        self::assertSame('#', Prop::link('l')->clean('javascript:alert(1)')['url']);
        self::assertSame('mailto:a@b.c', Prop::link('l')->clean('mailto:a@b.c')['url']);
        self::assertSame('اتصل', Prop::link('l')->clean(['url' => '/c', 'label' => ['en' => 'Call', 'ar' => 'اتصل']])['label']);
    }

    public function testImages(): void
    {
        self::assertSame(['src' => '/img/a.jpg', 'alt' => 'A', 'width' => 400, 'height' => null], Prop::image('i')->clean(['src' => '/img/a.jpg', 'alt' => 'A', 'width' => '400']));
        self::assertSame('https://x.test/a.png', Prop::image('i')->clean('https://x.test/a.png')['src']);
        self::assertSame('', Prop::image('i')->clean('javascript:x')['src']);
        self::assertTrue(Prop::image('i')->isEmpty(Prop::image('i')->clean(null)));
    }

    public function testRichTextIsCleanedWithAnAllowList(): void
    {
        $html = Prop::richText('body')->clean('<p onclick="x()">Hi <strong>there</strong><script>alert(1)</script><a href="javascript:x" target="_blank">l</a><iframe src="//x"></iframe><span style="color:red">s</span></p>');
        self::assertSame('<p>Hi <strong>there</strong><a href="#" target="_blank" rel="noopener noreferrer">l</a><span>s</span></p>', $html);
    }

    public function testListsCleanEachItemAndKeepTheMost(): void
    {
        $prop = Prop::list('items', [Prop::text('title')->required(), Prop::number('n')])->max(2);
        self::assertSame([['title' => 'A', 'n' => 1], ['title' => 'B', 'n' => null]], $prop->clean([['title' => 'A', 'n' => '1'], ['title' => ' B '], ['title' => 'C'], 'not an item']));
        self::assertSame([['title' => 'A', 'n' => null]], $prop->clean('[{"title":"A"}]'), 'stored as JSON (flocms-admin)');
        self::assertSame('item 2 of "items": the prop "title" is required', $prop->problem([['title' => 'A'], ['title' => '']]));
        self::assertSame('the prop "items" needs at least 2 items', Prop::list('items', [Prop::text('t')])->min(2)->problem([['t' => 'a']]));
    }

    public function testRequiredAndNames(): void
    {
        self::assertSame('the prop "heading" is required', Prop::text('heading')->required()->problem('  '));
        self::assertNull(Prop::text('heading')->required()->problem('Hi'));
        $this->expectException(\InvalidArgumentException::class);
        Prop::text('Bad-Name');
    }
}
