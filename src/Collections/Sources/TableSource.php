<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Sources;

use FloCMS\Core\App;
use FloCMS\Core\Database;
use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Item;
use FloCMS\Frontend\Collections\Query;
use FloCMS\Frontend\Collections\Result;
use FloCMS\Frontend\Support\Languages;

/**
 * A collection's own table (D27), one column per field. Words that differ
 * per language are JSON objects by language ({"en": "…", "ar": "…"}), the
 * same as flocms-admin's Pages, so the admin edits them as they are.
 *
 * Queries use plain SQL only (LIKE, IN, BETWEEN), no JSON functions, so they
 * work the same on MySQL, MariaDB and SQLite.
 */
class TableSource implements Source
{
    /** At most this many items for all() (sitemaps, feeds, groups). */
    public const ALL_LIMIT = 5000;

    private static ?Database $db = null;
    /** @var array<string, Item|null> */
    private array $byId = [];
    /** @var list<Item>|null */
    private ?array $all = null;
    /** @var array<string, list<int|string>> */
    private array $searches = [];

    public function __construct(protected Collection $collection)
    {
    }

    /** Tests: a database of their own. */
    public static function useDatabase(?Database $db): void
    {
        self::$db = $db;
    }

    protected function db(): Database
    {
        if (self::$db !== null) {
            return self::$db;
        }
        return App::$db ?? App::db();
    }

    protected function table(): string
    {
        return $this->collection::table();
    }

    /** A query on the table, with only what visitors may see. */
    protected function visible(): Database
    {
        $q = $this->db()->table($this->table());
        if ($this->collection::hasPublishing()) {
            $now = date('Y-m-d H:i:s');
            // Published (from its date, if it has one), or Scheduled once its date has passed; until unpublished
            $q->whereRaw('((`status` = ? AND (`publish_at` IS NULL OR `publish_at` <= ?)) OR (`status` = ? AND `publish_at` IS NOT NULL AND `publish_at` <= ?)) AND (`unpublish_at` IS NULL OR `unpublish_at` > ?)', ['published', $now, 'scheduled', $now, $now]);
        }
        return $q;
    }

    public function query(Query $query): Result
    {
        $per = $query->limit ?? $query->perPage;
        $count = $this->filtered($query, null);
        $total = $count->count();
        if ($query->limit !== null) {
            $total = min($total, $query->limit);
        }
        $rows = $this->ordered($this->filtered($query, null), $query)
            ->limit($per)->offset(($query->page - 1) * $per)->get();
        $items = array_map(fn (object $row): Item => $this->item($row), $rows);
        if ($query->ids !== []) {
            $order = array_flip(array_map('strval', $query->ids));
            usort($items, static fn (Item $a, Item $b): int => ($order[(string) $a->id()] ?? 0) <=> ($order[(string) $b->id()] ?? 0));
        }
        return new Result($items, $total, $query->page, $per);
    }

    public function find(string $slug, string $lang): ?Item
    {
        $slugField = $this->collection::slugField();
        if ($slug === '' || $this->collection->field($slugField) === null) {
            return null;
        }
        $like = self::likes($slug, true);
        $rows = $this->visible()
            ->whereRaw('(`' . $slugField . '` = ? OR ' . implode(' OR ', array_fill(0, count($like), '`' . $slugField . '` LIKE ? ESCAPE \'!\'')) . ')', array_merge([$slug], $like))
            ->limit(50)->get();
        $fallback = null;
        foreach ($rows as $row) {
            $item = $this->item($row);
            if ($item->hasLanguage($lang) && $item->slug($lang) === $slug) {
                return $item;
            }
            if ($fallback === null && Filters::slugMatches($item, $slug)) { // another language's address: the page redirects
                $fallback = $item;
            }
        }
        if ($fallback !== null) {
            return $fallback;
        }
        // An item saved without a slug answers to one made from its title.
        $rows = $this->visible()->whereRaw('(`' . $slugField . '` IS NULL OR `' . $slugField . '` = ? OR `' . $slugField . '` = ?)', ['', '{}'])
            ->limit(self::ALL_LIMIT)->get();
        foreach ($rows as $row) {
            $item = $this->item($row);
            if ($item->slug($lang) === $slug || $item->slug(Languages::default()) === $slug) {
                return $item;
            }
        }
        return null;
    }

