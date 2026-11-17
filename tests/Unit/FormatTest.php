<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Format;
use FloCMS\Frontend\Tests\TestCase;

final class FormatTest extends TestCase
{
    private const DAY = '2026-11-13 14:30:00';

    /** @return array<string, array{0: bool}> */
    public static function intl(): array
    {
        return ['with intl' => [true], 'own tables' => [false]];
    }

    /** @dataProvider intl */
    #[\PHPUnit\Framework\Attributes\DataProvider('intl')]
    public function testNumbersAndPrices(bool $intl): void
    {
        if ($intl && !class_exists(\NumberFormatter::class)) { self::markTestSkipped('PHP without intl'); }
        Format::useIntl($intl);
        self::assertSame('2,400', Format::number(2400, 0, 'en'));
        self::assertSame('2,400.50', Format::number(2400.5, 2, 'ar'));
        self::assertSame('$2,400', Format::money(2400, 'USD', null, 'en'));
        self::assertSame('2,400 $', Format::money(2400, 'USD', null, 'ar'), 'after the number in Arabic and Kurdish');
        self::assertSame('2,400.50 $', Format::money(2400.5, 'USD', null, 'ku'));
        self::assertSame('25,000 د.ع', Format::money(25000, 'IQD', null, 'ar'));
        Frontend::useConfig(['digits' => ['ku' => 'arabic']]);
        Format::useIntl($intl);
        self::assertSame('٢٬٤٠٠', Format::number(2400, 0, 'ku'));
        self::assertSame('2,400', Format::number(2400, 0, 'ar'), 'Latin digits by default');
    }

    /** @dataProvider intl */
    #[\PHPUnit\Framework\Attributes\DataProvider('intl')]
    public function testDates(bool $intl): void
    {
        if ($intl && !class_exists(\NumberFormatter::class)) { self::markTestSkipped('PHP without intl'); }
        Format::useIntl($intl);
        self::assertSame('13/11/2026', Format::date(self::DAY, 'short', 'en'));
        self::assertMatchesRegularExpression('/13.*November.*2026|November 13, 2026/', Format::date(self::DAY, 'long', 'en'));
        self::assertStringContainsString('تشرين الثاني', Format::date(self::DAY, 'long', 'ar'));
        self::assertStringContainsString('تشرینی دووەم', Format::date(self::DAY, 'long', 'ku'));
        self::assertSame('14:30', Format::time(self::DAY, 'ar'));
        self::assertSame('', Format::date('not a date'));
        self::assertSame(date('c', strtotime(self::DAY)), Format::iso(self::DAY));
    }

    public function testOwnTablesExactly(): void
    {
        Format::useIntl(false);
        self::assertSame('13 November 2026', Format::date(self::DAY, 'long', 'en'));
        self::assertSame('13 Nov 2026', Format::date(self::DAY, 'medium', 'en'));
        self::assertSame('الجمعة، 13 تشرين الثاني 2026', Format::date(self::DAY, 'full', 'ar'));
        Frontend::useConfig(['digits' => ['ar' => 'arabic']]);
        Format::useIntl(false);
        self::assertSame('١٣ تشرين الثاني ٢٠٢٦', Format::date(self::DAY, 'long', 'ar'));
        self::assertSame('2,400.5', Format::toLatinDigits('٢٬٤٠٠٫٥'));
    }
}
