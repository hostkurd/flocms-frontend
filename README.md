# flocms-frontend

The public side of a FloCMS website: **components** written once and used everywhere, a **Site Style** generated in PHP, **every language and direction** (RTL, Arabic and Kurdish built in), **menus and SEO**, and ready-made **starter home pages**.

- **One component, used everywhere.** A component is a PHP class with its fields, plus a template. Use it in your templates (`@component('hero', [...])`); when [flocms-admin](https://github.com/hostkurd/flocms-admin) is installed, the same component is a Builder block that editors place on pages. Nothing is written twice.
- **Server-rendered, works on plain cPanel hosting.** No Node and no build step on the server. Pages work without JavaScript; a little vanilla JS loads only for the components on the page.
- **Optional and standalone.** It needs only flocms-core. Removing it leaves the site as it was.

```
composer require hostkurd/flocms-frontend
php flo site:install
```

## Contents

- [Requirements](#requirements)
- [Install](#install)
- [The layout](#the-layout)
- [Components](#components)
- [Site Style](#site-style)
- [Languages, numbers and dates](#languages-numbers-and-dates)
- [Menus](#menus)
- [SEO and the page head](#seo-and-the-page-head)
- [Cookie notice and scripts](#cookie-notice-and-scripts)
- [Sitemap](#sitemap)
- [Starter home pages](#starter-home-pages)
- [With flocms-admin](#with-flocms-admin)
- [Commands](#commands)
- [Removing the package](#removing-the-package)
- [Roadmap](#roadmap)

Guides: [Components](docs/components.md) · [Site Style](docs/style.md)

## Requirements

- PHP 8.1+ with `mbstring` and `dom` (`intl` is used when the server has it, but isn't needed)
- `hostkurd/flocms-core` 2.10+ (the template directives and `Mail`)
- Optional: `hostkurd/flocms-admin` 0.43+ for Builder blocks, Settings → Site style and one sitemap

## Install

```
composer require hostkurd/flocms-frontend
php flo site:install
```

`site:install` never overwrites a file. It writes:

| File | What it is |
|---|---|
| `config/frontend.php` | The site's name and contact details, menus, cookie notice, digits |
| `components/README.md` | The folder for your own components |
| `templates/site/layouts/default.html` | A layout with the header, footer and the three page calls |
| `templates/site/style.php` | The Site Style values of this template |

It then offers to make `site` the active template (`APP_TEMPLATE=site` in `.env`). Use `--template=name` for another folder name, `--no-template` to keep your own layout, `--activate` to answer yes without asking.

Next, start the home page from a [starter](#starter-home-pages) and run `php flo site:doctor`.

## The layout

A layout makes three calls; everything else is up to you:

```html
{!! site_html() !!}
<head>
{!! site_head() !!}
</head>
<body>
{!! component('header') !!}
<main id="main">{!! $data['content'] !!}</main>
{!! component('footer') !!}
{!! site_foot() !!}
</body>
</html>
```

- `site_html()` prints the `<html>` tag with `lang` and `dir` from the page's language, the class of its script (`fe-script-arabic` for Arabic and Kurdish) and the visitor's light/dark choice, so one stylesheet serves both directions.
- `site_head()` prints the charset and viewport, the [SEO tags](#seo-and-the-page-head), the Site Style and the CSS of the components on the page.
- `site_foot()` prints the cookie notice when needed, the scripts the visitor allowed, and one combined script for the components on the page (none when the page needs none).

## Components

The package ships 24 components, all working without JavaScript and in RTL:

| Layout | Navigation | Content | Media |
|---|---|---|---|
| `section`, `container`, `grid` | `header`, `footer`, `breadcrumbs`, `pagination`, `language-switcher`, `theme-switch`, `cookie-notice` | `hero`, `card`, `cards`, `button`, `alert`, `accordion`, `tabs`, `dialog`, `call-to-action`, `pricing`, `faq`, `features` | `slider`, `gallery` |

Use one in a template, with slots for content between the tags:

```html
@component('section', ['heading' => 'What we do', 'tone' => 'surface'])
    <p>Content between the tags is the default slot.</p>
    @slot('actions') <a href="/services">All services</a> @endslot
@endcomponent
```

Or without slots, from a template or PHP:

```html
{!! component('button', ['label' => 'Contact us', 'url' => '/contact']) !!}
```

Props are checked on every render. A missing required prop or an unknown prop is an error with `APP_DEBUG` on; in production the component renders without it and logs one line. Values are escaped; rich text is cleaned with an allow-list. Every component takes `id` and `class` too.

**Your own components** live in `components/<name>/`: the class, its template and, when it needs them, CSS and JS. Create one with `php flo site:component Pricing --css --js`. A folder with the name of one of the package's components replaces its files: `components/hero/hero.html` alone changes the hero's markup and keeps its props; `components/hero/hero.css` alone restyles it. See [the components guide](docs/components.md).

`@icon('arrow-right')` and `site_icon('search', ['label' => 'Search'])` print one of the package's ~60 outline icons; arrows and chevrons are mirrored on right-to-left pages.

## Site Style

The whole look of the site comes from one short list of values in the active template's `style.php`, turned into one CSS file in PHP (cached in `storage/cache/frontend`, served with a versioned name):

```php
return [
    'colours' => ['brand' => '#0A7FA8', 'accent' => '#38BBE0', 'text' => '#0E1A24', 'background' => '#FFFFFF', 'surface' => '#F6F8FA', 'band' => '#0C2233'],
    'dark'    => 'switch',          // switch | auto | light | dark
    'fonts'   => ['latin' => 'Instrument Sans', 'arabic' => 'Noto Sans Arabic'],
    'radius'  => 'small',           // none | small (4 px) | medium | large | round
    'spacing' => 'comfortable',     // compact | comfortable | roomy
    'width'   => 1200,
];
```

The package works out the colour scales (in OKLCH, so the steps look even), the dark mode colours, the text colour on each background and the success, warning and danger colours. `php flo site:style --check` reports any pair below WCAG AA. The defaults are the approved default look: white content, cool grey rows, one navy closing band, FloCMS blue for buttons and links, 4 px corners, Instrument Sans and Noto Sans Arabic (self-hosted, no Google Fonts call). flocms-admin's own blocks read the same values, so blocks and components look alike on one page. See [the Site Style guide](docs/style.md).

## Languages, numbers and dates

The site's languages are `LANGUAGES` / `DEFAULT_LANG`, as everywhere in FloCMS.

- Direction comes from the language (`lng.dir` in its lang file); all CSS uses logical properties.
- Each script gets its font; Arabic and Kurdish get a slightly larger size and line height.
- `site_number(2400)`, `site_money(2400, 'USD')`, `site_date($date, 'long')`, `site_time($date)` format by the page's language: "$2,400" in English, "2,400 $" in Arabic and Kurdish. They use PHP's `intl` when it's there (Kurdish Sorani as `ckb`), and the package's own tables for en, ar and ku when it isn't.
- Digits are Latin by default; `'digits' => ['ku' => 'arabic']` in `config/frontend.php` gives Arabic-Indic digits.
- The package's words are in en, ar and ku. Change any of them, or add a language, in your own `lang/<code>.php` as `'frontend.<key>' => '…'`.
- `site_url('/services')` is the address in the page's language.

## Menus

Menus are defined once in `config/frontend.php` and shown by the header, the footer and the breadcrumbs:

```php
'menus' => [
    'main' => fn (Menu $m) => $m
        ->link('frontend.menu.home', '/')
        ->link('Services', '/services', children: fn (Menu $c) => $c->link('Hosting', '/services/hosting'))
        ->pages()                                   // flocms-admin's pages marked "Show in menu"
        ->link('Account', '/account', members: true),
],
```

Labels are word keys or text. The current page's item and its parents are marked (`aria-current="page"`). On phones the header's menu opens without JavaScript. Without a `main` menu, the header shows Home and flocms-admin's pages; without a `footer` menu, the footer shows the main one. In a template: `@foreach(site_menu('main') as $item) … @endforeach`.

## SEO and the page head

From a controller:

```php
site_seo()->title('Our plans')->description('Three design packages…')->image('/img/plans.jpg');
```

`site_head()` prints the title (with the site's name), description, canonical link, `hreflang` alternates for every language, Open Graph and social card tags, and JSON-LD (Organization, WebSite; BreadcrumbList when breadcrumbs are shown; FAQPage from the `faq` component). Without settings, the site's name and description from `config/frontend.php` are used. On a flocms-admin page, the page's own settings (`page_meta()`) are printed instead, so nothing is printed twice. `->noindex()` keeps a page out of search results.

## Cookie notice and scripts

```php
site_script('statistics', 'https://stats.example.com/script.js');
site_script('marketing', '<script>…</script>');
```

Scripts in `statistics` and `marketing` load only after the visitor allows that category in the cookie notice (docked to the bottom of the screen on the first visit). With JavaScript they start at once, without a reload; without it the notice's buttons post the choice. The footer's "Cookie settings" link opens it again. `'cookies' => ['notice' => 'auto']` (the default) shows it only when a script waits for consent; `'on'` and `'off'` decide yourself.

## Sitemap

```php
Sitemap::add(fn () => [['loc' => '/services', 'lastmod' => '2026-11-01', 'alternates' => true]]);
```

There is one sitemap: with flocms-admin's Pages and sitemap on, these addresses join the admin's `/sitemap.xml`; without them, the package serves `/sitemap.xml` itself.

## Starter home pages

Six ready-made home pages, all made of components: **Studio**, **Slider**, **Shop front**, **Journal**, **Landing page** and **Blank**, with sample text in English, Arabic and Kurdish.

- `php flo site:starter studio` creates one. So does the starter picker at `/site-starter`, which only administrators (or anyone on `APP_ENV=local`) see, until a starter is chosen.
- With flocms-admin's Pages on, the starter becomes the home page in Site → Pages, built from Builder blocks.
- Without them, it's written to `views/pages/index.html` as `@component` calls you own and edit. The skeleton's welcome page that was there is kept as `views/pages/index.welcome.html`.
- It's created once; `--force` creates another.

## With flocms-admin

With flocms-admin 0.43 or later installed, the package adds itself to the panel, with no lines in `config/admin.php`:

- Components marked `protected static bool $block = true;` are Builder blocks in the "Your site" group (12 of the built-ins are). Their props become the block's fields, and the website shows the component's own template.
- **Settings → Site style**: colours, fonts, corners, spacing, width and dark mode. Saved values override the template's `style.php`; an empty field keeps the template's value.
- The sitemap addresses you add join the admin's sitemap.

## Commands

| Command | What it does |
|---|---|
| `php flo site:install` | Config, components folder, a template with the layout and `style.php` |
| `php flo site:component Pricing --css --js --block` | A new component folder |
| `php flo site:components` | Every component, where it comes from, which files the site changed |
| `php flo site:style [--check] [--css]` | The style values and colours; `--check` reports contrast problems |
| `php flo site:starter [name] [--force]` | Start the home page from a starter |
| `php flo site:doctor` | Checks the setup and says what to fix |

## Removing the package

`composer remove hostkurd/flocms-frontend`, then point `APP_TEMPLATE` back at your own template (or remove the `site_*` calls from your layout). Your `config/frontend.php`, `components/` and template folder stay.

## Roadmap

- 0.1: components, Site Style, languages, menus and SEO, slider, starter picker, Builder blocks
- 0.2: collections: blog and shop catalogue
- 0.3: site forms and the Form entries screen
- 0.4: visitor accounts
- Later: templates management, on-page editing, `php flo site:check`

## License

MIT. The fonts are under the SIL Open Font License 1.1 (`resources/fonts`).