    public function byId(int|string $id): ?Item
    {
        $key = (string) $id;
        if (!array_key_exists($key, $this->byId)) {
            $row = $this->db()->table($this->table())->where('id', '=', $id)->first();
            $this->byId[$key] = $row === null ? null : $this->item($row);
        }
        return $this->byId[$key];
    }

    public function all(): array
    {
        if ($this->all === null) {
            $rows = $this->ordered($this->visible(), new Query())->limit(self::ALL_LIMIT)->get();
            $this->all = array_map(fn (object $row): Item => $this->item($row), $rows);
            foreach ($this->all as $item) {
                $this->byId[(string) $item->id()] = $item;
            }
        }
        return $this->all;
    }

    public function facets(Query $query, string $field): array
    {
        $prop = $this->collection->field($field);
        if ($prop === null || !in_array($prop->type(), ['select', 'toggle'], true)) {
            return [];
        }
        $rows = $this->filtered($query, $field)
            ->select([$field])->selectRaw('COUNT(*) AS `flo_n`')->groupBy($field)->get();
        $counts = [];
        foreach ($rows as $row) {
            $value = $row->{$field} ?? null;
            if ($value === null || $value === '') { continue; }
            $counts[(string) $value] = (int) ($row->flo_n ?? 0);
        }
        return $counts;
    }

    /**
     * The ids of visible items whose text has the words. LIKE finds the
     * candidates; each is then checked in the page's language with the
     * markup taken out, so "en" or "strong" doesn't match every row's JSON
     * or HTML (the same rule as ArraySource).
     *
     * @param list<string> $columns
     * @return list<int|string>
     */
    private function searchIds(string $search, array $columns): array
    {
        $lang = Languages::current();
        $key = $lang . '|' . $search;
        if (isset($this->searches[$key])) {
            return $this->searches[$key];
        }
        $parts = [];
        $bindings = [];
        foreach ($columns as $column) {
            foreach (self::likes($search) as $l) { $parts[] = '`' . $column . '` LIKE ? ESCAPE \'!\''; $bindings[] = $l; }
        }
        $rows = $this->visible()->select(array_values(array_unique(array_merge(['id'], $columns))))
            ->whereRaw('(' . implode(' OR ', $parts) . ')', $bindings)->limit(self::ALL_LIMIT)->get();
        $ids = [];
        foreach ($rows as $row) {
            if (Filters::matchesSearch($this->collection, $this->item($row), $search, $lang)) {
                $ids[] = $row->id;
            }
        }
        return $this->searches[$key] = $ids;
    }

    public function neighbours(Item $item): array
    {
        $field = $this->collection::dateField();
        if ($field === '') {
            return [null, null];
        }
        $column = $field === 'created_at' ? '`created_at`' : 'COALESCE(`' . $field . '`, `created_at`)';
        $value = $item->raw($field) ?? $item->raw('created_at');
        if (!is_string($value) || $value === '') {
            return [null, null];
        }
        $id = $item->id();
        $newer = $this->visible()->whereRaw('(' . $column . ' > ? OR (' . $column . ' = ? AND `id` > ?))', [$value, $value, $id])
            ->orderByRaw($column . ' ASC, `id` ASC')->limit(1)->get();
        $older = $this->visible()->whereRaw('(' . $column . ' < ? OR (' . $column . ' = ? AND `id` < ?))', [$value, $value, $id])
            ->orderByRaw($column . ' DESC, `id` DESC')->limit(1)->get();
        return [isset($newer[0]) ? $this->item($newer[0]) : null, isset($older[0]) ? $this->item($older[0]) : null];
    }

