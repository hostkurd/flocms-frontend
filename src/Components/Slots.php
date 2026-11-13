<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components;

/**
 * What @component … @endcomponent compiles to: the content between the tags
 * is the default slot, @slot('name') … @endslot fills a named slot.
 *
 *     @component('pricing', ['heading' => 'Plans'])
 *         <p>Prices include VAT.</p>
 *         @slot('footer') <a href="/faq">Questions?</a> @endslot
 *     @endcomponent
 */
final class Slots
{
    /** @var list<array{name: string, props: array<string, mixed>, slots: array<string, string>, slot: ?string, level: int}> */
    private static array $stack = [];

    /** @param array<string, mixed> $props */
    public static function start(string $name, array $props = []): void
    {
        ob_start();
        self::$stack[] = ['name' => $name, 'props' => $props, 'slots' => [], 'slot' => null, 'level' => ob_get_level()];
    }

    public static function slot(string $slot): void
    {
        $top = array_key_last(self::$stack);
        if ($top === null) {
            throw new ComponentException('@slot(\'' . $slot . '\') is outside @component … @endcomponent.');
        }
        if (self::$stack[$top]['slot'] !== null) {
            self::endSlot();
        }
        if (preg_match('/^[a-z][a-z0-9_]{0,40}$/', $slot) !== 1 || $slot === 'default') {
            throw new ComponentException('A slot name is lower-case letters, digits and _ ("default" is the content between the tags): "' . $slot . '".');
        }
        ob_start();
        self::$stack[$top]['slot'] = $slot;
    }

    public static function endSlot(): void
    {
        $top = array_key_last(self::$stack);
        if ($top === null || self::$stack[$top]['slot'] === null) {
            throw new ComponentException('@endslot without @slot.');
        }
        self::$stack[$top]['slots'][self::$stack[$top]['slot']] = trim((string) ob_get_clean());
        self::$stack[$top]['slot'] = null;
    }

    public static function end(): string
    {
        $top = array_key_last(self::$stack);
        if ($top === null) {
            throw new ComponentException('@endcomponent without @component.');
        }
        if (self::$stack[$top]['slot'] !== null) {
            self::endSlot();
        }
        // Buffers a template opened inside and left open are closed with it
        while (ob_get_level() > self::$stack[$top]['level']) {
            ob_end_clean();
        }
        $default = trim((string) ob_get_clean());
        $frame = array_pop(self::$stack);
        $slots = $frame['slots'];
        if ($default !== '') {
            $slots['default'] = $default;
        }
        return Components::render($frame['name'], $frame['props'], $slots);
    }

    /** @return list<string> the components started and not ended yet */
    public static function open(): array
    {
        return array_map(static fn (array $frame): string => $frame['name'], self::$stack);
    }

    /** For tests and error pages: drop unfinished components. */
    public static function reset(): void
    {
        while (self::$stack !== []) {
            $frame = array_pop(self::$stack);
            while (ob_get_level() >= $frame['level'] && ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }
}
