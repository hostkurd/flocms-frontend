<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Starters;

use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Languages;

/**
 * Ready-made home pages built from the package's components (D25, D26):
 * Studio, Slider, Shop front, Journal, Landing page and Blank.
 *
 * Without flocms-admin's Pages a starter is written as a template
 * (views/pages/index.html) the developer owns; with them it becomes the
 * home page in Site → Pages, built from Builder blocks. Either way it is
 * created once: the choice is kept in storage/frontend/starter.json.
 */
final class Starters
{
    public const NAMES = ['studio', 'slider', 'shop', 'journal', 'landing', 'blank'];

    /**
     * The sections of a starter: [component, props], text as "t:key"
     * (resolved to one value per language).
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public static function sections(string $name): array
    {
        $cards = ['heading' => 't:services', 'items' => [
            ['heading' => 't:card1', 'text' => 't:card1_text', 'link' => ['url' => '/services', 'label' => 't:more']],
            ['heading' => 't:card2', 'text' => 't:card2_text', 'link' => ['url' => '/services', 'label' => 't:more']],
            ['heading' => 't:card3', 'text' => 't:card3_text', 'link' => ['url' => '/services', 'label' => 't:more']],
        ]];
        $pricing = ['heading' => 't:pricing', 'highlight_label' => 't:popular', 'note' => 't:vat', 'tone' => 'surface', 'plans' => [
            ['name' => 't:plan1', 'price' => 't:plan1_price', 'period' => 't:per', 'features' => 't:plan1_features', 'button' => ['url' => '/contact', 'label' => 't:choose']],
            ['name' => 't:plan2', 'price' => 't:plan2_price', 'period' => 't:per', 'features' => 't:plan2_features', 'button' => ['url' => '/contact', 'label' => 't:choose'], 'highlight' => true],
            ['name' => 't:plan3', 'price' => 't:plan3_price', 'period' => 't:per', 'features' => 't:plan3_features', 'button' => ['url' => '/contact', 'label' => 't:choose']],
        ]];
        $faq = ['heading' => 't:faq', 'items' => [['question' => 't:q1', 'answer' => 't:a1'], ['question' => 't:q2', 'answer' => 't:a2'], ['question' => 't:q3', 'answer' => 't:a3']]];
        $cta = ['heading' => 't:band', 'text' => 't:band_text', 'primary' => ['url' => '/contact', 'label' => 't:contact']];
        $features = ['heading' => 't:why', 'items' => [
            ['icon' => 'user', 'heading' => 't:f1', 'text' => 't:f1_text'], ['icon' => 'tag', 'heading' => 't:f2', 'text' => 't:f2_text'], ['icon' => 'clock', 'heading' => 't:f3', 'text' => 't:f3_text'],
        ]];
        return match ($name) {
            'studio' => [
                ['hero', ['eyebrow' => 't:eyebrow', 'heading' => 't:h1', 'text' => 't:lead', 'primary' => ['url' => '/projects', 'label' => 't:cta1'], 'secondary' => ['url' => '/contact', 'label' => 't:cta2']]],
                ['cards', $cards + ['photos' => true]],
                ['pricing', $pricing],
                ['faq', $faq],
                ['call-to-action', $cta],
            ],
            'slider' => [
                ['slider', ['slides' => [
                    ['eyebrow' => 't:eyebrow', 'heading' => 't:slide1', 'text' => 't:slide1_text', 'link' => ['url' => '/projects', 'label' => 't:cta1']],
                    ['heading' => 't:slide2', 'text' => 't:slide2_text', 'link' => ['url' => '/services', 'label' => 't:more']],
                    ['heading' => 't:slide3', 'text' => 't:slide3_text', 'link' => ['url' => '/contact', 'label' => 't:cta2']],
                ]]],
                ['cards', $cards + ['tone' => 'surface']],
                ['features', $features],
                ['call-to-action', $cta],
            ],
            'shop' => [
                ['hero', ['heading' => 't:shop_h1', 'text' => 't:shop_lead', 'layout' => 'centered', 'primary' => ['url' => '/shop', 'label' => 't:shop_new']]],
                ['cards', ['heading' => 't:shop_cats', 'columns' => 4, 'items' => [
                    ['heading' => 't:cat1', 'link' => ['url' => '/shop', 'label' => '']], ['heading' => 't:cat2', 'link' => ['url' => '/shop', 'label' => '']],
                    ['heading' => 't:cat3', 'link' => ['url' => '/shop', 'label' => '']], ['heading' => 't:cat4', 'link' => ['url' => '/shop', 'label' => '']],
                ]]],
                ['cards', ['heading' => 't:shop_new', 'columns' => 4, 'tone' => 'surface', 'items' => [
                    ['heading' => 't:p1', 'text' => 't:p1_text', 'link' => ['url' => '/shop', 'label' => '']], ['heading' => 't:p2', 'text' => 't:p2_text', 'link' => ['url' => '/shop', 'label' => '']],
                    ['heading' => 't:p3', 'text' => 't:p3_text', 'link' => ['url' => '/shop', 'label' => '']], ['heading' => 't:p4', 'text' => 't:p4_text', 'link' => ['url' => '/shop', 'label' => '']],
                ]]],
                ['call-to-action', ['heading' => 't:shop_band', 'text' => 't:shop_band_text', 'primary' => ['url' => '/contact', 'label' => 't:contact']]],
            ],
            'journal' => [
                ['hero', ['heading' => 't:journal_h1', 'text' => 't:journal_lead', 'primary' => ['url' => '/blog', 'label' => 't:read']]],
                ['cards', ['heading' => 't:latest', 'tone' => 'surface', 'items' => [
                    ['heading' => 't:post1', 'text' => 't:post1_text', 'link' => ['url' => '/blog', 'label' => 't:read']],
                    ['heading' => 't:post2', 'text' => 't:post2_text', 'link' => ['url' => '/blog', 'label' => 't:read']],
                    ['heading' => 't:post3', 'text' => 't:post3_text', 'link' => ['url' => '/blog', 'label' => 't:read']],
                ]]],
                ['call-to-action', $cta],
            ],
            'landing' => [
                ['hero', ['heading' => 't:landing_h1', 'text' => 't:landing_lead', 'layout' => 'centered', 'primary' => ['url' => '/contact', 'label' => 't:landing_cta']]],
                ['features', $features],
                ['pricing', $pricing],
                ['faq', $faq],
                ['call-to-action', $cta + ['primary' => ['url' => '/contact', 'label' => 't:landing_cta']]],
            ],
            default => [],
        };
    }

    /**
     * A starter's sections with the text in the site's languages
     * (['en' => …, 'ar' => …] where the site has more than one).
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public static function resolved(string $name, ?array $languages = null): array
    {
        $languages ??= Languages::all();
        $texts = self::texts();
        $resolve = static function (mixed $value) use (&$resolve, $texts, $languages): mixed {
            if (is_array($value)) {
                return array_map($resolve, $value);
            }
            if (!is_string($value) || !str_starts_with($value, 't:')) {
                return $value;
            }
            $key = substr($value, 2);
            $map = [];
            foreach ($languages as $lang) {
                $map[$lang] = $texts[$lang][$key] ?? $texts['en'][$key] ?? '';
            }
            return count($map) === 1 ? reset($map) : $map;
        };
        return array_map(static fn (array $section): array => [$section[0], $resolve($section[1])], self::sections($name));
    }

    /** @return array<string, array<string, string>> */
    public static function texts(): array
    {
        $texts = require Frontend::path('resources/starters/texts.php');
        return is_array($texts) ? $texts : [];
    }

