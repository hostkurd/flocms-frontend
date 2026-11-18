<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Sources;

use FloCMS\Frontend\Collections\Item;
use FloCMS\Frontend\Collections\Query;
use FloCMS\Frontend\Collections\Result;

/**
 * Where a collection's items are kept (D31): its table (TableSource),
 * Markdown files (MarkdownSource), or a class of the site's. Everything but
 * byId() only ever returns items visitors may see.
 */
interface Source
{
    /** One page of items for a list. */
    public function query(Query $query): Result;

    /** The visible item with this address part in this language (or in the default language). */
    public function find(string $slug, string $lang): ?Item;

    /** An item by id, visible or not (a post's category, the admin's preview). */
    public function byId(int|string $id): ?Item;

    /** @return list<Item> every visible item (sitemaps, feeds, group lists), newest first */
    public function all(): array;

    /**
     * How many visible items each value of a filter field has, with the
     * query's other filters on (the counts beside the checkboxes).
     *
     * @return array<string, int> value => count
     */
    public function facets(Query $query, string $field): array;

    /**
     * The visible items just newer and just older than this one, by the
     * collection's date (an item page's "Newer post" and "Older post").
     *
     * @return array{0: ?Item, 1: ?Item}
     */
    public function neighbours(Item $item): array;
}
