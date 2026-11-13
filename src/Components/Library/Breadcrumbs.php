<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Menus\Menus;
use FloCMS\Frontend\Seo\Seo;
use FloCMS\Frontend\Support\FrontendLang;
use FloCMS\Frontend\Support\Languages;

/**
 * Where the page is: Home › Services › Hosting. Given items, or worked out
 * from the main menu. Adds BreadcrumbList JSON-LD to the page head.
 */
final class Breadcrumbs extends Component
{
    protected static string $name = 'breadcrumbs';
    protected static string $icon = 'chevron-right';
    protected static string $category = 'navigation';
    protected static bool $cacheable = false;

    public function props(): array
    {
        return [
            Prop::list('items', [Prop::text('label')->required()->translatable(), Prop::text('url')]),
            Prop::text('current')->translatable(),
        ];
    }

    public function data(array $props): array
    {
        $trail = [['label' => FrontendLang::get('menu.home'), 'url' => Languages::url('/')]];
        if ($props['items'] !== []) {
            foreach ($props['items'] as $item) {
                $trail[] = ['label' => $item['label'], 'url' => $item['url'] === '' ? '' : \FloCMS\Frontend\Support\Html::safeUrl(str_starts_with($item['url'], '/') && !str_starts_with($item['url'], '//') ? Languages::url($item['url']) : $item['url'])];
            }
        } else {
            foreach (self::fromMenu(Menus::get('main')) as $item) {
                if ($item['url'] !== $trail[0]['url']) { $trail[] = $item; } // the home page is there already
            }
        }
        if ($props['current'] !== '') {
            $trail[] = ['label' => $props['current'], 'url' => ''];
        }
        Seo::page()->breadcrumbs(array_map(static fn (array $c): array => ['label' => $c['label'], 'url' => $c['url'] !== '' ? $c['url'] : Languages::switchUrl(Languages::current())], $trail));
        return ['trail' => $trail];
    }

    /**
     * The menu items leading to the current page.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array{label: string, url: string}>
     */
    private static function fromMenu(array $items): array
    {
        foreach ($items as $item) {
            if ($item['current']) {
                return [['label' => $item['label'], 'url' => '']];
            }
            if ($item['active']) {
                return array_merge([['label' => $item['label'], 'url' => $item['url']]], self::fromMenu($item['children']));
            }
        }
        return [];
    }
}