    public function forget(): void
    {
        $this->byId = [];
        $this->all = null;
        $this->searches = [];
    }

    protected function item(object $row): Item
    {
        return new Item($this->collection, (array) $row);
    }

    /** The visible rows the query's search, filters and ids keep ($except: one filter left out, for its counts). */
    protected function filtered(Query $query, ?string $except): Database
    {
        $q = $this->visible();
        if ($query->ids !== []) {
            $q->whereIn('id', $query->ids);
        }
        if ($query->search !== '') {
            $columns = [];
            foreach ($this->collection->fieldMap() as $name => $prop) {
                if ($prop->isSearchable() || $name === $this->collection::titleField()) { $columns[] = $name; }
            }
            if ($columns !== []) {
                $ids = $this->searchIds($query->search, $columns);
                $ids === [] ? $q->whereRaw('1 = 0', []) : $q->whereIn('id', $ids);
            }
        }
        foreach ($query->filters as $field => $values) {
            $prop = $this->collection->field($field);
            // A visitor's filters are filterable fields (Query::fromRequest); a group page's field always applies
            if ($field === $except || $prop === null || (!$prop->isFilterable() && !in_array($field, $this->collection->groups(), true))) { continue; }
            $groups = $this->collection->groupCollection($field);
            if ($groups !== null) {
                $ids = Filters::groupIds($groups, $values);
                $ids === [] ? $q->whereRaw('1 = 0', []) : $q->whereIn($field, $ids);
            } elseif ($prop->type() === 'tags') {
                $parts = [];
                $bindings = [];
                foreach ($values as $value) {
                    foreach (self::likes($value, true) as $l) {
                        $parts[] = '`' . $field . '` LIKE ? ESCAPE \'!\'';
                        $bindings[] = $l;
                    }
                }
                $q->whereRaw('(' . implode(' OR ', $parts) . ')', $bindings);
            } else {
                $q->whereIn($field, $values);
            }
        }
        if ($query->range !== null && $query->rangeField !== null && $query->rangeField !== $except && $this->collection->field($query->rangeField) !== null) {
            [$from, $to] = $query->range;
            if ($from !== null) { $q->where($query->rangeField, '>=', $from); }
            if ($to !== null) { $q->where($query->rangeField, '<=', $to); }
        }
        return $q;
    }

    protected function ordered(Database $q, Query $query): Database
    {
        $sorts = $this->collection->sorts();
        [$field, $dir] = $sorts[$query->sort] ?? (reset($sorts) ?: ['id', 'ASC']);
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        if (preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1) {
            $field = 'id';
        }
        $column = '`' . $field . '`';
        if ($field === $this->collection::dateField() && $field !== 'created_at') {
            $column = 'COALESCE(`' . $field . '`, `created_at`)';
        }
        // Empty values (no price) last in either direction
        return $q->orderByRaw('(' . $column . ' IS NULL) ASC, ' . $column . ' ' . $dir . ', `id` ' . $dir);
    }

    /**
     * LIKE patterns for a text inside a column: as written, and as JSON
     * writes it with escaped characters (rows saved by json_encode() without
     * JSON_UNESCAPED_UNICODE).
     *
     * @return list<string>
     */
    protected static function likes(string $text, bool $quoted = false): array
    {
        $escape = static fn (string $s): string => '%' . strtr($s, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $plain = substr((string) json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
        $ascii = substr((string) json_encode($text, JSON_UNESCAPED_SLASHES), 1, -1);
        $q = $quoted ? '"' : '';
        $out = [$escape($q . ($quoted ? $plain : $text) . $q)];
        if ($ascii !== ($quoted ? $plain : $text)) {
            $out[] = $escape($q . $ascii . $q);
        }
        return $out;
    }
}
