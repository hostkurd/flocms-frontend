<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

use FloCMS\Frontend\Collections\Sources\Source;
use FloCMS\Frontend\Collections\Sources\TableSource;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Support\Languages;

/**
 * A kind of content with public pages (D23): posts, products, projects.
 * One class says what an item has (fields()), where its pages are ($path)
 * and where it is kept (source()); the package gives it a list page, group
 * pages, item pages, a feed, sitemap entries and, with flocms-admin, its
 * admin screens.
 *
 *     final class Posts extends Collection
 *     {
 *         protected static string $name = 'posts';
 *         protected static string|array $path = 'blog';   // /en/blog, /en/blog/<slug>
 *
 *         public function fields(): array
 *         {
 *             return [
 *                 Prop::text('title')->required()->translatable()->searchable(),
 *                 Prop::slug('slug')->from('title')->translatable(),
 *                 Prop::richText('body')->translatable(),
 *                 Prop::select('category')->from(PostCategories::class)->filterable(),
 *             ];
 *         }
 *     }
 */
abstract class Collection
{
    /** Lower-case words joined with "_": posts, products, product_categories. */
    protected static string $name = '';
    /**
     * The address of its pages after the language ("blog", or one per
     * language ['en' => 'blog', 'ar' => 'المدونة']). Empty: no pages of its
     * own, e.g. categories, whose pages belong to the collection they group.
     *
     * @var string|array<string, string>
     */
    protected static string|array $path = '';
    /** Its table; default "site_<name>". */
    protected static ?string $table = null;
    /** The pages it is shown with: "blog", "shop" or "default" (D39). */
    protected static string $kit = 'default';
    protected static int $perPage = 12;
    /** Its name for people, one per language or a word key. */
    protected static string|array|null $label = null;
    /** A short line under the list page's heading. */
    protected static string|array|null $lead = null;
    /** Items have Draft / Published / Scheduled and dates (D29). Groups (categories) don't. */
    protected static bool $publishing = true;
    /** An RSS feed at /<lang>/<path>/feed.xml (D41). */
    protected static bool $feed = false;
    /** The field that orders by "newest" and dates feed entries. */
    protected static string $date = 'publish_at';
    protected static string $title = 'title';
    protected static string $slug = 'slug';

    /** @var array<class-string, Collection> */
    private static array $instances = [];
    /** @var array<class-string, array<string, Prop>> */
    private static array $fieldMaps = [];
    private ?Source $sourceInstance = null;

    /** @return list<Prop> */
    abstract public function fields(): array;

    /**
     * Group pages: the address word => the field it groups by. A select
     * field from a collection (categories) or a tags field.
     *
     *     return ['category' => 'category', 'tag' => 'tags'];
     *
     * @return array<string, string>
     */
    public function groups(): array
    {
        return [];
    }

    /**
     * The orders a visitor can choose: key => [field, ASC|DESC]. The first is
     * the default. "newest" orders by $date.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function sorts(): array
    {
        return static::dateField() !== '' ? ['newest' => [static::dateField(), 'DESC']] : ['name' => [static::$title, 'ASC']];
    }

    /** Where its items are kept: its table (default), or Markdown files, or a class of the site's. */
    protected function makeSource(): Source
    {
        return new TableSource($this);
    }

    /** schema.org data for an item's page, or null. */
    public function jsonLd(Item $item): ?array
    {
        return null;
    }

    final public function source(): Source
    {
        return $this->sourceInstance ??= $this->makeSource();
    }

    /** The one instance of a collection class. */
    final public static function get(): static
    {
        /** @var static */
        return self::$instances[static::class] ??= new static();
    }

    public static function forget(): void
    {
        self::$instances = [];
        self::$fieldMaps = [];
    }

    public static function name(): string
    {
        $name = static::$name;
        if (preg_match('/^[a-z][a-z0-9_]{0,40}$/', $name) !== 1) {
            throw new \LogicException(static::class . '::$name must be lower-case letters, digits and _, starting with a letter, e.g. "posts".');
        }
        return $name;
    }

    public static function table(): string
    {
        return static::$table ?? 'site_' . static::name();
    }

