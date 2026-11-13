# Components

A component is written once and used everywhere: in templates, and as a Builder block when flocms-admin is installed.

## A component is a folder

```
components/pricing/
    Pricing.php      the class: name, props, data
    pricing.html     the template (FloCMS template syntax)
    pricing.css      optional: linked only on pages that use it
    pricing.js       optional: the same
```

`php flo site:component Pricing --css --js` creates one. The class is in the `App\Components` namespace and is loaded from the folder by itself (no autoload entry needed).

```php
<?php
namespace App\Components;

use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Prop;

final class Pricing extends Component
{
    protected static string $name = 'pricing-table';   // lower-case words joined with "-"; also the folder's name
    protected static string $icon = 'tag';             // one of the package's icons (site_icon())
    protected static bool $block = true;               // a Builder block when flocms-admin is installed

    public function props(): array
    {
        return [
            Prop::text('heading')->required()->translatable(),
            Prop::list('plans', [
                Prop::text('name')->required(),
                Prop::number('price'),
                Prop::link('button'),
            ])->max(4),
            Prop::toggle('highlight_middle')->default(true),
        ];
    }

    /** Optional: more values for the template, from the cleaned props. */
    public function data(array $props): array
    {
        return ['currency' => 'USD'];
    }
}
```

The template gets every prop as a variable, the values of `data()`, `$slot` (the content between the tags), `$slots` (named slots), `$id` and `$class`:

```html
<section class="fe-section pricing{{ $class !== '' ? ' ' . $class : '' }}"@if($id !== '') id="{{ $id }}"@endif>
  <div class="fe-container">
    <h2>{{ $heading }}</h2>
    @foreach($plans as $plan)
      <p>{{ $plan['name'] }}: {{ site_money($plan['price'], $currency) }}</p>
      @if($plan['button']['url'] !== '')<a class="fe-button" href="{{ $plan['button']['url'] }}">{{ $plan['button']['label'] }}</a>@endif
    @endforeach
    {!! $slot !!}
  </div>
</section>
```

Instead of a template, a component can build its markup in PHP: `public function html(array $vars): ?string`.

## Props

| Prop | The template gets | In the Builder |
|---|---|---|
| `Prop::text('x')` | text on one line | TextInput |
| `Prop::textarea('x')` | text with line breaks | Textarea |
| `Prop::richText('x')` | HTML, cleaned with an allow-list (print with `{!! !!}`) | RichEditor (Textarea in a list) |
| `Prop::number('x')` | int or float, or null | TextInput (numbers) |
| `Prop::toggle('x')` | bool | Toggle |
| `Prop::select('x', ['a' => 'Label'])` | one of the keys | Select |
| `Prop::image('x')` | `['src', 'alt', 'width', 'height']` from an address, a media id or an array | MediaPicker |
| `Prop::link('x')` | `['url', 'label', 'new_tab']`; site paths get the page's language | two TextInputs, `x_label` and `x_url` |
| `Prop::date('x')` | the date as written | Date |
| `Prop::colour('x')` | `#rrggbb` or '' | ColorPicker |
| `Prop::icon('x')` | an icon name or '' | Select of the icons |
| `Prop::list('x', [...props])` | a list of items, each cleaned by its props | Repeater |

Every prop takes `->required()`, `->default($value)`, `->label('…')`, `->help('…')`; text and lists `->max(n)`, numbers and lists `->min(n)`. `->translatable()` holds one value per language when edited in the admin; in a template, pass the value for the page's language, or `['en' => '…', 'ar' => '…']` and the page's language is picked.

Props are checked on every render: a missing required prop or an unknown prop is an error with `APP_DEBUG` on, and in production the component renders without it and logs one line (`storage/logs`).

## Using a component

```html
@component('pricing-table', ['heading' => 'Plans', 'plans' => $plans])
    <p>Prices include VAT.</p>
    @slot('footer') <a href="/faq">Questions?</a> @endslot
@endcomponent

{!! component('button', ['label' => 'Contact us', 'url' => '/contact']) !!}
```

Components can be nested inside slots. `@@component` prints a literal `@component`.

## Replacing or restyling the package's components

A folder with the name of one of the package's components replaces its files, one by one:

- `components/hero/hero.html` alone: your markup, the package's props and CSS.
- `components/hero/hero.css` alone: your CSS instead of the package's, linked on the pages that show a hero.
- `components/hero/Hero.php`: your class replaces the package's (keep `$name = 'hero'`).

`php flo site:components` lists every component, where it comes from and which of its files the site changed.

## CSS and JavaScript

The package's own components' CSS is part of the Site Style file. Your components' CSS, and the JavaScript of every component, is collected while the page renders: `site_head()` links one combined file of the CSS, `site_foot()` one combined script, named by their contents and cached for a year. A page that needs no JavaScript gets none.

Each JS file runs once per page, inside its own function. Write it for any number of the component on the page, and for none:

```js
document.querySelectorAll('.pricing').forEach(function (element) { … });
```

Use logical CSS properties (`margin-inline-start`, `inset-inline-end`, `text-align: start`) so the component works in both directions, and the `--fe-*` variables of the Site Style so it follows the site's colours, spacing and dark mode.

## For packages

Another package adds its components from its bootstrap: `\FloCMS\Frontend\Components\Components::register(MyComponent::class);`. The site's own folder still wins.
