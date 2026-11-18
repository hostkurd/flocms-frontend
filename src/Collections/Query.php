<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

/**
 * What a list page asks for (D35): a page of items, a search, filters and
 * an order. Built from the address's query string, so every list works as
 * a plain link or GET form, without JavaScript.
 */
final class Query
{
    public int $page = 1;
    public int $perPage = 12;
    public string $search = '';
    /** @var array<string, list<string>> field => wanted values (a group's slugs, a select's keys, tags) */
    public array $filters = [];
    /** @var array{0: ?float, 1: ?float}|null a price field's from and to */
    public ?array $range = null;
    public ?string $rangeField = null;
    public string $sort = '';
    /** @var list<int|string> only these ids, in this order (featured items, a block's choice) */
    public array $ids = [];
    public ?int $limit = null;

    /** The query a list page's address asks for. @param array<string, mixed> $get */
    public static function fromRequest(Collection $collection, array $get): self
    {
        $q = new self();
        $q->perPage = $collection::perPage();
        $page = $get['page'] ?? 1;
        $q->page = is_numeric($page) ? max(1, min(10000, (int) $page)) : 1;
        $search = $get['q'] ?? '';
        $q->search = is_string($search) ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', $search)), 0, 100) : '';
        $sort = $get['sort'] ?? '';
        $q->sort = is_string($sort) && array_key_exists($sort, $collection->sorts()) ? $sort : '';
        foreach ($collection->fieldMap() as $name => $prop) {
            if (!$prop->isFilterable()) { continue; }
            if ($prop->type() === 'price') {
                $from = $get[$name . '_from'] ?? null;
                $to = $get[$name . '_to'] ?? null;
                $from = is_numeric($from) && is_finite((float) $from) ? min(1e12, max(0.0, (float) $from)) : null;
                $to = is_numeric($to) && is_finite((float) $to) ? min(1e12, max(0.0, (float) $to)) : null;
                if ($from !== null || $to !== null) {
                    $q->range = [$from, $to];
                    $q->rangeField = $name;
                }
                continue;
            }
            $value = $get[$name] ?? null;
            $values = is_array($value) ? $value : (is_string($value) && $value !== '' ? [$value] : []);
            $values = array_values(array_unique(array_filter(array_map(static fn ($v): string => is_scalar($v) ? mb_substr(trim((string) $v), 0, 120) : '', $values), static fn (string $v): bool => $v !== '')));
            if ($values !== []) {
                $q->filters[$name] = array_slice($values, 0, 20);
            }
        }
        return $q;
    }

    /** How many filters are on (the phone's "Filters (3)"). */
    public function filterCount(): int
    {
        $n = 0;
        foreach ($this->filters as $values) { $n += count($values); }
        return $n + ($this->range !== null ? 1 : 0);
    }

    /**
     * The query string for a link from this page: this query with $changes
     * (null removes a key). The page number is dropped unless given.
     *
     * @param array<string, mixed> $changes
     */
    public function link(string $base, array $changes = []): string
    {
        $params = [];
        if ($this->search !== '') { $params['q'] = $this->search; }
        foreach ($this->filters as $field => $values) {
            $params[$field] = count($values) === 1 ? $values[0] : $values;
        }
        if ($this->range !== null && $this->rangeField !== null) {
            if ($this->range[0] !== null) { $params[$this->rangeField . '_from'] = self::number($this->range[0]); }
            if ($this->range[1] !== null) { $params[$this->rangeField . '_to'] = self::number($this->range[1]); }
        }
        if ($this->sort !== '') { $params['sort'] = $this->sort; }
        foreach ($changes as $key => $value) {
            if ($value === null) { unset($params[$key]); } else { $params[$key] = $value; }
        }
        // ':page' stays as it is, for the pagination component to fill in
        $page = $params['page'] ?? null;
        unset($params['page']);
        $query = http_build_query($params);
        if ($page === ':page') {
            $query .= ($query === '' ? '' : '&') . 'page=:page';
        } elseif (is_numeric($page) && (int) $page > 1) {
            $query .= ($query === '' ? '' : '&') . 'page=' . (int) $page;
        }
        return $query === '' ? $base : $base . '?' . $query;
    }

    private static function number(float $n): string
    {
        return floor($n) === $n ? (string) (int) $n : (string) $n;
    }
}
