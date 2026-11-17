<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** The navy closing band: a heading, a short text and one or two actions. Usually the last section before the footer. */
final class CallToAction extends Component
{
    protected static string $name = 'call-to-action';
    protected static string $icon = 'arrow-right';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::text('heading')->required()->translatable()->max(140),
            Prop::textarea('text')->translatable()->max(400),
            Prop::link('primary'),
            Prop::link('secondary'),
        ];
    }
}
