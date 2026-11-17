<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** Short points with an icon each (what's included, why choose us). */
final class Features extends Component
{
    protected static string $name = 'features';
    protected static string $icon = 'check';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::text('heading')->translatable(),
            Prop::textarea('intro')->translatable(),
            Prop::list('items', [
                Prop::icon('icon'),
                Prop::text('heading')->required()->translatable(),
                Prop::textarea('text')->translatable(),
            ])->max(12),
            Prop::number('columns')->default(3)->min(2)->max(4),
        ];
    }
}
