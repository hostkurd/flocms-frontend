<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

use FloCMS\Core\Http\Request;
use FloCMS\Core\Http\Response;
use FloCMS\Core\Template;
use FloCMS\Core\TemplateEngine;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Seo\Seo;
use FloCMS\Frontend\Support\FrontendLang;
use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Support\Languages;

/**
 * A collection's pages, answered through core's App::fallbackUsing() (a
 * site controller of the same name always wins):
 *
 *     /en/blog                     list (search ?q=, filters, ?sort=, ?page=)
 *     /en/blog/category/advice     a group: items of one category (or tag)
 *     /en/blog/<slug>              an item
 *     /en/blog/feed.xml            RSS 2.0 (D41)
 *
 * Each page is a template, overridable per site (D39):
 * templates/<template>/collections/<collection>/<page>.html, else the kit's
 * resources/collections/<kit>/<page>.html, inside the site's layout.
 */
final class CollectionPages
{
    /** The fallback: a collection's page for this path, or null for the next fallback (a 404). */
    public static function fallback(string $path, Request $request): ?Response
    {
        if (!$request->isMethod('GET', 'HEAD')) {
            return null;
        }
        $path = trim($path, '/'); // the router has decoded it already
        if ($path === '') {
            return null;
        }
        $lang = Languages::current();
        foreach (Collections::public() as $collection) {
            $base = $collection::path($lang);
            if ($base === '' || ($path !== $base && !str_starts_with($path, $base . '/'))) {
                continue;
            }
            $rest = trim(substr($path, strlen($base)), '/');
            return self::answer($collection, $rest, $request);
        }
        return null;
    }

    private static function answer(Collection $collection, string $rest, Request $request): ?Response
    {
        $get = is_array($_GET ?? null) ? $_GET : [];
        if ($rest === '') {
            return self::html(self::listPage($collection, $get));
        }
        if ($rest === 'feed.xml') {
            return $collection::hasFeed() ? Feed::response($collection) : null;
        }
        $parts = explode('/', $rest);
        if (count($parts) >= 2 && array_key_exists($parts[0], $collection->groups())) {
            // A tag may have a "/" in it (Indoor/outdoor): the rest of the path is its name
            $html = self::groupPage($collection, $parts[0], implode('/', array_slice($parts, 1)), $get);
            return $html === null ? null : self::html($html);
        }
        if (count($parts) !== 1) {
            return null;
        }
        $lang = Languages::current();
        $item = $collection->source()->find($parts[0], $lang);
        if ($item === null) {
            return null;
        }
        // Reached by another language's address while this language has its own: go there (301).
        $canonical = $item->url($lang);
        if ($item->hasLanguage($lang) && $item->slug($lang) !== $parts[0]) {
            return Response::make('', 301)->header('Location', self::asciiUrl($canonical));
        }
        return self::html(self::itemPage($collection, $item));
    }

    private static function html(?string $html): ?Response
    {
        return $html === null ? null : Response::html($html);
    }

    /** An address for a header: letters outside ASCII percent-encoded (pages keep them readable). */
    public static function asciiUrl(string $url): string
    {
        return (string) preg_replace_callback('/[^\x21-\x7E]/', static fn (array $m): string => rawurlencode($m[0]), $url);
    }

    /** @param array<string, mixed> $get */
    public static function listPage(Collection $collection, array $get): ?string
    {
        $query = Query::fromRequest($collection, $get);
        $result = $collection->source()->query($query);
        if ($query->page > 1 && $query->page > $result->pages()) {
            return null; // past the last page
        }
        $base = $collection::url();
        $title = $collection::label();
        $seo = Seo::title($title . ($query->page > 1 ? ' – ' . FrontendLang::get('pagination.page', ['number' => $query->page]) : ''));
        $lead = $collection::lead();
        if ($lead !== '') { $seo->description($lead); }
        $seo->canonical($query->page > 1 ? $base . '?page=' . $query->page : $base);
        if ($query->search !== '' || $query->filterCount() > 0 || $query->sort !== '') {
            $seo->noindex(); // results of a search or filter: not a page of their own
        }
        $crumbs = [['label' => FrontendLang::get('menu.home'), 'url' => Languages::url('/')], ['label' => $title, 'url' => $base]];
        $seo->breadcrumbs($crumbs);
        return self::render($collection, 'list', [
            'collection' => $collection,
            'title' => $title,
            'lead' => $lead,
            'query' => $query,
            'result' => $result,
            'items' => $result->items,
            'base' => $base,
            'groups' => self::groupNav($collection, $query, null),
            'filters' => self::filters($collection, $query),
            'sorts' => self::sortOptions($collection, $query),
            'featured' => $query->page === 1 && $query->search === '' && $query->filterCount() === 0 && $result->items !== [] ? $result->items[0] : null,
            'crumbs' => $crumbs,
            'pager' => ['page' => $result->page, 'pages' => $result->pages(), 'url' => $query->link($base, ['page' => ':page'])],
        ]);
    }

