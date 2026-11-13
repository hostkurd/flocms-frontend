<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** The page's width with its side padding. */
final class Container extends Component
{
    protected static string $name = 'container';
    protected static string $icon = 'square';
    protected static string $category = 'layout';

    public function props(): array
    {
        return [Prop::select('width', ['normal' => 'Normal', 'narrow' => 'Narrow', 'wide' => 'Wide'])->default('normal')];
    }

    public function html(array $vars): ?string
    {
        $class = trim('fe-container' . ($vars['width'] === 'normal' ? '' : ' fe-container--' . $vars['width']) . ' ' . $vars['class']);
        return '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' . ($vars['id'] !== '' ? ' id="' . htmlspecialchars($vars['id'], ENT_QUOTES, 'UTF-8') . '"' : '') . '>' . $vars['slot'] . '</div>';
    }
}
