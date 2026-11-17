<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Support\Icons;
use FloCMS\Frontend\Support\Languages;

/** A link that looks like a button: solid, outline, or a text link with an arrow. */
final class Button extends Component
{
    protected static string $name = 'button';
    protected static string $icon = 'link';
    protected static string $category = 'content';
    protected static bool $block = true;

    public function props(): array
    {
        return [
            Prop::text('label')->required()->translatable()->max(80),
            Prop::text('url')->required(),
            Prop::select('style', ['solid' => 'Solid', 'outline' => 'Outline', 'arrow' => 'Text with an arrow'])->default('solid'),
            Prop::select('size', ['normal' => 'Normal', 'small' => 'Small', 'large' => 'Large'])->default('normal'),
            Prop::icon('icon'),
            Prop::toggle('new_tab')->default(false),
        ];
    }

    public function html(array $vars): ?string
    {
        $url = Html::safeUrl(str_starts_with($vars['url'], '/') && !str_starts_with($vars['url'], '//') ? Languages::url($vars['url']) : $vars['url']);
        $classes = $vars['style'] === 'arrow' ? ['fe-link-arrow'] : ['fe-button'];
        if ($vars['style'] === 'outline') { $classes[] = 'fe-button--outline'; }
        if ($vars['style'] !== 'arrow' && $vars['size'] !== 'normal') { $classes[] = 'fe-button--' . $vars['size']; }
        if ($vars['class'] !== '') { $classes[] = $vars['class']; }
        $icon = $vars['icon'] !== '' ? Icons::svg($vars['icon'], ['size' => 18]) : '';
        $arrow = $vars['style'] === 'arrow' ? Icons::svg('arrow-right', ['size' => 16]) : '';
        return '<a' . Html::attributes(['class' => $classes, 'href' => $url, 'id' => $vars['id'] ?: null, 'target' => $vars['new_tab'] ? '_blank' : null, 'rel' => $vars['new_tab'] ? 'noopener noreferrer' : null]) . '>'
            . $icon . '<span>' . Html::e($vars['label']) . '</span>' . $arrow . '</a>';
    }
}
