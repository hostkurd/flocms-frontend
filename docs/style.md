# Site Style

The whole look of a site comes from one short list of values. PHP turns it into one CSS file: no Node, Tailwind or Vite step, so it works on plain shared hosting.

## Where the values live

In the active template: `templates/<APP_TEMPLATE>/style.php` (else `templates/default/style.php`, else the package's defaults). Switching the template switches the style. With flocms-admin, Settings → Site style overrides the file's values; an empty field there keeps the file's value.

```php
return [
    'colours' => [
        'brand' => '#0A7FA8',        // buttons and links
        'accent' => '#38BBE0',       // buttons on the navy band and in dark mode (with dark text)
        'text' => '#0E1A24',
        'muted' => '#56616C',        // secondary text
        'background' => '#FFFFFF',
        'surface' => '#F6F8FA',      // the grey rows
        'band' => '#0C2233',         // the navy closing band and the footer
        'line' => '#E4E7EB',
        'line_strong' => '#CDD3DA',
        // 'success' => '#1E7A4F', 'warning' => '#9A5B00', 'danger' => '#C0352B',
    ],
    'dark_colours' => [],            // any dark-mode colour by hand: background, surface, text, muted, line, band, brand, link …
    'dark' => 'switch',              // switch | auto | light | dark
    'fonts' => ['latin' => 'Instrument Sans', 'arabic' => 'Noto Sans Arabic', 'headings' => null],
    'font_files' => [],              // fonts of your own: ['family' => 'Vazirmatn', 'file' => 'fonts/vazirmatn.woff2', 'weight' => '100 900', 'range' => 'U+0600-06FF']
    'radius' => 'small',             // none | small (4 px) | medium (8) | large (12) | round
    'spacing' => 'comfortable',      // compact | comfortable | roomy
    'width' => 1200,                 // px, 640 to 2400
];
```

A wrong value falls back to the default; `php flo site:doctor` and `php flo site:style` say which.

## What the package works out

- **Scales**: `--fe-brand-50` … `--fe-brand-900`, computed in OKLCH so the steps look even and don't turn grey.
- **Dark mode**: background, surface, text, lines and the rest, worked out from the light colours unless set in `dark_colours`. The brand colour in dark mode is the accent (the logo blue), always with dark text.
- **Text on colours**: white or dark on each background, whichever passes WCAG contrast. `php flo site:style --check` lists any pair below AA (4.5 : 1 for text).
- **Hover, focus, notice colours**, and the muted text and lines of the navy band.

## Dark mode

- `switch` (the default): a light/dark button in the header. The choice is kept in a cookie and applied on the server (`data-theme` on `<html>`), so the page never flashes the wrong colours. Until the visitor chooses, the device's setting is followed.
- `auto`: follows the device, no button.
- `light`, `dark`: always one.

## Fonts

The package ships Instrument Sans (Latin) and Noto Sans Arabic (Arabic and Kurdish Sorani), self-hosted: no call to Google Fonts. The page's language picks the font (`fe-script-arabic` on `<html>` for Arabic-script languages, which also get a slightly larger size and line height); a word in the other script still gets its own font through each file's `unicode-range`.

Other fonts: put the files in `public/themes/<template>/fonts/` and list them in `font_files`, then name the family in `fonts`.

## The CSS variables

Everything the components use is a variable, so your own CSS can use it too:

| Variable | What it is |
|---|---|
| `--fe-brand`, `--fe-on-brand`, `--fe-brand-hover`, `--fe-brand-50` … `-900` | buttons and links |
| `--fe-accent`, `--fe-on-accent` | the logo blue |
| `--fe-text`, `--fe-muted`, `--fe-background`, `--fe-surface`, `--fe-line`, `--fe-line-strong` | text and surfaces |
| `--fe-band`, `--fe-band-text`, `--fe-band-muted`, `--fe-band-line` | the navy band and footer |
| `--fe-link`, `--fe-link-hover`, `--fe-focus` | links and the focus ring |
| `--fe-success`, `--fe-warning`, `--fe-danger` (+ `-bg`) | notices |
| `--fe-radius`, `--fe-radius-lg`, `--fe-width`, `--fe-gutter`, `--fe-section`, `--fe-space-1` … `-8` | shape and spacing |
| `--fe-font`, `--fe-font-headings`, `--fe-size`, `--fe-leading` | type |

flocms-admin's blocks read their own variables (`--flo-brand`, `--flo-radius`, `--flo-space-*` …); the style sets them from these, so blocks and components look alike on one page.

## The file

`storage/cache/frontend/style-<16 hex>.css`, served at `/site-assets/file/…` with a one-year cache. Its name changes when the values, the package or a component's CSS change, so visitors never keep an old one. When `storage/` can't be written, the CSS is built on each request (slower; `site:doctor` reports it).
