<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** A notice: information, success, warning or a problem. Content between the tags is its text. */
final class Alert extends Component
{
    protected static string $name = 'alert';
    protected static string $icon = 'info';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::select('tone', ['info' => 'Information', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Problem'])->default('info'),
            Prop::text('title')->translatable(),
            Prop::textarea('text')->translatable(),
            Prop::toggle('dismissible')->default(false),
        ];
    }

    public function data(array $props): array
    {
        return ['icon_name' => ['info' => 'info', 'success' => 'success', 'warning' => 'alert', 'danger' => 'error'][$props['tone']] ?? 'info'];
    }
}
