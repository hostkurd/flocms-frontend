# Collections

A collection is a set of items that share their fields and get their own pages: a blog, a shop catalogue, projects, team members, events. You describe it in one PHP class; the package gives it a list page, category and tag pages, a page for each item, search, filters, an RSS feed, sitemap entries and SEO, in every language of the site.

There are two ready-made **kits**:

- **Blog**: posts with an excerpt, body, cover, category, tags and "featured", plus their categories. It has an RSS feed and BlogPosting data for search engines.
- **Shop catalogue**: products with a summary, photos, price, availability, category (up to 3 levels) and specs, plus their categories. It has filters, sorting, and an "Ask about this" button to WhatsApp or your contact page. There is no cart and no checkout.

Any other collection uses the plain look (cards in a grid).

## Making one

```
php flo site:collection --kit=blog                  Posts + PostCategories, at /blog
php flo site:collection --kit=shop                  Products + ProductCategories, at /shop
php flo site:collection News --kit=blog --path=news
php flo site:collection Projects                    a collection of your own, at /projects
php flo site:collection Projects --markdown         kept as Markdown files, no table
php flo migrate
```

The command writes:

- `collections/Posts.php` and `collections/PostCategories.php`: the classes, in the `App\Collections` namespace. They load from the folder on their own, with no autoload entry needed.
- One migration per table: `site_posts` and `site_post_categories`.
- The classes in `'collections'` in `config/frontend.php`.

It never overwrites a file, and running it twice does nothing new. `php flo site:collections` lists the collections, their addresses and their tables. `php flo site:doctor` checks that each one loads and that its table exists.

Add items in the table for now. flocms-frontend 0.2.1 adds the admin screens for collections.

## The class

```php
<?php
namespace App\Collections;

use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Components\Prop;

final class Projects extends Collection
{
    protected static string $name = 'projects';                   // also the table: site_projects
    protected static string|array $path = 'projects';             // /en/projects, /en/projects/<slug>
    protected static string|array|null $label = ['en' => 'Projects', 'ar' => 'المشاريع'];
    protected static string $date = 'publish_at';

    public function fields(): array
    {
        return [
            Prop::text('title')->required()->translatable(),
            Prop::slug('slug')->translatable(),
            Prop::textarea('summary')->translatable()->searchable(),
            Prop::richText('body')->translatable(),
            Prop::images('photos'),
            Prop::select('kind', ['house' => 'House', 'office' => 'Office'])->filterable(),
            Prop::tags('tags'),
        ];
    }
}
```

| Setting | Default | What it does |
|---|---|---|
| `$name` | (needed) | Lower-case letters, digits and `_`. It names the table (`site_<name>`) and the template folder. |
| `$path` | `''` | The address after the language. It can differ per language: `['en' => 'blog', 'ar' => 'مدونة']`. Empty means no pages of its own (categories use this). |
| `$table` | `site_<name>` | Another table. |
| `$kit` | `default` | `blog`, `shop` or `default`: which package pages it uses. |
| `$perPage` | `12` | Items per page (1–100). |
| `$label`, `$lead` | from the kit | The list page's heading and the line under it; a text or one per language. |
| `$publishing` | `true` | Draft, Published and Scheduled, with publish and unpublish dates. Categories turn it off. |
| `$feed` | `false` | An RSS feed at `/<lang>/<path>/feed.xml`. |
| `$date` | `publish_at` | The field that orders by "newest" and dates feed entries. If it isn't a column, `created_at` is used. |
| `$title`, `$slug` | `title`, `slug` | Which fields are the title and the address part. |

To extend a kit, extend its class and add fields:

```php
final class Posts extends \FloCMS\Frontend\Collections\Kits\BlogPosts
{
    protected static string $categories = PostCategories::class;

    public function fields(): array
    {
        return [...parent::fields(), Prop::text('reading_level')];
    }
}
```

Also overridable:

- `groups()`: the words in group addresses and their fields, e.g. `['category' => 'category', 'tag' => 'tags']`.
- `sorts()`: the sort options.
- `jsonLd(Item $item)`: the schema.org data for an item's page.
- `makeSource()`: where items are kept.

After adding a field, add its column with a migration of your own. `Schema::columns($collection)` shows the column types the package would use.

## Field types

Collections use the same `Prop` fields as components, plus five new types:

| Prop | Stored as | Cleaned to |
|---|---|---|
| `Prop::slug('slug')` | text | lower case, letters of any script, digits and `-` (`اختيار-الحجر` stays Arabic) |
| `Prop::tags('tags')` | JSON list | a list of words; a comma-separated text works too |
| `Prop::price('price')` | DECIMAL(12,2) | `"240.00"`, or null when empty or negative |
| `Prop::images('photos')` | JSON list | images with `src` and `alt`; unsafe addresses are dropped |
| `Prop::pairs('specs')` | JSON list | `[['label' => 'Size', 'value' => '45 cm'], …]` |

Three settings shape the pages:

