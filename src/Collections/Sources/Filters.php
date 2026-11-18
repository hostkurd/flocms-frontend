<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Sources;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Item;
use FloCMS\Frontend\Support\Languages;

/** What sources share: a group filter's slugs become ids, with every sub-group (Lighting → Pendants). */
final class Filters
{
    /**
     * The ids a group filter stands for: each wanted group (by slug or id)
     * and the groups under it.
     *
     * @param list<string> $wanted
     * @return list<int|string>
     */
    public static function groupIds(Collection $groups, array $wanted): array
    {
        $all = $groups->source()->all();
        $ids = [];
        foreach ($all as $group) {
            foreach ($wanted as $want) {
                if ((string) $group->id() === $want || self::slugMatches($group, $want)) {
                    $ids[] = $group->id();
                }
            }
        }
        // Children of what is wanted, level by level (at most 3 levels, D34)
        $parent = self::parentField($groups);
        if ($parent !== null) {
            for ($level = 0; $level < 3; $level++) {
                $added = false;
                foreach ($all as $group) {
                    $p = $group->raw($parent);
                    if ($p !== null && $p !== '' && in_array((string) $p, array_map('strval', $ids), true) && !in_array($group->id(), $ids, true)) {
                        $ids[] = $group->id();
                        $added = true;
                    }
                }
                if (!$added) { break; }
            }
        }
        return array_values(array_unique($ids, SORT_REGULAR));
    }

    /** Whether an item's searched fields (and its title) have the text, in a language, without markup. */
    public static function matchesSearch(Collection $collection, Item $item, string $search, string $lang): bool
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return true;
        }
        foreach ($collection->fieldMap() as $name => $prop) {
            if (!$prop->isSearchable() && $name !== $collection::titleField()) { continue; }
            if (!array_key_exists($name, $item->row())) { continue; }
            $text = $item->get($name, $lang);
            $text = is_array($text) ? implode(' ', array_filter($text, 'is_string')) : (string) (is_scalar($text) ? $text : '');
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (mb_stripos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** The select field of a group collection that points at its own items (its parent), if any. */
    public static function parentField(Collection $groups): ?string
    {
        foreach ($groups->fieldMap() as $name => $prop) {
            if ($prop->type() === 'select' && $prop->getFrom() === $groups::class) {
                return $name;
            }
        }
        return null;
    }

    public static function slugMatches(Item $item, string $slug): bool
    {
        foreach (Languages::all() as $lang) {
            if ($item->hasLanguage($lang) && $item->slug($lang) === $slug) {
                return true;
            }
        }
        return false;
    }
}
