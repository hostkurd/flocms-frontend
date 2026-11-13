<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/**
 * A full-width slider for the top of a page: at most 6 slides, the first one
 * carries the page's main heading. No autoplay unless turned on (then with a
 * pause button). Keyboard, swipe, and without JavaScript the slides are a
 * stacked list.
 */
final class Slider extends Component
{
    protected static string $name = 'slider';
    protected static string $icon = 'layers';
    protected static string $category = 'media';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::list('slides', [
                Prop::image('image'),
                Prop::text('eyebrow')->translatable(),
                Prop::text('heading')->required()->translatable()->max(140),
                Prop::textarea('text')->translatable()->max(400),
                Prop::link('link'),
            ])->max(6)->min(1),
            Prop::toggle('autoplay')->default(false),
            Prop::number('interval')->default(7)->min(3)->max(20),
            Prop::select('height', ['medium' => 'Medium', 'tall' => 'Tall', 'screen' => 'Screen height'])->default('tall'),
        ];
    }

    public function data(array $props): array
    {
        return ['slider_id' => self::uid('fe-slider', $props), 'total' => count($props['slides'])];
    }
}