    /** @param array<string, mixed> $get */
    public static function groupPage(Collection $collection, string $word, string $slug, array $get): ?string
    {
        $field = $collection->groups()[$word];
        $prop = $collection->field($field);
        if ($prop === null) {
            return null;
        }
        $lang = Languages::current();
        $groupCollection = $collection->groupCollection($field);
        $group = null;
        if ($groupCollection !== null) {
            foreach ($groupCollection->source()->all() as $candidate) {
                if (Sources\Filters::slugMatches($candidate, $slug)) { $group = $candidate; break; }
            }
            if ($group === null) {
                return null;
            }
            $name = $group->title();
            $description = $group->get('description');
            $wanted = (string) $group->id();
        } elseif ($prop->type() === 'tags') {
            $name = $slug;
            $description = '';
            $wanted = $slug;
        } else {
            return null;
        }
        $get[$field] = $wanted;
        $query = Query::fromRequest($collection, $get);
        $query->filters[$field] = [$wanted];
        $result = $collection->source()->query($query);
        if ($result->total === 0 && $groupCollection === null) {
            return null; // a tag nobody uses
        }
        if ($query->page > 1 && $query->page > $result->pages()) {
            return null; // past the last page
        }
        $base = $collection::groupUrl($word, $group !== null ? $group->slug($lang) : $slug);
        $seo = Seo::title($name . ' – ' . $collection::label());
        if (is_string($description) && $description !== '') { $seo->description($description); }
        $seo->canonical($query->page > 1 ? $base . '?page=' . $query->page : $base);
        if ($query->search !== '' || $query->filterCount() > 1 || $query->sort !== '') { $seo->noindex(); }
        $crumbs = [['label' => FrontendLang::get('menu.home'), 'url' => Languages::url('/')], ['label' => $collection::label(), 'url' => $collection::url()]];
        foreach (self::ancestors($group) as $parent) {
            $crumbs[] = ['label' => $parent->title(), 'url' => $collection::groupUrl($word, $parent->slug())];
        }
        $crumbs[] = ['label' => $name, 'url' => $base];
        $seo->breadcrumbs($crumbs);
        // Without the group's own filter in links: the page is the filter
        $linkQuery = clone $query;
        unset($linkQuery->filters[$field]);
        return self::render($collection, 'group', [
            'collection' => $collection,
            'title' => $name,
            'lead' => is_string($description) ? $description : '',
            'group' => $group,
            'word' => $word,
            'field' => $field,
            'query' => $linkQuery,
            'result' => $result,
            'items' => $result->items,
            'base' => $base,
            'groups' => self::groupNav($collection, $linkQuery, $group),
            'children' => $group !== null ? self::children($collection, $word, $group) : [],
            'filters' => self::filters($collection, $linkQuery, $field),
            'sorts' => self::sortOptions($collection, $linkQuery),
            'featured' => null,
            'crumbs' => $crumbs,
            'pager' => ['page' => $result->page, 'pages' => $result->pages(), 'url' => $linkQuery->link($base, ['page' => ':page'])],
        ]);
    }

