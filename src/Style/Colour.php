<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Style;

/**
 * Colour maths for the Site Style (D8): sRGB hex ↔ OKLCH, even-looking
 * scales, and WCAG contrast.
 *
 * OKLCH keeps the hue and "colourfulness" while the lightness changes, so a
 * scale's steps look even and don't turn grey, unlike mixing with white or
 * black. Colours outside what a screen can show are brought back by lowering
 * the chroma, never by changing the hue.
 */
final class Colour
{
    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', trim($value)) === 1;
    }

    /** @return array{0: float, 1: float, 2: float} sRGB 0–1 */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }

    /** @param array{0: float, 1: float, 2: float} $rgb */
    public static function hex(array $rgb): string
    {
        return '#' . implode('', array_map(static fn (float $c): string => str_pad(dechex((int) round(max(0, min(1, $c)) * 255)), 2, '0', STR_PAD_LEFT), $rgb));
    }

    /** @return array{0: float, 1: float, 2: float} lightness 0–1, chroma, hue in degrees */
    public static function oklch(string $hex): array
    {
        [$r, $g, $b] = array_map([self::class, 'toLinear'], self::rgb($hex));
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        [$l, $m, $s] = [self::cbrt($l), self::cbrt($m), self::cbrt($s)];
        $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $A = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $B = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
        $C = sqrt($A * $A + $B * $B);
        $H = $C < 1e-6 ? 0.0 : fmod(rad2deg(atan2($B, $A)) + 360, 360);
        return [$L, $C, $H];
    }

    /** OKLCH to hex, lowering the chroma until the colour fits the screen's range (sRGB). */
    public static function fromOklch(float $lightness, float $chroma, float $hue): string
    {
        $lightness = max(0.0, min(1.0, $lightness));
        $rgb = self::oklchToRgb($lightness, $chroma, $hue);
        if (self::inGamut($rgb)) {
            return self::hex($rgb);
        }
        $low = 0.0;
        $high = $chroma;
        for ($i = 0; $i < 20; $i++) {
            $mid = ($low + $high) / 2;
            if (self::inGamut(self::oklchToRgb($lightness, $mid, $hue))) { $low = $mid; } else { $high = $mid; }
        }
        return self::hex(self::oklchToRgb($lightness, $low, $hue));
    }

    /** The same colour at another lightness (0–1). */
    public static function withLightness(string $hex, float $lightness, ?float $chromaFactor = null): string
    {
        [, $c, $h] = self::oklch($hex);
        return self::fromOklch($lightness, $chromaFactor === null ? $c : $c * $chromaFactor, $h);
    }

    /** Lighter (positive) or darker (negative) by an OKLCH lightness step. */
    public static function shift(string $hex, float $step): string
    {
        [$l, $c, $h] = self::oklch($hex);
        return self::fromOklch($l + $step, $c, $h);
    }

    /**
     * A scale of ten steps (50 … 900): light tints to dark shades, the hue
     * kept, the chroma eased at both ends.
     *
     * @return array<int, string> step => hex
     */
    public static function scale(string $hex): array
    {
        [, $c, $h] = self::oklch($hex);
        $steps = [50 => 0.975, 100 => 0.94, 200 => 0.87, 300 => 0.78, 400 => 0.68, 500 => 0.58, 600 => 0.50, 700 => 0.42, 800 => 0.34, 900 => 0.26];
        $scale = [];
        foreach ($steps as $step => $lightness) {
            $ease = $lightness > 0.9 ? 0.35 : ($lightness > 0.8 ? 0.7 : 1.0);
            $scale[$step] = self::fromOklch($lightness, $c * $ease, $h);
        }
        return $scale;
    }

    /** Mix two colours (0 = $a, 1 = $b), in OKLab so the middle doesn't go grey. */
    public static function mix(string $a, string $b, float $amount): string
    {
        [$l1, $c1, $h1] = self::oklch($a);
        [$l2, $c2, $h2] = self::oklch($b);
        $a1 = $c1 * cos(deg2rad($h1)); $b1 = $c1 * sin(deg2rad($h1));
        $a2 = $c2 * cos(deg2rad($h2)); $b2 = $c2 * sin(deg2rad($h2));
        $l = $l1 + ($l2 - $l1) * $amount;
        $A = $a1 + ($a2 - $a1) * $amount;
        $B = $b1 + ($b2 - $b1) * $amount;
        $c = sqrt($A * $A + $B * $B);
        $h = $c < 1e-6 ? 0.0 : fmod(rad2deg(atan2($B, $A)) + 360, 360);
        return self::fromOklch($l, $c, $h);
    }

    /** WCAG 2 relative luminance. */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map([self::class, 'toLinear'], self::rgb($hex));
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** WCAG 2 contrast ratio (1 to 21). */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * The text colour for a background: white or $dark, whichever reads
     * better (white when it reaches 4.5 : 1, as on the approved buttons).
     */
    public static function on(string $background, string $dark = '#0E1A24', string $light = '#FFFFFF'): string
    {
        if (self::contrast($light, $background) >= 4.5) {
            return strtoupper($light);
        }
        return strtoupper(self::contrast($dark, $background) >= self::contrast($light, $background) ? $dark : $light);
    }

    private static function toLinear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function fromLinear(float $c): float
    {
        return $c <= 0.0031308 ? 12.92 * $c : 1.055 * ($c ** (1 / 2.4)) - 0.055;
    }

    private static function cbrt(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function oklchToRgb(float $L, float $C, float $H): array
    {
        $A = $C * cos(deg2rad($H));
        $B = $C * sin(deg2rad($H));
        $l = ($L + 0.3963377774 * $A + 0.2158037573 * $B) ** 3;
        $m = ($L - 0.1055613458 * $A - 0.0638541728 * $B) ** 3;
        $s = ($L - 0.0894841775 * $A - 1.2914855480 * $B) ** 3;
        $r = 4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
        $g = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
        $b = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;
        return [self::fromLinear($r), self::fromLinear($g), self::fromLinear($b)];
    }

    /** @param array{0: float, 1: float, 2: float} $rgb */
    private static function inGamut(array $rgb): bool
    {
        foreach ($rgb as $c) {
            if (is_nan($c) || $c < -0.0005 || $c > 1.0005) { return false; }
        }
        return true;
    }
}
