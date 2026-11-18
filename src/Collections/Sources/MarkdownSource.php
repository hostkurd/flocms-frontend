<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections\Sources;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Markdown;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Languages;

/**
 * A collection kept as Markdown files (D31), for sites with no database:
 *
 *     content/posts/choosing-a-stone.md        the default language
 *     content/posts/choosing-a-stone.ar.md     Arabic
 *
 * Front matter gives the fields (title: …, category: …, tags: [a, b],
 * date: 2026-11-04, status: draft); the text after it is the body. A
 * translatable field takes each language's file. The address part is the
 * file name unless the front matter has a slug. The admin only reads them.
 *
 *     protected function makeSource(): Source
 *     {
 *         return new MarkdownSource($this);            // content/<name>/
 *     }
 */
final class MarkdownSource extends ArraySource
{
    public function __construct(Collection $collection, private ?string $folder = null, private string $bodyField = 'body')
    {
        parent::__construct($collection);
    }

    public function folder(): string
    {
        return $this->folder ?? Frontend::root() . '/content/' . $this->collection::name();
    }

    protected function rows(): array
    {
        $folder = $this->folder();
        if (!is_dir($folder)) {
            return [];
        }
        $languages = Languages::all();
        $default = Languages::default();
        $byName = [];
        foreach (glob($folder . '/*.md') ?: [] as $file) {
            $base = basename($file, '.md');
            $lang = $default;
            if (preg_match('/^(.+)\.([a-z]{2,3}(?:-[a-z0-9]{2,8})?)$/', $base, $m) === 1 && in_array($m[2], $languages, true)) {
                [$base, $lang] = [$m[1], $m[2]];
            }
            [$meta, $body] = Markdown::parseFile((string) file_get_contents($file));
            $meta[$this->bodyField] = Markdown::html($body);
            $byName[$base][$lang] = $meta;
        }
        ksort($byName);
        $rows = [];
        $fields = $this->collection->fieldMap();
        foreach ($byName as $name => $versions) {
            $main = $versions[$default] ?? reset($versions);
            $row = ['id' => $name];
            foreach ($fields as $field => $prop) {
                if ($prop->isTranslatable()) {
                    $map = [];
                    foreach ($versions as $lang => $meta) {
                        if (array_key_exists($field, $meta)) { $map[$lang] = $meta[$field]; }
                    }
                    if ($field === $this->collection::slugField() && !isset($map[$default]) && isset($versions[$default])) {
                        $map[$default] = $name;
                    }
                    $row[$field] = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $value = $main[$field] ?? null;
                    $row[$field] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
                }
            }
            $slugProp = $fields[$this->collection::slugField()] ?? null;
            if ($slugProp === null || !$slugProp->isTranslatable()) {
                $slug = $row[$this->collection::slugField()] ?? null;
                $row[$this->collection::slugField()] = is_string($slug) && $slug !== '' ? $slug : $name;
            }
            $date = $main['date'] ?? $main[$this->collection::dateField()] ?? null;
            $row['status'] = is_string($main['status'] ?? null) ? $main['status'] : 'published';
            $row['publish_at'] = is_scalar($main['publish_at'] ?? $date) ? (string) ($main['publish_at'] ?? $date) : null;
            $row['unpublish_at'] = is_scalar($main['unpublish_at'] ?? null) ? (string) $main['unpublish_at'] : null;
            $row['created_at'] = is_scalar($date) ? (string) $date : date('Y-m-d H:i:s', (int) (@filemtime($folder . '/' . $name . '.md') ?: time()));
            if ($this->collection::dateField() !== '' && !isset($row[$this->collection::dateField()]) ) {
                $row[$this->collection::dateField()] = $row['publish_at'];
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
