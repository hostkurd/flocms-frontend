<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Sources;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Item;
use FloCMS\Frontend\Collections\Query;
use FloCMS\Frontend\Collections\Result;
use FloCMS\Frontend\Support\Languages;

/**
 * A source that holds its items in memory and searches, filters and orders
 * them in PHP: Markdown files, or rows a site gets from somewhere else.
 *
 *     protected function makeSource(): Source
 *     {
 *         return new ArraySource($this, fn (): array => $this->rowsFromMyApi());
 *     }
 */
class ArraySource implements Source
{
    /** @var list<Item>|null */
    private ?array $items = null;

    /** @param (callable(): list<array<string, mixed>>)|null $rows */
    public function __construct(protected Collection $collection, private $rows = null)
    {
    }

    /** @return list<array<string, mixed>> every row, visible or not */
    protected function rows(): array
    {
        return $this->rows === null ? [] : array_values(($this->rows)());
    }

    /** @return list<Item> every item, visible or not */
    final protected function items(): array
    {
        if ($this->items === null) {
            $this->items = array_map(fn (array $row): Item => new Item($this->collection, $row), $this->rows());
        }
        return $this->items;
    }

    public function forget(): void
    {
        $this->items = null;
    }

    public function all(): array
    {
        $visible = array_values(array_filter($this->items(), static fn (Item $i): bool => $i->isVisible()));
        return $this->sorted($visible, '');
    }

    public function neighbours(Item $item): array
    {
        if ($this->collection::dateField() === '') {
            return [null, null];
        }
        $all = $this->all();
        foreach ($all as $i => $other) {
            if ((string) $other->id() === (string) $item->id()) {
                return [$all[$i - 1] ?? null, $all[$i + 1] ?? null];
            }
        }
        return [null, null];
    }

    public function byId(int|string $id): ?Item
    {
        foreach ($this->items() as $item) {
            if ((string) $item->id() === (string) $id) { return $item; }
        }
        return null;
    }

    public function find(string $slug, string $lang): ?Item
    {
        $fallback = null;
        foreach ($this->items() as $item) {
            if (!$item->isVisible()) { continue; }
            if ($item->hasLanguage($lang) && $item->slug($lang) === $slug) { return $item; }
            if ($fallback === null && Filters::slugMatches($item, $slug)) { $fallback = $item; } // another language's address: the page redirects
        }
        return $fallback;
    }

    public function query(Query $query): Result
    {
        $items = $this->filtered($query, null);
        if ($query->ids !== []) {
            $order = array_flip(array_map('strval', $query->ids));
            $items = array_values(array_filter($items, static fn (Item $i): bool => isset($order[(string) $i->id()])));
            usort($items, static fn (Item $a, Item $b): int => $order[(string) $a->id()] <=> $order[(string) $b->id()]);
        } else {
            $items = $this->sorted($items, $query->sort);
        }
        $total = count($items);
        $per = $query->limit ?? $query->perPage;
        $slice = array_slice($items, ($query->page - 1) * $per, $per);
        return new Result($slice, $query->limit !== null ? min($total, $query->limit) : $total, $query->page, $per);
    }

    public function facets(Query $query, string $field): array
    {
        $counts = [];
        foreach ($this->filtered($query, $field) as $item) {
            $value = $item->raw($field);
            foreach (is_array($value) ? $value : [$value] as $v) {
                if ($v === null || $v === '') { continue; }
                $counts[(string) $v] = ($counts[(string) $v] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /** @return list<Item> the visible items the query's search and filters keep ($except: one filter left out) */
    private function filtered(Query $query, ?string $except): array
    {
        $lang = Languages::current();
        $out = [];
        $groupIds = [];
        foreach ($query->filters as $field => $values) {
            $groups = $this->collection->groupCollection($field);
            if ($groups !== null) { $groupIds[$field] = array_map('strval', Filters::groupIds($groups, $values)); }
        }
        $needle = mb_strtolower($query->search);
        foreach ($this->items() as $item) {
            if (!$item->isVisible()) { continue; }
            if ($needle !== '' && !Filters::matchesSearch($this->collection, $item, $query->search, $lang)) {
                continue;
            }
            foreach ($query->filters as $field => $values) {
                if ($field === $except) { continue; }
                $prop = $this->collection->field($field);
                $raw = $item->raw($field);
                if (isset($groupIds[$field])) {
                    if (!in_array((string) $raw, $groupIds[$field], true)) { continue 2; }
                } elseif ($prop !== null && $prop->type() === 'tags') {
                    $tags = $item->get($field, $lang);
                    if (array_intersect($values, is_array($tags) ? $tags : []) === []) { continue 2; }
                } elseif (!in_array((string) (is_scalar($raw) ? $raw : ''), $values, true)) {
                    continue 2;
                }
            }
            if ($query->range !== null && $query->rangeField !== null && $query->rangeField !== $except) {
                $price = $item->raw($query->rangeField);
                if (!is_numeric($price)) { continue; }
                if ($query->range[0] !== null && (float) $price < $query->range[0]) { continue; }
                if ($query->range[1] !== null && (float) $price > $query->range[1]) { continue; }
            }
            $out[] = $item;
        }
        return $out;
    }

    /** @param list<Item> $items @return list<Item> */
    private function sorted(array $items, string $sort): array
    {
        $sorts = $this->collection->sorts();
        [$field, $dir] = $sorts[$sort] ?? (reset($sorts) ?: ['id', 'ASC']);
        $desc = strtoupper($dir) === 'DESC';
        $lang = Languages::current();
        usort($items, static function (Item $a, Item $b) use ($field, $desc, $lang): int {
            $va = $a->raw($field);
            $vb = $b->raw($field);
            if (!is_numeric($va) && !is_numeric($vb) && (is_string($va) && str_starts_with(ltrim($va), '{') || is_string($vb) && str_starts_with(ltrim($vb), '{'))) {
                $va = $a->get($field, $lang);
                $vb = $b->get($field, $lang);
            }
            // Empty values (no price) last, whichever the direction
            $ea = $va === null || $va === '';
            $eb = $vb === null || $vb === '';
            if ($ea !== $eb) { return $ea ? 1 : -1; }
            $cmp = is_numeric($va) && is_numeric($vb) ? ((float) $va <=> (float) $vb) : strcmp((string) (is_scalar($va) ? $va : ''), (string) (is_scalar($vb) ? $vb : ''));
            if ($cmp === 0) { $cmp = (int) $a->id() <=> (int) $b->id(); }
            return $desc ? -$cmp : $cmp;
        });
        return $items;
    }
}
