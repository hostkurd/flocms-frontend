<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components\Library;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

/** Page numbers: 1 … 4 5 6 … 12, with previous and next. "url" has :page where the number goes. */
final class Pagination extends Component
{
    protected static string $name = 'pagination';
    protected static string $icon = 'list';
    protected static string $category = 'navigation';

    public function props(): array
    {
        return [
            Prop::number('page')->default(1)->min(1),
            Prop::number('pages')->default(1)->min(1),
            Prop::text('url')->default('?page=:page'),
        ];
    }

    public function data(array $props): array
    {
        $page = (int) $props['page'];
        $pages = max(1, (int) $props['pages']);
        $page = min($page, $pages);
        $numbers = [];
        $previous = 0;
        foreach (range(1, $pages) as $n) {
            if ($n === 1 || $n === $pages || abs($n - $page) <= 1) {
                if ($previous > 0 && $n - $previous > 1) { $numbers[] = null; } // a gap: …
                $numbers[] = $n;
                $previous = $n;
            }
        }
        $link = static fn (int $n): string => \FloCMS\Frontend\Support\Html::safeUrl(str_replace(':page', (string) $n, $props['url']));
        return ['current_page' => $page, 'last' => $pages, 'numbers' => $numbers, 'link' => $link];
    }
}