    public static function itemPage(Collection $collection, Item $item): string
    {
        $lang = Languages::current();
        $title = $item->title();
        $seo = Seo::title((string) ($item->get('meta_title') ?: $title));
        $description = $item->get('meta_description') ?: $item->get('excerpt') ?: $item->get('summary') ?: '';
        if (is_string($description) && $description !== '') { $seo->description($description); }
        $image = $item->image();
        if ($image !== null) { $seo->image($image['src']); }
        $seo->type($collection::kit() === 'shop' ? 'product' : 'article');
        $alternates = [];
        foreach ($item->languages() as $l) { $alternates[$l] = $item->url($l); }
        if ($item->hasLanguage($lang)) {
            $seo->canonical($item->url($lang));
        } else {
            // Shown in the default language: search engines get that page (the rule of admin Pages)
            $seo->canonical($item->url(Languages::default()));
        }
        $seo->alternates($alternates);
        $switch = [];
        foreach (Languages::all() as $l) { $switch[$l] = $item->url($l); }
        Languages::useSwitchUrls($switch);
        if (($item->raw('noindex') ?? 0) == 1) { $seo->noindex(); }
        $crumbs = [['label' => FrontendLang::get('menu.home'), 'url' => Languages::url('/')], ['label' => $collection::label(), 'url' => $collection::url()]];
        $groupWord = null;
        $groupItem = null;
        foreach ($collection->groups() as $word => $field) {
            $groupItem = $item->group($field);
            if ($groupItem !== null) {
                $groupWord = $word;
                foreach (array_merge(self::ancestors($groupItem), [$groupItem]) as $g) {
                    $crumbs[] = ['label' => $g->title(), 'url' => $collection::groupUrl($word, $g->slug())];
                }
                break;
            }
        }
        $crumbs[] = ['label' => $title, 'url' => $item->url()];
        $seo->breadcrumbs($crumbs);
        $data = $collection->jsonLd($item);
        if ($data !== null) { $seo->jsonLd($data); }
        [$newer, $older] = self::neighbours($collection, $item);
        return self::render($collection, 'item', [
            'collection' => $collection,
            'item' => $item,
            'title' => $title,
            'group' => $groupItem,
            'group_url' => $groupItem !== null && $groupWord !== null ? $collection::groupUrl($groupWord, $groupItem->slug()) : null,
            'related' => self::related($collection, $item, $collection::kit() === 'shop' ? 4 : 3),
            'newer' => $newer,
            'older' => $older,
            'crumbs' => $crumbs,
            'base' => $collection::url(),
            'tag_url' => array_key_exists('tag', $collection->groups()) ? static fn (string $tag): string => $collection::groupUrl('tag', $tag) : null,
            'ask' => self::askUrl($item),
        ]);
    }

