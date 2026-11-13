<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** Items that open and close (<details>: works without JavaScript). */
final class Accordion extends Component
{
    protected static string $name = 'accordion';
    protected static string $icon = 'list';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::list('items', [
                Prop::text('title')->required()->translatable(),
                Prop::richText('text')->translatable(),
            ])->max(30),
            Prop::toggle('first_open')->default(false),
            Prop::toggle('one_at_a_time')->default(false),
        ];
    }

    public function data(array $props): array
    {
        return ['group' => $props['one_at_a_time'] ? self::uid('fe-accordion', $props) : null];
    }
}
