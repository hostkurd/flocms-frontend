<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Support\Languages;

/**
 * One item of a collection (a post, a product), as its pages see it.
 * get() gives a field's value in the page's language, cleaned by its Prop.
 */
final class Item
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @param array<string, mixed> $row the stored row (JSON columns still as text) */
    public function __construct(private Collection $collection, private array $row)
    {
    }

    public function collection(): Collection
    {
        return $this->collection;
    }

    public function id(): int|string
    {
        $id = $this->row['id'] ?? '';
        return is_numeric($id) ? (int) $id : (string) $id;
    }

    /** A field's value in a language (default: the page's), cleaned by its Prop. */
    public function get(string $field, ?string $lang = null): mixed
    {
        $lang ??= Languages::current();
        $key = $field . '|' . $lang;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $prop = $this->collection->field($field);
        $raw = $this->raw($field);
        if ($prop === null) {
            return $this->cache[$key] = $raw;
        }
        if (is_string($raw) && $prop->isTranslatable() && str_starts_with(ltrim($raw), '{')) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : $raw;
        }
        return $this->cache[$key] = $prop->clean($raw, $lang);
    }

    /** The stored value, as it is. */
    public function raw(string $field): mixed
    {
        return $this->row[$field] ?? null;
    }

    /** @return array<string, mixed> */
    public function row(): array
    {
        return $this->row;
    }

    public function title(?string $lang = null): string
    {
        $title = $this->get($this->collection::titleField(), $lang);
        return is_scalar($title) ? (string) $title : '';
    }

    /** Whether it is written in a language (its title has words in it). */
    public function hasLanguage(string $lang): bool
    {
        $field = $this->collection->field($this->collection::titleField());
        if ($field === null || !$field->isTranslatable()) {
            return true;
        }
        $raw = $this->raw($this->collection::titleField());
        $map = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($map)) {
            return $lang === Languages::default();
        }
        return isset($map[$lang]) && is_string($map[$lang]) && trim($map[$lang]) !== '';
    }

    /** @return list<string> the languages it is written in */
    public function languages(): array
    {
        return array_values(array_filter(Languages::all(), fn (string $l): bool => $this->hasLanguage($l)));
    }

    /** Its address part in a language: its own slug, else one made from its title, else its id. */
    public function slug(?string $lang = null): string
    {
        $lang ??= Languages::current();
        if (!$this->hasLanguage($lang)) {
            $lang = Languages::default();
        }
        $slug = $this->get($this->collection::slugField(), $lang);
        $raw = $this->raw($this->collection::slugField());
        $map = is_string($raw) && str_starts_with(ltrim($raw), '{') ? json_decode($raw, true) : $raw;
        if (is_array($map)) {
            // Its own slug in that language only, never another language's
            $slug = is_string($map[$lang] ?? null) ? Prop::slugify($map[$lang]) : '';
        }
        if (!is_string($slug) || $slug === '') {
            $slug = Prop::slugify($this->title($lang));
        }
        return $slug !== '' ? $slug : (string) $this->id();
    }

    /** Its page: /en/blog/<slug> (a page's address in the language it is written in). */
    public function url(?string $lang = null): string
    {
        $lang ??= Languages::current();
        $collection = $this->collection;
        if (!$collection::isPublic()) {
            return '';
        }
        return Languages::url('/' . $collection::path($lang) . '/' . Collection::segment($this->slug($lang)), $lang);
    }

    /** The date it is ordered and shown by (Y-m-d H:i:s), or null. */
    public function date(): ?string
    {
        foreach ([$this->collection::dateField(), 'publish_at', 'created_at'] as $field) {
            $value = $field === '' ? null : $this->raw($field);
            if (is_string($value) && $value !== '' && strtotime($value) !== false) {
                return $value;
            }
        }
        return null;
    }

    /** The first image: of an images field, else of an image field. @return array{src: string, alt: string, width: ?int, height: ?int}|null */
    public function image(?string $lang = null): ?array
    {
        foreach ($this->collection->fieldMap() as $name => $prop) {
            if ($prop->type() === 'images') {
                $images = $this->get($name, $lang);
                if (is_array($images) && $images !== []) {
                    return $images[0];
                }
            }
            if ($prop->type() === 'image') {
                $image = $this->get($name, $lang);
                if (is_array($image) && ($image['src'] ?? '') !== '') {
                    return $image;
                }
            }
        }
        return null;
    }

    /** Visible to visitors now: published (or scheduled and its time came), within its dates. */
    public function isVisible(?int $now = null): bool
    {
        if (!$this->collection::hasPublishing()) {
            return true;
        }
        $now ??= time();
        $status = (string) ($this->row['status'] ?? 'published');
        if ($status !== 'published' && $status !== 'scheduled') {
            return false;
        }
        $from = self::time($this->row['publish_at'] ?? null);
        $until = self::time($this->row['unpublish_at'] ?? null);
        if ($status === 'scheduled' && $from === null) {
            return false; // Scheduled waits for its date
        }
        return ($from === null || $from <= $now) && ($until === null || $until > $now);
    }

    /** The group item a select field points at (its category), or null. */
    public function group(string $field): ?Item
    {
        $groups = $this->collection->groupCollection($field);
        if ($groups === null) {
            return null;
        }
        $id = $this->raw($field);
        if ($id === null || $id === '') {
            return null;
        }
        return $groups->source()->byId($id);
    }

    /** Reading time in minutes, from a rich text or text field (about 200 words a minute, at least 1). */
    public function minutes(string $field = 'body'): int
    {
        $text = $this->get($field);
        $words = is_string($text) ? count(preg_split('/\s+/u', trim(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY) ?: []) : 0;
        return max(1, (int) round($words / 200));
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->collection->field($name) !== null || array_key_exists($name, $this->row);
    }

    private static function time(mixed $value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $time = strtotime($value);
        return $time === false ? null : $time;
    }
}
