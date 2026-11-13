<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** Equal columns that fold into fewer on smaller screens. */
final class Grid extends Component
{
    protected static string $name = 'grid';
    protected static string $icon = 'grid';
    protected static string $category = 'layout';

    public function props(): array
    {
        return [
            Prop::number('columns')->default(3)->min(1)->max(6),
            Prop::select('gap', ['small' => 'Small', 'normal' => 'Normal', 'large' => 'Large'])->default('normal'),
        ];
    }

    public function html(array $vars): ?string
    {
        $class = trim('fe-grid fe-grid--gap-' . $vars['gap'] . ' ' . $vars['class']);
        return '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" style="--fe-columns:' . (int) $vars['columns'] . '"' . ($vars['id'] !== '' ? ' id="' . htmlspecialchars($vars['id'], ENT_QUOTES, 'UTF-8') . '"' : '') . '>' . $vars['slot'] . '</div>';
    }
}
