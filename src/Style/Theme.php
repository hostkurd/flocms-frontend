<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Style;

/**
 * Light or dark for this visitor. With 'dark' => 'switch' (or 'auto') the
 * light/dark switch keeps the visitor's choice in a cookie, applied on the
 * server (data-theme on <html>), so the page never flashes the wrong colours.
 */
final class Theme
{
    public const COOKIE = 'fe_theme';

    /** "light", "dark", or null to follow the device. */
    public static function chosen(): ?string
    {
        $mode = Style::values()['dark'];
        if ($mode === 'light' || $mode === 'dark') {
            return null; // fixed by the style: no switch
        }
        $value = $_COOKIE[self::COOKIE] ?? null;
        return in_array($value, ['light', 'dark'], true) ? $value : null;
    }

    /** Whether the page shows a light/dark switch. */
    public static function hasSwitch(): bool
    {
        return Style::values()['dark'] === 'switch';
    }
}