- `->filterable()`: a filter on the list page. A select or a group gets checkboxes; a price gets a from–to range.
- `->searchable()`: searched by `?q=`. The title is always searched.
- `->from(OtherCollection::class)`: a select whose options are the items of another collection, as the kits' `category` field does.

A translatable field is stored as JSON per language: `{"en":"…","ar":"…"}`.

## Pages and addresses

| Address | Page |
|---|---|
| `/en/blog` | The list: newest first, search, filters, sort, pages (`?page=2`) |
| `/en/blog/category/advice` | A group: the items in a category, including its sub-categories |
| `/en/blog/tag/stone` | A tag |
| `/en/blog/choosing-a-stone` | An item |
| `/en/blog/feed.xml` | The RSS feed (when `$feed` is on) |

- **Languages.** An item has its own address in each language it is written in, e.g. `/ar/مدونة/اختيار-الحجر`. hreflang links list only those languages, and the language switcher goes to the right address. An address with another language's slug redirects (301) to the item's address in that language. An item not written in the page's language is shown in the default language, with that language's page as its canonical.
- **Visibility.** Visitors only see items that are Published or Scheduled with a publish date that has passed, and not yet unpublished. Everything else is a 404.
- **SEO.** Each page sets its title, description, canonical and breadcrumbs. Item pages add Open Graph data and the kit's schema.org data (BlogPosting; Product with an Offer when there is a price). Search, filter and sort results are marked noindex. Published items join the sitemap with their alternates.
- **Without JavaScript.** Everything works without it. Filters are a form, and on a phone they open as a sheet (`<details>`). A little script adds the product photo arrows, Copy link and closing the filter sheet.

## Changing the pages

Copy a page into your template and change it:

```
templates/<template>/collections/<name>/list.html      the list
templates/<template>/collections/<name>/group.html     a category or tag
templates/<template>/collections/<name>/item.html      an item
templates/<template>/collections/parts/<part>.html     a part, for every collection
```

The parts are `crumbs`, `page-head`, `category-nav`, `filters`, `toolbar`, `post-card`, `product-card`, `item-card`, `empty`, `share`, `specs` and `shop-results`. The package's own files are in `vendor/hostkurd/flocms-frontend/resources/collections/`; start from those.

What a page gets:

- **Every page:** `$collection`, `$title`, `$crumbs` and `$base` (the list's address).
- **List and group:**
  - `$items` and `$result` (`total`, `page`, `pages()`, `isEmpty()`).
  - `$query` (`search`, `filters`, `sort`, `link($base, [...])`).
  - `$groups`, `$filters`, `$sorts` and `$pager`.
  - The list also has `$featured`; a group also has `$group` and `$children`.
- **Item:**
  - `$item`.
  - `$group` and `$group_url`.
  - `$related`, `$newer` and `$older`.
  - `$tag_url(tag)`.
  - `$ask`: the "Ask about this" address.

An item gives you:

- `$item->title()` and `$item->get('field')`, both in the page's language.
- `$item->url($lang)`, `$item->date()`, `$item->image()` and `$item->minutes()` (reading time).
- `$item->languages()`.

In any template:

- `site_part('product-card', [...])` renders a part.
- `site_collection('posts')` gives a collection, e.g. `site_collection('posts')->source()->all()` for the latest posts on the home page.

## The shop

```php
// config/frontend.php
'shop' => [
    'currency' => 'IQD',            // three letters; default USD
    'decimals' => 0,                // 0–3; default 2
    'ask' => [
        'whatsapp' => '+964 750 000 0000',   // "Ask about this" opens WhatsApp with the product's name
        // or 'url' => '/contact',           // default: your contact page, with ?about=<product>
    ],
],
```

Prices are written in the page's language (`Format::money`). A product with no price shows "Price on request" and sorts last. Availability is In stock, Made to order or Out of stock.

## Markdown collections

For a site with no database, keep a collection as files:

```
content/projects/stone-house.md        the default language
content/projects/stone-house.ar.md     Arabic
```

```markdown
---
title: Stone house
tags: [stone, courtyard]
date: 2026-11-04
status: draft            # optional: hide it until you remove this
---
The text, in **Markdown**.
```

The file name is the address part, unless the front matter has a `slug:`. Each language's file gives that language's values. The front matter is simple YAML: `key: value`, `[a, b]` lists, numbers, `true` and `false`. The Markdown covers:

- headings (`#` is an h2, because the page title is the h1), paragraphs, lists, quotes and code;
- bold, italic and links;
- images.

The HTML is cleaned. `php flo site:collection Projects --markdown` sets this up. For your own class:

```php
protected function makeSource(): Source
{
    return new MarkdownSource($this);   // content/<name>/
}
```

## Without the pages

A collection with no `$path` has no pages, and it can still be used in templates and code:

```php
$latest = \App\Collections\Posts::get()->source()->query(
    \FloCMS\Frontend\Collections\Query::fromRequest(\App\Collections\Posts::get(), ['sort' => 'newest'])
);
```
