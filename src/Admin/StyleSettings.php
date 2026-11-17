<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Admin;

use FloCMS\Admin\Form\Fields\ColorPicker;
use FloCMS\Admin\Form\Fields\Select;
use FloCMS\Admin\Form\Fields\TextInput;
use FloCMS\Admin\Settings\SettingsPage;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Support\FrontendLang;

/**
 * Settings → Site style in flocms-admin: colours, fonts, corners, spacing,
 * width and dark mode. Saved values override the template's style.php; an
 * empty field keeps the template's value.
 */
final class StyleSettings
{
    public const NAME = 'site_style';

    public static function page(): SettingsPage
    {
        $t = static fn (string $key): string => FrontendLang::get('admin.' . $key);
        // The template's own values as placeholders: what an empty field keeps
        $template = array_replace_recursive(Style::DEFAULTS, Style::fileValues());
        $colour = static fn (string $name) => ColorPicker::make($name)->label($t('style.' . $name))->help($t('style.keep'))
            ->placeholder(is_string($template['colours'][$name] ?? null) ? strtolower($template['colours'][$name]) : '');
        return SettingsPage::make(self::NAME)
            ->label($t('style.title'))
            ->icon('settings')
            ->description($t('style.description'))
            ->permission('settings.manage')
            ->columns(2)
            ->schema([
                $colour('brand'),
                $colour('accent'),
                $colour('text'),
                $colour('muted'),
                $colour('background'),
                $colour('surface'),
                $colour('band'),
                $colour('line'),
                Select::make('dark')->label($t('style.dark'))->options(['' => $t('style.template_value'), 'switch' => $t('style.dark_switch'), 'auto' => $t('style.dark_auto'), 'light' => $t('style.dark_light'), 'dark' => $t('style.dark_dark')]),
                Select::make('radius')->label($t('style.radius'))->options(['' => $t('style.template_value'), 'none' => '0 px', 'small' => '4 px', 'medium' => '8 px', 'large' => '12 px', 'round' => $t('style.round')]),
                Select::make('spacing')->label($t('style.spacing'))->options(['' => $t('style.template_value'), 'compact' => $t('style.compact'), 'comfortable' => $t('style.comfortable'), 'roomy' => $t('style.roomy')]),
                TextInput::make('width')->label($t('style.width'))->numeric()->rules('min:640|max:2400')->help($t('style.width_help'))->placeholder((string) ($template['width'] ?? 1200)),
                TextInput::make('font_latin')->label($t('style.font_latin'))->help($t('style.font_help'))->rules('max:60')->placeholder((string) ($template['fonts']['latin'] ?? '')),
                TextInput::make('font_arabic')->label($t('style.font_arabic'))->help($t('style.font_help'))->rules('max:60')->placeholder((string) ($template['fonts']['arabic'] ?? '')),
            ])
            ->beforeSave(static function (array $values): array {
                Style::reset();
                return $values;
            });
    }

    /**
     * The saved values in the shape of style.php (empty ones left out).
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $saved = settings(self::NAME);
        if (!is_array($saved)) { return []; }
        $values = [];
        foreach (['brand', 'accent', 'text', 'muted', 'background', 'surface', 'band', 'line'] as $colour) {
            $value = trim((string) ($saved[$colour] ?? ''));
            if ($value !== '') { $values['colours'][$colour] = $value; }
        }
        foreach (['dark', 'radius', 'spacing'] as $key) {
            $value = trim((string) ($saved[$key] ?? ''));
            if ($value !== '') { $values[$key] = $value; }
        }
        $width = trim((string) ($saved['width'] ?? ''));
        if ($width !== '' && is_numeric($width)) { $values['width'] = (int) $width; }
        foreach (['latin', 'arabic'] as $script) {
            $font = trim((string) ($saved['font_' . $script] ?? ''));
            if ($font !== '') { $values['fonts'][$script] = $font; }
        }
        return $values;
    }
}
