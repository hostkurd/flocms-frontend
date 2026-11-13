<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components;

use FloCMS\Core\TemplateEngine;

/**
 * The package's template directives, through core's TemplateEngine::extend()
 * (flocms-core 2.10, D4):
 *
 *     @component('name', [...props]) … @endcomponent
 *     @slot('footer') … @endslot
 *     @icon('arrow-right')
 *
 * Without slots, {!! component('name', [...]) !!} does the same.
 */
final class Directives
{
    /** Bump when the PHP a directive produces changes, so compiled templates are rebuilt. */
    public const VERSION = '1';

    public const NAMES = ['component', 'endcomponent', 'slot', 'endslot', 'icon'];

    public static function register(): void
    {
        TemplateEngine::extend('component', static function (?string $expression): string {
            if ($expression === null || $expression === '') {
                throw new \UnexpectedValueException('@component needs the component\'s name: @component(\'hero\', [...]).');
            }
            return '<?php \\FloCMS\\Frontend\\Components\\Slots::start(' . $expression . '); ?>';
        }, self::VERSION);
        TemplateEngine::extend('endcomponent', static fn (?string $expression): string => '<?= \\FloCMS\\Frontend\\Components\\Slots::end(); ?>', self::VERSION);
        TemplateEngine::extend('slot', static function (?string $expression): string {
            if ($expression === null || $expression === '') {
                throw new \UnexpectedValueException('@slot needs the slot\'s name: @slot(\'footer\').');
            }
            return '<?php \\FloCMS\\Frontend\\Components\\Slots::slot(' . $expression . '); ?>';
        }, self::VERSION);
        TemplateEngine::extend('endslot', static fn (?string $expression): string => '<?php \\FloCMS\\Frontend\\Components\\Slots::endSlot(); ?>', self::VERSION);
        TemplateEngine::extend('icon', static function (?string $expression): string {
            if ($expression === null || $expression === '') {
                throw new \UnexpectedValueException('@icon needs the icon\'s name: @icon(\'arrow-right\').');
            }
            return '<?= \\FloCMS\\Frontend\\Support\\Icons::svg(' . $expression . '); ?>';
        }, self::VERSION);
    }

    public static function forget(): void
    {
        foreach (self::NAMES as $name) {
            TemplateEngine::forget($name);
        }
    }
}
