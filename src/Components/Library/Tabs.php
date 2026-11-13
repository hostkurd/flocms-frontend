<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** Tabs. Without JavaScript they are a list of <details>, one open; with it, a tab list with arrow keys. */
final class Tabs extends Component
{
    protected static string $name = 'tabs';
    protected static string $icon = 'columns';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::list('items', [
                Prop::text('label')->required()->translatable()->max(60),
                Prop::richText('content')->translatable(),
            ])->max(8)->min(1),
            Prop::text('label')->translatable(),
        ];
    }

    public function data(array $props): array
    {
        return ['tabs_id' => self::uid('fe-tabs', $props)];
    }
}
