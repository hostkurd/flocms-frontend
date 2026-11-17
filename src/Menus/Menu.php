<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Menus;

use FloCMS\Frontend\Support\FrontendLang;
use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Support\Languages;

/**
 * A menu, defined once in config/frontend.php (D11) and shown by the header,
 * the footer and the breadcrumbs.
 *
 *     'menus' => [
 *         'main' => fn (Menu $m) => $m
 *             ->link('frontend.menu.home', '/')
 *             ->link('Services', '/services', children: fn (Menu $c) => $c->link('Hosting', '/services/hosting'))
 *             ->pages()                       // flocms-admin's pages marked "Show in menu"
 *             ->link('Account', '/account', members: true),
 *     ],
 *
 * Labels are word keys or text. The current page's item, and its parents,
 * are marked (aria-current="page").
 */
final class Menu
{
    /** @var list<array{type: string, label: string, url: string, children: ?callable, members: ?bool, icon: ?string, new_tab: bool}> */
    private array $entries = [];

    /**
     * @param ?callable(Menu): mixed $children
     * @param ?bool $members true: only signed-in visitors, false: only guests, null: everyone
     */
    public function link(string $label, string $url, ?callable $children = null, ?bool $members = null, ?string $icon = null, bool $newTab = false): self
    {
        $this->entries[] = ['type' => 'link', 'label' => $label, 'url' => $url, 'children' => $children, 'members' => $members, 'icon' => $icon, 'new_tab' => $newTab];
        return $this;
    }

    /** flocms-admin's published pages marked "Show in menu", in their order and nesting (nothing without the admin). */
    public function pages(): self
    {
        $this->entries[] = ['type' => 'pages', 'label' => '', 'url' => '', 'children' => null, 'members' => null, 'icon' => null, 'new_tab' => false];
        return $this;
    }

    /**
     * The items to show, for this visitor, on this page.
     *
     * @return list<array{label: string, url: string, current: bool, active: bool, icon: ?string, new_tab: bool, children: list<array<string, mixed>>}>
     */
    public function items(?string $currentPath = null): array
    {
        $currentPath ??= Languages::currentPath();
        $member = Menus::isMember();
        $items = [];
        foreach ($this->entries as $entry) {
            if ($entry['members'] !== null && $entry['members'] !== $member) {
                continue;
            }
            if ($entry['type'] === 'pages') {
                foreach (self::adminPages() as $page) {
                    $items[] = self::fromPage($page, $currentPath);
                }
                continue;
            }
            $children = [];
            if ($entry['children'] !== null) {
                $child = new self();
                ($entry['children'])($child);
                $children = $child->items($currentPath);
            }
            $url = Languages::url($entry['url']);
            $current = self::isCurrent($entry['url'], $currentPath);
            $active = $current;
            foreach ($children as $item) {
                if ($item['current'] || $item['active']) { $active = true; }
            }
            $items[] = [
                'label' => FrontendLang::label($entry['label']),
                'url' => Html::safeUrl($url),
                'current' => $current,
                'active' => $active,
                'icon' => $entry['icon'],
                'new_tab' => $entry['new_tab'],
                'children' => $children,
            ];
        }
        return $items;
    }

    /** Whether a menu address is the page being shown. */
    public static function isCurrent(string $url, string $currentPath): bool
    {
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\#)#i', $url) === 1) {
            $site = \FloCMS\Frontend\Frontend::siteUri();
            if ($site === '' || !str_starts_with($url, $site . '/')) {
                return false;
            }
            $url = substr($url, strlen($site));
        }
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $parts = $path === '' ? [] : explode('/', $path);
        if ($parts !== [] && in_array(strtolower($parts[0]), Languages::all(), true)) {
            array_shift($parts);
        }
        return mb_strtolower(implode('/', $parts)) === mb_strtolower(trim($currentPath, '/'));
    }

    /** @return list<array<string, mixed>> */
    private static function adminPages(): array
    {
        if (!function_exists('pages_menu')) {
            return [];
        }
        try {
            $pages = pages_menu();
        } catch (\Throwable) {
            return [];
        }
        return is_array($pages) ? $pages : [];
    }

    /**
     * @param array<string, mixed> $page
     * @return array{label: string, url: string, current: bool, active: bool, icon: ?string, new_tab: bool, children: list<array<string, mixed>>}
     */
    private static function fromPage(array $page, string $currentPath): array
    {
        $children = [];
        foreach ((array) ($page['children'] ?? []) as $child) {
            if (is_array($child)) { $children[] = self::fromPage($child, $currentPath); }
        }
        $current = (bool) ($page['current'] ?? false);
        $active = $current;
        foreach ($children as $child) {
            if ($child['current'] || $child['active']) { $active = true; }
        }
        return [
            'label' => (string) ($page['title'] ?? ''),
            'url' => Html::safeUrl((string) ($page['url'] ?? '')),
            'current' => $current,
            'active' => $active,
            'icon' => null,
            'new_tab' => false,
            'children' => $children,
        ];
    }
}