    /** A part of the pages (a card, the filters): the site's own, else the package's. @param array<string, mixed> $vars */
    public static function part(string $name, array $vars = []): string
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,40}$/', $name) !== 1) {
            throw new \InvalidArgumentException('A part name is lower-case words joined with "-": ' . $name);
        }
        unset($vars['__floCompiled'], $vars['__floSource'], $vars['__floLevel'], $vars['data'], $vars['this']);
        $site = Frontend::root() . '/templates/' . \FloCMS\Frontend\Style\Style::template() . '/collections/parts/' . $name . '.html';
        $file = is_file($site) ? $site : Frontend::path('resources/collections/parts/' . $name . '.html');
        if (!is_file($file)) {
            throw new \RuntimeException('There is no collection part "' . $name . '".');
        }
        return TemplateEngine::renderFile($file, $vars);
    }

    /** The page in the site's layout. @param array<string, mixed> $vars */
    public static function render(Collection $collection, string $page, array $vars): string
    {
        $content = TemplateEngine::renderFile(self::template($collection, $page), $vars) . self::script();
        $layout = self::layout();
        return $layout === null ? $content : TemplateEngine::renderFile($layout, ['content' => $content]);
    }

    /** The template file for a collection's page (D39). */
    public static function template(Collection $collection, string $page): string
    {
        $template = \FloCMS\Frontend\Style\Style::template();
        $candidates = [
            Frontend::root() . '/templates/' . $template . '/collections/' . $collection::name() . '/' . $page . '.html',
            Frontend::path('resources/collections/' . $collection::kit() . '/' . $page . '.html'),
            Frontend::path('resources/collections/default/' . $page . '.html'),
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) { return $file; }
        }
        throw new \RuntimeException('No template for the ' . $page . ' page of ' . $collection::class . '.');
    }

    /**
     * The little the pages do with JavaScript; every page works without it:
     * the product photos' arrows, Copy link, and closing the filters on a phone.
     */
    private static function script(): string
    {
        return <<<'JS'
<script>
(function(){
document.querySelectorAll('[data-fe-product-gallery]').forEach(function(g){
var track=g.querySelector('[data-fe-track]');if(!track)return;var items=track.children,count=g.querySelector('[data-fe-count]'),thumbs=g.querySelectorAll('.fe-product__thumbs a');
function at(){var w=track.clientWidth||1;return Math.min(items.length-1,Math.round(Math.abs(track.scrollLeft)/w));}
function show(i){i=(i+items.length)%items.length;track.scrollTo({left:(document.dir==='rtl'?-1:1)*i*track.clientWidth,behavior:'smooth'});}
g.querySelectorAll('[data-fe-step]').forEach(function(b){b.hidden=false;b.addEventListener('click',function(){show(at()+Number(b.getAttribute('data-fe-step')));});});
thumbs.forEach(function(t,i){t.addEventListener('click',function(e){e.preventDefault();show(i);});});
track.addEventListener('scroll',function(){var i=at();if(count)count.textContent=(i+1)+' / '+items.length;thumbs.forEach(function(t,j){if(j===i)t.setAttribute('aria-current','true');else t.removeAttribute('aria-current');});},{passive:true});
});
document.querySelectorAll('[data-fe-copy]').forEach(function(b){if(!navigator.clipboard)return;b.hidden=false;b.addEventListener('click',function(){navigator.clipboard.writeText(b.getAttribute('data-fe-copy')).then(function(){var s=b.querySelector('span'),t=s.textContent;s.textContent=b.getAttribute('data-fe-copied');setTimeout(function(){s.textContent=t;},2000);});});});
document.querySelectorAll('[data-fe-filters]').forEach(function(d){if(window.matchMedia('(max-width: 900px)').matches)d.removeAttribute('open');var c=d.querySelector('[data-fe-filters-close]');if(c)c.addEventListener('click',function(){d.removeAttribute('open');});});
})();
</script>
JS;
    }

    private static function layout(): ?string
    {
        if (!defined('TEMPLATES_PATH') || !defined('DS')) { return null; }
        try {
            $path = Template::getLayoutPath('default');
            return is_file($path) ? $path : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The tabs/links of a list's groups (blog categories with counts).
     *
     * @return list<array{label: string, url: string, count: int, current: bool}>
     */
    public static function groupNav(Collection $collection, Query $query, ?Item $current): array
    {
        foreach ($collection->groups() as $word => $field) {
            $groups = $collection->groupCollection($field);
            if ($groups === null) { continue; }
            $counts = self::groupCounts($collection, $field, new Query());
            $parent = Sources\Filters::parentField($groups);
            $nav = [];
            foreach ($groups->source()->all() as $g) {
                if ($parent !== null && $g->raw($parent) !== null && $g->raw($parent) !== '') { continue; } // top level only
                $nav[] = ['label' => $g->title(), 'url' => $collection::groupUrl($word, $g->slug()), 'count' => $counts[(string) $g->id()] ?? 0, 'current' => $current !== null && (string) $current->id() === (string) $g->id(), 'image' => $g->image()];
            }
            return $nav;
        }
        return [];
    }

    /** @return list<array{label: string, url: string, count: int}> the sub-groups of a group (Lighting → Pendants) */
    private static function children(Collection $collection, string $word, Item $group): array
    {
        $groups = $group->collection();
        $parent = Sources\Filters::parentField($groups);
        if ($parent === null) { return []; }
        $field = $collection->groups()[$word];
        $counts = self::groupCounts($collection, $field, new Query());
        $out = [];
        foreach ($groups->source()->all() as $g) {
            if ((string) $g->raw($parent) === (string) $group->id()) {
                $out[] = ['label' => $g->title(), 'url' => $collection::groupUrl($word, $g->slug()), 'count' => $counts[(string) $g->id()] ?? 0];
            }
        }
        return $out;
    }

    /** Items per group, a sub-group's counted in its parents too. @return array<string, int> */
    private static function groupCounts(Collection $collection, string $field, Query $query): array
    {
        $counts = $collection->source()->facets($query, $field);
        $groups = $collection->groupCollection($field);
        $parent = $groups !== null ? Sources\Filters::parentField($groups) : null;
        if ($groups === null || $parent === null) { return $counts; }
        $parents = [];
        foreach ($groups->source()->all() as $g) { $parents[(string) $g->id()] = (string) ($g->raw($parent) ?? ''); }
        $total = [];
        foreach ($counts as $id => $n) {
            $seen = [];
            for ($at = (string) $id; $at !== '' && !isset($seen[$at]); $at = $parents[$at] ?? '') {
                $seen[$at] = true;
                $total[$at] = ($total[$at] ?? 0) + $n;
            }
        }
        return $total;
    }

    /**
     * The filters of a list page (D35): checkboxes for groups and selects,
     * from–to for prices, each with the query's state.
     *
     * @return list<array<string, mixed>>
     */
    public static function filters(Collection $collection, Query $query, ?string $skip = null): array
    {
        $lang = Languages::current();
        $out = [];
        foreach ($collection->fieldMap() as $name => $prop) {
            if (!$prop->isFilterable() || $name === $skip) { continue; }
            $label = FrontendLang::has('collections.fields.' . $name) ? FrontendLang::get('collections.fields.' . $name) : $prop->getLabel();
            if ($prop->type() === 'price') {
                $on = $query->rangeField === $name ? $query->range : null;
                $out[] = ['type' => 'range', 'name' => $name, 'label' => $label, 'from' => $on[0] ?? null, 'to' => $on[1] ?? null, 'currency' => Frontend::config()->shop()['currency']];
                continue;
            }
            if ($prop->type() === 'tags') { continue; }
            $options = [];
            $groups = $collection->groupCollection($name);
            if ($groups !== null) {
                $parent = Sources\Filters::parentField($groups);
                $counts = self::groupCounts($collection, $name, self::without($query, $name));
                foreach ($groups->source()->all() as $g) {
                    if ($parent !== null && $g->raw($parent) !== null && $g->raw($parent) !== '') { continue; }
                    $slug = $g->slug($lang);
                    $options[] = ['value' => $slug, 'label' => $g->title(), 'count' => $counts[(string) $g->id()] ?? 0, 'checked' => in_array($slug, $query->filters[$name] ?? [], true) || in_array((string) $g->id(), $query->filters[$name] ?? [], true)];
                }
            } else {
                $counts = $collection->source()->facets($query, $name);
                foreach ($prop->getOptions() as $value => $optionLabel) {
                    $text = is_array($optionLabel) ? Prop::pick($optionLabel, $lang) : $optionLabel;
                    $options[] = ['value' => (string) $value, 'label' => is_string($text) ? FrontendLang::label($text) : (string) $value, 'count' => $counts[(string) $value] ?? 0, 'checked' => in_array((string) $value, $query->filters[$name] ?? [], true)];
                }
            }
            if ($options !== []) {
                $out[] = ['type' => 'checkboxes', 'name' => $name, 'label' => $label, 'options' => $options];
            }
        }
        return $out;
    }

    private static function without(Query $query, string $field): Query
    {
        $copy = clone $query;
        unset($copy->filters[$field]);
        return $copy;
    }

    /** @return list<array{value: string, label: string, selected: bool}> */
    private static function sortOptions(Collection $collection, Query $query): array
    {
        $out = [];
        $first = true;
        foreach (array_keys($collection->sorts()) as $key) {
            $word = 'collections.sort.' . $key;
            $out[] = ['value' => $first ? '' : $key, 'label' => FrontendLang::has($word) ? FrontendLang::get($word) : ucfirst(str_replace('_', ' ', $key)), 'selected' => $query->sort === $key || ($first && $query->sort === '')];
            $first = false;
        }
        return $out;
    }

    /** @return list<Item> the group's parents, top first */
    private static function ancestors(?Item $group): array
    {
        if ($group === null) { return []; }
        $parent = Sources\Filters::parentField($group->collection());
        if ($parent === null) { return []; }
        $out = [];
        $at = $group;
        for ($i = 0; $i < 3; $i++) {
            $id = $at->raw($parent);
            if ($id === null || $id === '') { break; }
            $at = $group->collection()->source()->byId($id);
            if ($at === null) { break; }
            array_unshift($out, $at);
        }
        return $out;
    }

    /** @return list<Item> up to $limit others of the same group, newest first, else the newest */
    public static function related(Collection $collection, Item $item, int $limit): array
    {
        $field = array_values($collection->groups())[0] ?? null;
        $source = $collection->source();
        $out = [];
        $add = static function (array $items) use (&$out, $item, $limit): void {
            foreach ($items as $other) {
                if (count($out) >= $limit) { return; }
                if ((string) $other->id() === (string) $item->id() || isset($out[(string) $other->id()])) { continue; }
                $out[(string) $other->id()] = $other;
            }
        };
        // The same group first (two small queries, never every item)
        $value = $field !== null ? $item->raw($field) : null;
        if (is_scalar($value) && (string) $value !== '' && $collection->field($field)?->type() !== 'tags') {
            $same = new Query();
            $same->perPage = $limit + 1;
            $same->filters[$field] = [(string) $value];
            $add($source->query($same)->items);
        }
        if (count($out) < $limit) {
            $newest = new Query();
            $newest->perPage = $limit + count($out) + 1;
            $add($source->query($newest)->items);
        }
        return array_values($out);
    }

    /** @return array{0: ?Item, 1: ?Item} the newer and the older item (by date) */
    private static function neighbours(Collection $collection, Item $item): array
    {
        return $collection->source()->neighbours($item);
    }

    /** "Ask about this product" (Q4): WhatsApp with the product's name and link, or the site's page. */
    public static function askUrl(Item $item): string
    {
        $shop = Frontend::config()->shop();
        $text = $item->title() . ' – ' . \FloCMS\Frontend\Seo\Seo::absolute($item->url());
        if ($shop['whatsapp'] !== '') {
            return 'https://wa.me/' . $shop['whatsapp'] . '?text=' . rawurlencode($text);
        }
        $url = $shop['ask_url'];
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = Languages::url($url);
        }
        return Html::safeUrl($url . (str_contains($url, '?') ? '&' : '?') . 'about=' . rawurlencode($item->title()));
    }
}