    public static function kit(): string
    {
        return in_array(static::$kit, ['blog', 'shop', 'default'], true) ? static::$kit : 'default';
    }

    public static function perPage(): int { return max(1, min(100, static::$perPage)); }
    public static function hasPublishing(): bool { return static::$publishing; }
    public static function hasFeed(): bool { return static::$feed && static::dateField() !== ''; }
    /**
     * The field that dates an item: $date when it is a column (one of its
     * fields, publish_at with publishing, or created_at), else created_at.
     */
    public static function dateField(): string
    {
        $date = static::$date;
        if ($date === '' || $date === 'created_at' || isset(static::get()->fieldMap()[$date]) || ($date === 'publish_at' && static::$publishing)) {
            return $date;
        }
        return 'created_at';
    }
    public static function titleField(): string { return static::$title; }
    public static function slugField(): string { return static::$slug; }

    /** Whether it has pages of its own. */
    public static function isPublic(): bool
    {
        return static::$path !== '' && static::$path !== [];
    }

    /** The address after the language: "blog" ('' without pages). */
    public static function path(?string $lang = null): string
    {
        $path = static::$path;
        if (is_array($path)) {
            $lang ??= Languages::current();
            $path = $path[$lang] ?? $path[Languages::default()] ?? (string) reset($path);
        }
        return trim((string) $path, '/');
    }

    /** Every address word it answers to, one per language (for routing). @return array<string, string> lang => path */
    public static function paths(): array
    {
        $out = [];
        foreach (Languages::all() as $lang) {
            $out[$lang] = static::path($lang);
        }
        return $out;
    }

    public static function label(?string $lang = null): string
    {
        $label = static::$label;
        if (is_array($label)) {
            $picked = Prop::pick($label, $lang ?? Languages::current());
            return is_string($picked) ? $picked : ucfirst(str_replace('_', ' ', static::name()));
        }
        if (is_string($label) && $label !== '') {
            return \FloCMS\Frontend\Support\FrontendLang::label($label);
        }
        return ucfirst(str_replace('_', ' ', static::name()));
    }

    public static function lead(?string $lang = null): string
    {
        $lead = static::$lead;
        if (is_array($lead)) {
            $picked = Prop::pick($lead, $lang ?? Languages::current());
            return is_string($picked) ? $picked : '';
        }
        return is_string($lead) ? \FloCMS\Frontend\Support\FrontendLang::label($lead) : '';
    }

    /** @return array<string, Prop> name => prop */
    final public function fieldMap(): array
    {
        if (!isset(self::$fieldMaps[static::class])) {
            $map = [];
            foreach ($this->fields() as $prop) {
                if (!$prop instanceof Prop) {
                    throw new \LogicException(static::class . '::fields() must return Prop objects.');
                }
                $map[$prop->name()] = $prop;
            }
            self::$fieldMaps[static::class] = $map;
        }
        return self::$fieldMaps[static::class];
    }

    final public function field(string $name): ?Prop
    {
        return $this->fieldMap()[$name] ?? null;
    }

    /** The collection a select field takes its options from (categories), or null. */
    final public function groupCollection(string $field): ?Collection
    {
        $from = $this->field($field)?->getFrom();
        if ($from === null || !is_subclass_of($from, Collection::class)) {
            return null;
        }
        return $from::get();
    }

    /** Where it lists in the site, e.g. /en/blog. */
    public static function url(?string $lang = null, array $query = []): string
    {
        $lang ??= Languages::current();
        $url = Languages::url('/' . static::path($lang), $lang);
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    /** A group page's address: /en/blog/category/<slug>. */
    public static function groupUrl(string $word, string $slug, ?string $lang = null): string
    {
        $lang ??= Languages::current();
        return Languages::url('/' . static::path($lang) . '/' . $word . '/' . self::segment($slug), $lang);
    }

    /**
     * One part of an address: letters in any script stay readable (as the
     * collection's own path does); anything else unsafe is percent-encoded.
     */
    public static function segment(string $text): string
    {
        return (string) preg_replace_callback('/[^\p{L}\p{M}\p{N}\-_.~]/u', static fn (array $m): string => rawurlencode($m[0]), $text);
    }
}
