# Changelog

## 0.2.0 - 2026-11-18

**Collections**: a blog, a shop catalogue, or any set of items with its own pages, in every language of the site. See [docs/collections.md](docs/collections.md).

- **A collection is one class** (`App\Collections\…`, in `collections/`, loaded on its own) with its fields as `Prop`s. It sets its address, which can differ per language (`['en' => 'blog', 'ar' => 'مدونة']`), how many items a page shows, its label and whether it has publishing and a feed. It lists in `'collections'` in `config/frontend.php`.
- **Pages**:
  - a list with search (`?q=`), filters, sorting and pages;
  - category and tag pages (a category includes its sub-categories, up to 3 levels);
  - a page for each item, at `/<lang>/<path>/<slug>`;
  - an RSS 2.0 feed at `/<lang>/<path>/feed.xml`.
  The pages are server-rendered, work without JavaScript, and support RTL, dark mode and phones.
- **Languages**: an item has its own address in each language it is written in, and Arabic and Kurdish addresses stay readable. hreflang links name only those languages, and the language switcher goes to the right address. A slug from another language redirects (301). An item without the page's language is shown in the default language.
- **Visibility**: visitors see Published items, and Scheduled items once their date has passed, until they are unpublished. Drafts, future items and expired items are a 404 and stay out of the sitemap and the feed.
- **SEO**: title, description, canonical, breadcrumbs and Open Graph on every page, plus BlogPosting or Product data (with an Offer when there is a price). Search, filter and sort results are noindex. Items join the sitemap with their alternates.
- **Blog kit** (`Kits\BlogPosts`, `Kits\BlogCategories`):
  - featured newest post, excerpt, cover, category, tags and reading time;
  - older and newer post, more in the category, share and Copy link;
  - RSS.
- **Shop catalogue kit** (`Kits\ShopProducts`, `Kits\ShopCategories`):
  - photos with arrows and thumbnails, price ("Price on request" when empty, sorted last), availability and specs;
  - category and price filters with counts, and sorting by newest, price and name;
  - "Ask about this" to WhatsApp or your contact page.
  New `'shop'` settings: `currency`, `decimals` and `ask` (`whatsapp` or `url`). There is no cart.
- **Other collections** use the plain look: cards in a grid.
- **Pages you can change**: `templates/<template>/collections/<name>/{list,group,item}.html` and `templates/<template>/collections/parts/<part>.html` replace the package's own. New helpers: `site_part()` and `site_collection()`.
- **Markdown collections** (`Sources\MarkdownSource`): items as `content/<name>/<slug>[.<lang>].md` with front matter, for sites with no database.
- **New field types**:
  - `Prop::slug()` keeps letters of any script;
  - `Prop::tags()`, `Prop::price()`, `Prop::images()` and `Prop::pairs()`;
  - `->filterable()`, `->searchable()` and `->from(OtherCollection::class)`.
- **Commands**:
  - `php flo site:collection [Name] --kit=blog|shop [--path=] [--markdown]` writes the classes, a migration per table (portable across MySQL, MariaDB and SQLite) and the config line, never overwriting.
  - `php flo site:collections` lists the collections.
  - `php flo site:doctor` checks each collection's class, address and table.
- New words `collections.*` (en, ar, ku); new icons `rss` and `file`.
- **Later, in 0.2.1**: collections in flocms-admin (screens, publishing, preview, permissions), the Collection list Builder block, and the Journal and Shop front starters showing live items.

## 0.1.4 - 2026-11-17

- **Fixed: a chosen starter showed without any style** when the active template was still the skeleton's `default` (e.g. `site:install` was answered "no", or never run). That layout doesn't call `site_head()`, so the Site Style was never linked. Choosing a starter (the picker or `php flo site:starter`) now makes sure the active template's layout calls `site_head()`. If it doesn't, the package's `site` template is written (only missing files) and made active (`APP_TEMPLATE=site` in `.env`), and the picker and the command say so. A template of your own that calls `site_head()` is kept. When `.env` can't be written, you get a warning with what to do. New `Support\SiteTemplate`; new words `starter.template_on`, `starter.template_problem` (en, ar, ku).
- **A site where a starter was already chosen** isn't changed by updating: run `php flo site:install --activate` once (or set `APP_TEMPLATE=site` in `.env`).

