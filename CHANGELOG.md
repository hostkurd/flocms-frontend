# Changelog

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
