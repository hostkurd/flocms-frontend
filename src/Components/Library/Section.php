<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** A band of the page: plain (white), surface (the grey rows) or band (the navy closing band), with an optional heading. */
final class Section extends Component
{
    protected static string $name = 'section';
    protected static string $icon = 'layers';
    protected static string $category = 'layout';

    public function props(): array
    {
        return [
            Prop::select('tone', ['plain' => 'Plain', 'surface' => 'Grey row', 'band' => 'Navy band'])->default('plain'),
            Prop::select('width', ['normal' => 'Normal', 'narrow' => 'Narrow', 'wide' => 'Wide', 'full' => 'Full width'])->default('normal'),
            Prop::select('spacing', ['normal' => 'Normal', 'tight' => 'Tight', 'flush' => 'None'])->default('normal'),
            Prop::text('eyebrow')->translatable(),
            Prop::text('heading')->translatable(),
            Prop::textarea('intro')->translatable(),
            Prop::select('level', ['2' => 'h2', '1' => 'h1', '3' => 'h3'])->default('2'),
        ];
    }

    public function data(array $props): array
    {
        $classes = ['fe-section', 'fe-section--' . $props['tone']];
        if ($props['spacing'] !== 'normal') { $classes[] = 'fe-section--' . $props['spacing']; }
        if ($props['class'] !== '') { $classes[] = $props['class']; }
        return [
            'section_class' => implode(' ', $classes),
            'container_class' => $props['width'] === 'full' ? '' : 'fe-container' . ($props['width'] === 'normal' ? '' : ' fe-container--' . $props['width']),
            'tag' => 'h' . $props['level'],
        ];
    }
}