## 0.1.3 - 2026-11-17

- **The starter picker shows real previews**: a picture of each starter's first screen in the default Site Style, in the page's language (en, ar, ku), instead of the grey outline sketches. In the previews, the image placeholders and slides show dummy pictures (houses, rooms, lamps, furniture) so a starter reads as a finished page; the starter itself still has placeholders for your own photos. 18 WebP files (720×540, about 220 KB in all) in `resources/starters/previews/`, served from `/site-assets/preview/` and cached for a year.

## 0.1.2 - 2026-11-17

- **Fixed: no stylesheet when the site is viewed on another address than `APP_URL`** (e.g. `APP_URL=http://localhost` on `https://mysite.localhost`). The Site Style and the component files were linked with `APP_URL`'s full address, so the browser asked the wrong host (and an https page refused an http file). They are now linked from the site's root (`/site-assets/file/…`), like the fonts already were. Links in menus, canonical and hreflang still use `APP_URL`.
- **Fixed: the starter picker works on such an address too.** Its form, the redirect after choosing and the coming-soon page's sign-in link stay on the address you are using (`Languages::path()`, new).
- **The picker warns when `APP_URL` doesn't match** the address you are viewing, with the value to set, the way the skeleton's welcome page did. New word: `starter.app_url` (en, ar, ku).
- The picker's "only administrators" note keeps its icon on the same line.

## 0.1.1 - 2026-11-17

- **The home page of a new site** (needs skeleton 2.1's `home.fallback`). While no starter is chosen and `views/pages/index.html` is still the skeleton's welcome page, `/` shows the starter picker to administrators (and on `APP_ENV=local`) and a "coming soon" page to everyone else, in the Site Style, in en/ar/ku, marked noindex, with a "Site owner? Sign in" link. Once a starter is chosen or the page is your own, `/` is yours again. A site's own `home.fallback` is kept; `'starter' => false` turns it off. Older skeletons ignore the setting.
- New word: `starter.owner_sign_in` (en, ar, ku).

## 0.1.0 - 2026-11-13

The first release: the public side of a FloCMS website. Needs flocms-core 2.10; works with flocms-admin 0.43+ when it is installed.

- **Components**: one class with its props plus a template, used in templates (`@component` … `@slot` … `@endcomponent`, `component()`) and as flocms-admin Builder blocks. Props of the package's own (text, textarea, rich text, number, toggle, select, image, link, date, colour, icon, list) are checked and cleaned on every render. A site's `components/<name>/` folder adds components or replaces any file of the package's.
- **24 built-in components** in the approved default look, working without JavaScript and in RTL: section, container, grid, header, footer, hero, full-width slider, card, cards, button, alert, accordion, tabs, dialog, gallery, breadcrumbs, pagination, language switcher, light/dark switch, cookie notice, call to action, pricing, FAQ, features.
- **Site Style**: the look from the active template's `style.php`, turned into one cached CSS file in PHP: OKLCH colour scales, worked-out dark mode, text colours that pass WCAG contrast, self-hosted Instrument Sans and Noto Sans Arabic. flocms-admin's blocks follow it.
- **Languages**: `lang`/`dir` and the script's font from the page's language, logical CSS, an icon set whose arrows flip in RTL, numbers, prices and dates in en/ar/ku with or without `intl`, Latin or Arabic-Indic digits per language, the package's words in en, ar and ku.
- **Menus** in `config/frontend.php` with flocms-admin's pages pulled in; **SEO**: title, description, canonical, hreflang, Open Graph, JSON-LD.
- **Cookie notice** with categories; `site_script()` loads statistics and marketing scripts only after consent.
- **Sitemap**: the site's addresses join flocms-admin's `/sitemap.xml`, or the package serves one.
- **Starter home pages** (Studio, Slider, Shop front, Journal, Landing page, Blank), in Site → Pages with flocms-admin, else as `views/pages/index.html`; the starter picker at `/site-starter` for administrators.
- **flocms-admin**: components as Builder blocks, Settings → Site style, one sitemap (through `Admin::extend()`).
- Commands: `site:install`, `site:component`, `site:components`, `site:style`, `site:starter`, `site:doctor`.
