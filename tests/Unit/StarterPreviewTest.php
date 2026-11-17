<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Core\HttpException;
use FloCMS\Frontend\Http\AssetController;
use FloCMS\Frontend\Http\StarterController;
use FloCMS\Frontend\Starters\Starters;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

/** 0.1.2: the starter picker shows a picture of each starter's first screen. */
final class StarterPreviewTest extends TestCase
{
    public function testEveryStarterHasAPreviewInEveryLanguage(): void
    {
        foreach (Starters::NAMES as $name) {
            foreach (['en', 'ar', 'ku'] as $lang) {
                $file = dirname(__DIR__, 2) . '/resources/starters/previews/' . $name . '-' . $lang . '-v1.webp';
                self::assertFileExists($file);
                self::assertSame([720, 540], array_slice((array) getimagesize($file), 0, 2), $file);
            }
        }
    }

    public function testThePickerShowsThemInThePagesLanguage(): void
    {
        $html = StarterController::page();
        self::assertStringContainsString('<img src="/site-assets/preview/studio-en-v1.webp" width="720" height="540" alt=""', $html);
        self::assertSame(count(Starters::NAMES), substr_count($html, '/site-assets/preview/'));

        Languages::use('ku');
        self::assertStringContainsString('/site-assets/preview/journal-ku-v1.webp', StarterController::page());
    }

    public function testTheyAreServed(): void
    {
        $response = $this->controller(AssetController::class, 'GET', ['slider-ar-v1.webp'])->preview();
        self::assertSame('image/webp', $response->headers()['Content-Type'] ?? null);
        self::assertStringStartsWith('RIFF', $response->body());

        foreach (['../../composer.json', 'studio-de-v1.webp', 'studio-en-v1.png', 'nope-en-v1.webp'] as $name) {
            try {
                $this->controller(AssetController::class, 'GET', [$name])->preview();
                self::fail($name . ' was served');
            } catch (HttpException $error) {
                self::assertSame(404, $error->status, $name);
            }
        }
    }
}
