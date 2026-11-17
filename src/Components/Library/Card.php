<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** One card: a photo, a heading, a short text and a link. Content between the tags goes under the text. */
final class Card extends Component
{
    protected static string $name = 'card';
    protected static string $icon = 'square';
    protected static string $category = 'content';

    public function props(): array
    {
        return [
            Prop::image('image'),
            Prop::text('eyebrow')->translatable(),
            Prop::text('heading')->required()->translatable(),
            Prop::textarea('text')->translatable(),
            Prop::link('link'),
            Prop::text('badge')->translatable(),
            Prop::toggle('placeholder')->default(false),
            Prop::select('ratio', ['4x3' => '4:3', '16x9' => '16:9', '1x1' => 'Square', '3x4' => 'Portrait'])->default('4x3'),
        ];
    }
}
