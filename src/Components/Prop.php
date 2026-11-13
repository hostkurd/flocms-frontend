<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components;

use FloCMS\Frontend\Support\Html;
use FloCMS\Frontend\Support\Icons;
use FloCMS\Frontend\Support\Languages;

/**
 * One field of a component (D3): the package's own small field types, so it
 * needs nothing from flocms-admin. Each prop knows its default, how its value
 * is cleaned and checked, and which admin field it becomes in the Builder.
 *
 *     Prop::text('heading')->required()->translatable(),
 *     Prop::list('plans', [Prop::text('name')->required(), Prop::number('price')])->max(4),
 */
final class Prop
{
    public const TYPES = ['text', 'textarea', 'rich_text', 'number', 'toggle', 'select', 'image', 'link', 'date', 'colour', 'icon', 'list'];

    private bool $required = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private bool $translatable = false;
    private ?string $label = null;
    private ?string $help = null;
    /** @var array<string|int, string> */
    private array $options = [];
    private ?float $min = null;
    private ?float $max = null;
    /** @var list<Prop> */
    private array $children = [];

    private function __construct(private string $type, private string $name)
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,40}$/', $name) !== 1) {
            throw new \InvalidArgumentException('A prop name is lower-case letters, digits and _, starting with a letter: "' . $name . '".');
        }
    }

    public static function text(string $name): self { return new self('text', $name); }
    public static function textarea(string $name): self { return new self('textarea', $name); }
    /** HTML from an editor, cleaned with an allow-list when shown. */
    public static function richText(string $name): self { return new self('rich_text', $name); }
    public static function number(string $name): self { return new self('number', $name); }
    public static function toggle(string $name): self { return (new self('toggle', $name))->default(false); }
    /** @param array<string|int, string> $options value => label (a word key or text) */
    public static function select(string $name, array $options = []): self { return (new self('select', $name))->options($options); }
    /** An image: an address, a flocms-admin media id, or ['src' => …, 'alt' => …]. */
    public static function image(string $name): self { return new self('image', $name); }
    /** A link: an address, or ['url' => …, 'label' => …, 'new_tab' => bool]. */
    public static function link(string $name): self { return new self('link', $name); }
    public static function date(string $name): self { return new self('date', $name); }
    public static function colour(string $name): self { return new self('colour', $name); }
    /** One of the package's icons (site_icon()). */
    public static function icon(string $name): self { return new self('icon', $name); }
    /** @param list<Prop> $props the props of each item */
    public static function list(string $name, array $props): self
    {
        $prop = (new self('list', $name))->default([]);
        foreach ($props as $child) {
            if (!$child instanceof self || $child->type === 'list') {
                throw new \InvalidArgumentException('The items of list "' . $name . '" take props other than lists.');
            }
        }
        $prop->children = array_values($props);
        return $prop;
    }

    public function required(bool $required = true): self { $this->required = $required; return $this; }
    public function default(mixed $value): self { $this->default = $value; $this->hasDefault = true; return $this; }
    /** One value per language when edited in flocms-admin (in templates, pass the value for the page's language). */
    public function translatable(bool $translatable = true): self { $this->translatable = $translatable; return $this; }
    public function label(string $label): self { $this->label = $label; return $this; }
    public function help(string $help): self { $this->help = $help; return $this; }
    /** @param array<string|int, string> $options */
    public function options(array $options): self { $this->options = $options; return $this; }
    /** Text: the longest length; number: the highest value; list: the most items. */
    public function max(int|float $max): self { $this->max = (float) $max; return $this; }
    /** Number: the lowest value; list: the fewest items. */
    public function min(int|float $min): self { $this->min = (float) $min; return $this; }

    public function type(): string { return $this->type; }
    public function name(): string { return $this->name; }
    public function isRequired(): bool { return $this->required; }
    public function isTranslatable(): bool { return $this->translatable; }
    public function getLabel(): string { return $this->label ?? ucfirst(str_replace('_', ' ', $this->name)); }
    public function getHelp(): ?string { return $this->help; }
    /** @return array<string|int, string> */
    public function getOptions(): array { return $this->options; }
    public function getMin(): ?float { return $this->min; }
    public function getMax(): ?float { return $this->max; }
    /** @return list<Prop> */
    public function children(): array { return $this->children; }
    public function hasDefault(): bool { return $this->hasDefault; }
    public function getDefault(): mixed { return $this->default; }

    /** Whether a value counts as given (for required). */
    public function isEmpty(mixed $value): bool
    {
        return match ($this->type) {
            'toggle' => false,
            'number' => !is_numeric($value),
            'list' => !is_array($value) || $value === [],
            'image' => !is_array($value) || ($value['src'] ?? '') === '',
            'link' => !is_array($value) || ($value['url'] ?? '') === '',
            default => $value === null || (is_string($value) && trim($value) === '') || $value === [],
        };
    }

    /**
     * The value the template gets: cleaned and of the prop's type. A value
     * of the wrong shape becomes the default (or empty), never an error.
     */
    public function clean(mixed $value, ?string $lang = null): mixed
    {
        $lang ??= Languages::current();
        if ($this->translatable || (is_array($value) && $this->type !== 'list' && $this->type !== 'link' && $this->type !== 'image' && self::isLanguageMap($value))) {
            $value = self::pick($value, $lang);
        }
        if ($value === null && $this->hasDefault) {
            $value = is_array($this->default) && $this->type !== 'list' ? self::pick($this->default, $lang) : $this->default;
        }
        $max = $this->max === null ? null : (int) $this->max;
        switch ($this->type) {
            case 'text':
            case 'textarea':
                $text = is_scalar($value) ? trim((string) $value) : '';
                if ($this->type === 'text') { $text = (string) preg_replace('/\s+/u', ' ', $text); }
                return $max !== null && mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
            case 'rich_text':
                return is_string($value) ? Html::clean($value) : '';
            case 'number':
                if (!is_numeric($value)) { return $this->hasDefault && is_numeric($this->default) ? $this->default + 0 : null; }
                $number = $value + 0;
                if ($this->min !== null && $number < $this->min) { $number = is_int($number) ? (int) ceil($this->min) : $this->min; }
                if ($this->max !== null && $number > $this->max) { $number = is_int($number) ? (int) floor($this->max) : $this->max; }
                return $number;
            case 'toggle':
                return $value === true || $value === 1 || $value === '1' || $value === 'on' || $value === 'true';
            case 'select':
                $key = is_scalar($value) ? (string) $value : '';
                foreach (array_keys($this->options) as $option) {
                    if ((string) $option === $key) { return $key; }
                }
                return $this->hasDefault && is_scalar($this->default) ? (string) $this->default : '';
            case 'image':
                return self::cleanImage($value);
            case 'link':
                return self::cleanLink($value, $lang);
            case 'date':
                if ($value instanceof \DateTimeInterface) { return $value->format('Y-m-d'); }
                return is_string($value) && strtotime($value) !== false ? trim($value) : '';
            case 'colour':
                return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', trim($value)) === 1 ? strtolower(trim($value)) : '';
            case 'icon':
                return is_string($value) && Icons::has($value) ? $value : '';
            case 'list':
                if (is_string($value) && str_starts_with(ltrim($value), '[')) { $value = json_decode($value, true); }
                $items = [];
                foreach (is_array($value) ? array_values($value) : [] as $item) {
                    if (!is_array($item)) { continue; }
                    $clean = [];
                    foreach ($this->children as $child) {
                        $clean[$child->name] = $child->clean($item[$child->name] ?? null, $lang);
                    }
                    $items[] = $clean;
                    if ($max !== null && count($items) >= $max) { break; }
                }
                return $items;
        }
        return $value;
    }

    /** What is wrong with a value given in a template (null when it is fine). */
    public function problem(mixed $value): ?string
    {
        if ($this->required && ($value === null || $this->isEmpty($this->clean($value)))) {
            return 'the prop "' . $this->name . '" is required';
        }
        if ($this->type === 'select' && $value !== null && $value !== '' && $this->options !== [] && !array_key_exists((string) (is_scalar($value) ? $value : ''), $this->options)) {
            return 'the prop "' . $this->name . '" must be one of: ' . implode(', ', array_keys($this->options));
        }
        if ($this->type === 'list' && is_array($value)) {
            if ($this->min !== null && count($value) < (int) $this->min) { return 'the prop "' . $this->name . '" needs at least ' . (int) $this->min . ' items'; }
            foreach (array_values($value) as $i => $item) {
                foreach ($this->children as $child) {
                    $problem = is_array($item) ? $child->problem($item[$child->name] ?? null) : null;
                    if ($problem !== null) { return 'item ' . ($i + 1) . ' of "' . $this->name . '": ' . $problem; }
                }
            }
        }
        return null;
    }

    /** @param array<mixed> $value */
    private static function isLanguageMap(array $value): bool
    {
        if ($value === []) { return false; }
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $key) !== 1) { return false; }
        }
        return true;
    }

    /** A translatable value: the page's language, the default language, then the first one given. */
    private static function pick(mixed $value, string $lang): mixed
    {
        if (!is_array($value) || !self::isLanguageMap($value)) { return $value; }
        foreach ([$lang, Languages::default()] as $try) {
            if (isset($value[$try]) && $value[$try] !== '' && $value[$try] !== []) { return $value[$try]; }
        }
        foreach ($value as $first) {
            if ($first !== '' && $first !== null && $first !== []) { return $first; }
        }
        return null;
    }

    /** @return array{src: string, alt: string, width: ?int, height: ?int} */
    private static function cleanImage(mixed $value): array
    {
        if (is_string($value) && str_starts_with(ltrim($value), '[')) {
            $value = json_decode($value, true); // a media picker's stored ids
        }
        $empty = ['src' => '', 'alt' => '', 'width' => null, 'height' => null];
        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && function_exists('media')) {
            // A media id from flocms-admin's Media library
            $item = media($value);
            if (is_object($item) && method_exists($item, 'url')) {
                $src = (string) $item->url();
                $alt = method_exists($item, 'alt') ? (string) $item->alt() : '';
                $width = method_exists($item, 'width') ? $item->width() : null;
                $height = method_exists($item, 'height') ? $item->height() : null;
                return ['src' => Html::safeUrl($src), 'alt' => $alt, 'width' => is_int($width) ? $width : null, 'height' => is_int($height) ? $height : null];
            }
            return $empty;
        }
        if (is_array($value) && array_is_list($value) && $value !== []) {
            return self::cleanImage($value[0]); // a media picker with several images: the first
        }
        if (is_string($value)) {
            $value = ['src' => $value];
        }
        if (!is_array($value)) { return $empty; }
        $src = Html::safeUrl($value['src'] ?? $value['url'] ?? '');
        if ($src === '#') { $src = ''; }
        return [
            'src' => $src,
            'alt' => is_scalar($value['alt'] ?? null) ? trim((string) $value['alt']) : '',
            'width' => isset($value['width']) && is_numeric($value['width']) ? (int) $value['width'] : null,
            'height' => isset($value['height']) && is_numeric($value['height']) ? (int) $value['height'] : null,
        ];
    }

    /** @return array{url: string, label: string, new_tab: bool} */
    private static function cleanLink(mixed $value, string $lang): array
    {
        if (is_string($value)) {
            $value = ['url' => $value];
        }
        if (!is_array($value)) {
            return ['url' => '', 'label' => '', 'new_tab' => false];
        }
        $url = is_scalar($value['url'] ?? null) ? trim((string) $value['url']) : '';
        // Site paths get the page's language: "/contact" → /<lang>/contact
        if ($url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = Languages::url($url, $lang);
        }
        $label = $value['label'] ?? '';
        if (is_array($label)) { $label = self::pick($label, $lang); }
        return [
            'url' => Html::safeUrl($url),
            'label' => is_scalar($label) ? trim((string) $label) : '',
            'new_tab' => ($value['new_tab'] ?? false) === true || ($value['new_tab'] ?? '') === '1',
        ];
    }
}
