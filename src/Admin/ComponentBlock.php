<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Admin;

use FloCMS\Admin\Blocks\Block;
use FloCMS\Admin\Form\Fields\ColorPicker;
use FloCMS\Admin\Form\Fields\Date;
use FloCMS\Admin\Form\Fields\MediaPicker;
use FloCMS\Admin\Form\Fields\Repeater;
use FloCMS\Admin\Form\Fields\RichEditor;
use FloCMS\Admin\Form\Fields\Select;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Form\Fields\Textarea;
use FloCMS\Admin\Form\Fields\Toggle;
use FloCMS\Admin\Form\Field;
use FloCMS\Frontend\Components\Component;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Support\FrontendLang;
use FloCMS\Frontend\Support\Icons;

/**
 * One component as a flocms-admin Builder block ("one component, used
 * everywhere"): its props become the block's fields, and the website shows
 * the component's own template, so it looks the same in the Builder's pages
 * and in the site's templates.
 *
 * Fields per prop: text → TextInput, textarea → Textarea, rich text →
 * RichEditor (Textarea inside a list), number → TextInput (numeric), toggle →
 * Toggle, select → Select, image → MediaPicker, link → two TextInputs
 * (<name>_label, <name>_url), date → Date, colour → ColorPicker, icon →
 * Select of the icons, list → Repeater.
 */
final class ComponentBlock extends Block
{
    /** The package's icons and the closest one of the panel's. */
    private const ADMIN_ICONS = ['layers' => 'layers', 'grid' => 'grid', 'image' => 'image', 'menu' => 'menu', 'columns' => 'columns', 'link' => 'link', 'info' => 'info',
        'list' => 'list', 'tag' => 'tag', 'check' => 'check', 'arrow-right' => 'arrow-right', 'square' => 'square', 'globe' => 'globe', 'cookie' => 'shield', 'moon' => 'eye', 'chevron-right' => 'chevron-right'];

    public function __construct(private string $component)
    {
    }

    public function component(): string { return $this->component; }

    public function blockType(): string { return 'site_' . str_replace('-', '_', $this->component); }

    public function blockLabel(): string { return $this->class()::label(); }

    public function blockIcon(): string { return self::ADMIN_ICONS[$this->class()::icon()] ?? 'square'; }

    /** The panel's "Your site" group, so the site's components are found together. */
    public function blockCategory(): string { return 'site'; }

    public function blockDescription(): string { return (string) ($this->class()::description() ?? ''); }

    /** Components print their own section and container. */
    public function defaultWidth(): string { return 'full'; }

    public function cacheable(): bool { return $this->class()::cacheable(); }

    public function fields(): array
    {
        $fields = [];
        foreach ($this->instance()->props() as $prop) {
            foreach (self::fieldsFor($prop, false) as $field) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    public function render(array $vars): ?string
    {
        $props = [];
        foreach ($this->instance()->props() as $prop) {
            $props[$prop->name()] = self::valueFor($prop, $vars);
        }
        $anchor = $vars['block']->anchor ?? '';
        if (is_string($anchor) && $anchor !== '') { $props['id'] = $anchor; }
        return Components::render($this->component, $props);
    }

    /** @return list<Field> */
    private static function fieldsFor(Prop $prop, bool $inList): array
    {
        $name = $prop->name();
        $label = self::fieldLabel($prop);
        $field = match ($prop->type()) {
            'text' => TextInput::make($name),
            'textarea' => Textarea::make($name)->rows(3),
            'rich_text' => $inList ? Textarea::make($name)->rows(4) : RichEditor::make($name),
            'number' => TextInput::make($name)->numeric(),
            'toggle' => Toggle::make($name),
            'select' => Select::make($name)->options(array_map(static fn (string $text): string => FrontendLang::label($text), $prop->getOptions())),
            'image' => MediaPicker::make($name),
            'date' => Date::make($name),
            'colour' => ColorPicker::make($name),
            'icon' => Select::make($name)->options(['' => '—'] + array_combine(array_keys(Icons::ICONS), array_keys(Icons::ICONS))),
            'link' => null,
            'list' => Repeater::make($name)->schema(array_merge(...array_map(static fn (Prop $child): array => self::fieldsFor($child, true), $prop->children()))),
            default => TextInput::make($name),
        };
        if ($field === null) {
            // A link is its text and its address
            return [
                TextInput::make($name . '_label')->label($label)->rules('max:80'),
                TextInput::make($name . '_url')->label($label . ' · ' . FrontendLang::get('admin.link')),
            ];
        }
        $field->label($label);
        if ($prop->getHelp() !== null) { $field->help(FrontendLang::label($prop->getHelp())); }
        if ($prop->isRequired() && $prop->type() !== 'list') { $field->required(); }
        if ($prop->hasDefault() && !is_array($prop->getDefault())) {
            $default = $prop->getDefault();
            $field->default(is_bool($default) ? ($default ? '1' : '0') : $default);
        }
        if ($prop->getMax() !== null && in_array($prop->type(), ['text', 'textarea'], true)) { $field->rules('max:' . (int) $prop->getMax()); }
        if ($field instanceof Repeater && $prop->getMax() !== null) { $field->maxItems((int) $prop->getMax()); }
        return [$field];
    }

    /** The prop's value from the block's stored fields. */
    private static function valueFor(Prop $prop, array $vars): mixed
    {
        $name = $prop->name();
        if ($prop->type() === 'link') {
            return ['url' => (string) ($vars[$name . '_url'] ?? ''), 'label' => (string) ($vars[$name . '_label'] ?? '')];
        }
        $value = $vars[$name] ?? null;
        if ($prop->type() === 'list') {
            if (is_string($value) && str_starts_with(ltrim($value), '[')) { $value = json_decode($value, true); }
            $items = [];
            foreach (is_array($value) ? $value : [] as $item) {
                if (!is_array($item)) { continue; }
                $clean = [];
                foreach ($prop->children() as $child) { $clean[$child->name()] = self::valueFor($child, $item); }
                $items[] = $clean;
            }
            return $items;
        }
        return $value === '' ? null : $value;
    }

    private static function fieldLabel(Prop $prop): string
    {
        $key = 'props.' . $prop->name();
        return FrontendLang::has($key) ? FrontendLang::get($key) : FrontendLang::label($prop->getLabel());
    }

    /** @return class-string<Component> */
    private function class(): string
    {
        return Components::all()[$this->component] ?? \FloCMS\Frontend\Components\Library\Section::class;
    }

    private function instance(): Component
    {
        return Components::find($this->component) ?? new \FloCMS\Frontend\Components\Library\Section();
    }
}
