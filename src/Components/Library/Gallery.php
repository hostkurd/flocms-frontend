<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** Photos in a grid; with JavaScript a photo opens large, without it links to the photo. */
final class Gallery extends Component
{
    protected static string $name = 'gallery';
    protected static string $icon = 'image';
    protected static string $category = 'media';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::text('heading')->translatable(),
            Prop::list('images', [
                Prop::image('image')->required(),
                Prop::text('caption')->translatable(),
            ])->max(60),
            Prop::number('columns')->default(3)->min(2)->max(5),
            Prop::select('ratio', ['4x3' => '4:3', '1x1' => 'Square', '3x4' => 'Portrait', '16x9' => '16:9'])->default('4x3'),
        ];
    }

    public function data(array $props): array
    {
        return ['gallery_id' => self::uid('fe-gallery', $props)];
    }
}
