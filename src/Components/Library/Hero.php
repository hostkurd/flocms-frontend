<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** The top of a page: a big heading, a short text, one or two actions and a photo beside (split) or none (centred). */
final class Hero extends Component
{
    protected static string $name = 'hero';
    protected static string $icon = 'image';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::text('eyebrow')->translatable(),
            Prop::text('heading')->required()->translatable()->max(160),
            Prop::textarea('text')->translatable()->max(600),
            Prop::link('primary'),
            Prop::link('secondary'),
            Prop::image('image'),
            Prop::select('layout', ['split' => 'Photo beside', 'centered' => 'Centred, no photo'])->default('split'),
        ];
    }
}
