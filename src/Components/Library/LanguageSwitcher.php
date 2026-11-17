<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Support\Languages;

/** The page in the site's other languages: a small menu (<details>) or a plain list. */
final class LanguageSwitcher extends Component
{
    protected static string $name = 'language-switcher';
    protected static string $icon = 'globe';
    protected static string $category = 'navigation';
    protected static bool $cacheable = false;

    public function props(): array
    {
        return [Prop::select('style', ['menu' => 'Menu', 'list' => 'List'])->default('menu')];
    }

    public function data(array $props): array
    {
        $current = Languages::current();
        $languages = [];
        foreach (Languages::all() as $lang) {
            $languages[] = ['code' => $lang, 'name' => Languages::name($lang), 'url' => Languages::switchUrl($lang), 'current' => $lang === $current, 'dir' => Languages::dir($lang)];
        }
        return ['languages' => $languages, 'current_code' => $current, 'current_name' => Languages::name($current)];
    }
}