    /** The template file of a starter, made of @component calls (for views/pages/index.html). */
    public static function template(string $name): string
    {
        $out = '';
        foreach (self::resolved($name) as [$component, $props]) {
            $out .= "@component('" . $component . "', " . self::export($props, 0) . ")\n@endcomponent\n\n";
        }
        return $out === '' ? "<div class=\"fe-section\"></div>\n" : $out;
    }

    /**
     * The Builder blocks of a starter, per language, in flocms-admin's format
     * ({"en": [nodes], "ar": [nodes]}): one site_<component> block per section.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function blocks(string $name): array
    {
        $blocks = [];
        foreach (Languages::all() as $lang) {
            $nodes = [];
            foreach (self::resolved($name, [$lang]) as $i => [$component, $props]) {
                $nodes[] = ['id' => 'b' . substr(sha1($name . $lang . $i . $component), 0, 11), 'type' => 'site_' . str_replace('-', '_', $component), 'data' => self::blockData($component, $props), 'settings' => []];
            }
            $blocks[$lang] = $nodes;
        }
        return $blocks;
    }

    /**
     * Props in the shape of the block's stored fields: a link prop is two
     * fields (<name>_label, <name>_url), as ComponentBlock has it.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public static function blockData(string $component, array $props): array
    {
        $data = [];
        foreach ($props as $key => $value) {
            if (is_array($value) && array_key_exists('url', $value) && array_key_exists('label', $value) && !array_is_list($value)) {
                $data[$key . '_label'] = (string) $value['label'];
                $data[$key . '_url'] = (string) $value['url'];
            } elseif (is_array($value) && array_is_list($value)) {
                $data[$key] = array_map(static fn (mixed $item): mixed => is_array($item) ? self::blockData($component, $item) : $item, $value);
            } elseif (is_bool($value)) {
                $data[$key] = $value ? '1' : '0';
            } else {
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /** PHP array syntax, short and indented, for the written template. */
    private static function export(mixed $value, int $depth): string
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }
        $pad = str_repeat('    ', $depth + 1);
        $lines = [];
        $list = array_is_list($value);
        foreach ($value as $key => $item) {
            $lines[] = $pad . ($list ? '' : var_export($key, true) . ' => ') . self::export($item, $depth + 1);
        }
        return "[\n" . implode(",\n", $lines) . ",\n" . str_repeat('    ', $depth) . ']';
    }
}
