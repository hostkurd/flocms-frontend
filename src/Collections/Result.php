<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

/** One page of a list: its items and how many there are in all. */
final class Result
{
    /** @param list<Item> $items */
    public function __construct(public readonly array $items, public readonly int $total, public readonly int $page, public readonly int $perPage)
    {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
