<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** A heading and a row of cards (services, projects, team). */
final class Cards extends Component
{
    protected static string $name = 'cards';
    protected static string $icon = 'grid';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::text('heading')->translatable(),
            Prop::textarea('intro')->translatable(),
            Prop::list('items', [
                Prop::image('image'),
                Prop::text('heading')->required()->translatable(),
                Prop::textarea('text')->translatable(),
                Prop::link('link'),
            ])->max(12),
            Prop::number('columns')->default(3)->min(2)->max(4),
            Prop::toggle('photos')->default(true),
            Prop::select('tone', ['plain' => 'Plain', 'surface' => 'Grey row'])->default('plain'),
        ];
    }
}
