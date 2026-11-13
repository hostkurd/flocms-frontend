<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Support;

/**
 * The package's own icon set: simple outline icons on a 24 px grid, drawn
 * with the current text colour. Icons that point (arrows, chevrons, "back",
 * "external") are mirrored on right-to-left pages; the others never are.
 *
 *     {!! site_icon('arrow-right') !!}
 *     {!! site_icon('search', ['label' => 'Search', 'size' => 20]) !!}
 */
final class Icons
{
    /** Icons that point along the reading direction and are mirrored in RTL. */
    public const DIRECTIONAL = ['arrow-right', 'arrow-left', 'chevron-right', 'chevron-left', 'back', 'external', 'reply', 'logout', 'login', 'send', 'list'];

    /** @var array<string, string> name => SVG drawing (paths) */
    public const ICONS = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrow-down' => '<path d="M12 5v14M18 13l-6 6-6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-up' => '<path d="m6 15 6-6 6 6"/>',
        'back' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'moon' => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
        'login' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5"/>',
        'logout' => '<path d="M15 17l5-5-5-5M20 12H8"/><path d="M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="m4 7 8 6 8-6"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
        'map-pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="1.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'tag' => '<path d="M3.5 12.5V4.5a1 1 0 0 1 1-1h8l8 8-9 9z"/><circle cx="8.5" cy="8.5" r="1.5"/>',
        'cart' => '<circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2.5 3.5h3l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L21 7.5H6.3"/>',
        'heart' => '<path d="M12 20s-7.5-4.6-7.5-10A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 7.5 3c0 5.4-7.5 10-7.5 10z"/>',
        'star' => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.8-5.2-2.8-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z"/>',
        'image' => '<rect x="3.5" y="4.5" width="17" height="15" rx="1.5"/><circle cx="9" cy="10" r="1.8"/><path d="m4 18 5.5-5.5 4 4 2.5-2.5 4.5 4.5"/>',
        'play' => '<path d="M8 5.5v13l10.5-6.5z"/>',
        'pause' => '<path d="M8.5 5v14M15.5 5v14"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.01"/>',
        'alert' => '<path d="M12 4 2.8 19.5h18.4z"/><path d="M12 10v4.5M12 17.2v.01"/>',
        'success' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.8 2.7L16 9.5"/>',
        'error' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'cookie' => '<path d="M20.5 12.5A8.5 8.5 0 1 1 11.5 3.5a3 3 0 0 0 4 4 3 3 0 0 0 5 5z"/><path d="M8.5 9.5v.01M9 15v.01M14 15.5v.01M12 12v.01"/>',
        'send' => '<path d="M4 12 20 4l-5 16-3.5-6.5z"/><path d="M11.5 13.5 20 4"/>',
        'reply' => '<path d="M9 8 4 12.5 9 17"/><path d="M4 12.5h10a6 6 0 0 1 6 6v.5"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11M4.5 6v.01M4.5 12v.01M4.5 18v.01"/>',
        'grid' => '<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/>',
        'square' => '<rect x="4" y="4" width="16" height="16" rx="1.5"/>',
        'columns' => '<rect x="3.5" y="4.5" width="17" height="15" rx="1.5"/><path d="M12 4.5v15"/>',
        'layers' => '<path d="m12 3.5 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'download' => '<path d="M12 4v11M7 10.5l5 5 5-5M5 20h14"/>',
        'share' => '<circle cx="6" cy="12" r="2.5"/><circle cx="17.5" cy="6" r="2.5"/><circle cx="17.5" cy="18" r="2.5"/><path d="m8.2 10.8 7-3.6M8.2 13.2l7 3.6"/>',
        'filter' => '<path d="M4 5.5h16l-6.2 7.3v5.7l-3.6 1.8v-7.5z"/>',
        'sliders' => '<path d="M5 6h9M18 6h1M5 12h3M12 12h7M5 18h11M20 18h-1"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'facebook' => '<path d="M14.5 21v-7h2.4l.4-3h-2.8V9.2c0-.9.3-1.5 1.5-1.5h1.4V5.1c-.3 0-1.1-.1-2.1-.1-2.1 0-3.6 1.3-3.6 3.7V11H9.4v3h2.3v7"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="3.8"/><path d="M17 7v.01"/>',
        'x' => '<path d="M4.5 4.5 19.5 19.5M19.5 4.5l-6.4 6.4M10.9 13.1 4.5 19.5"/>',
        'linkedin' => '<rect x="3.5" y="3.5" width="17" height="17" rx="2"/><path d="M8 10.5v6M8 7.5v.01M12 16.5v-6M12 13a2.5 2.5 0 0 1 5 0v3.5"/>',
        'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="3.5"/><path d="m10 9.2 5 2.8-5 2.8z"/>',
        'whatsapp' => '<path d="M4 20l1.3-4.2A8.5 8.5 0 1 1 8.4 18.9z"/><path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.5-2-1-1 .8a5 5 0 0 1-2.8-2.8l.8-1-1-2z"/>',
        'telegram' => '<path d="M20.5 4.5 3 11.2l5.5 2 2 6.3 3.2-3.8 4.8 3.6z"/><path d="m8.5 13.2 8-5.7"/>',
        'tiktok' => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4a4.5 4.5 0 0 0 4.5 4.5"/>',
    ];

    public static function has(string $name): bool
    {
        return isset(self::ICONS[$name]);
    }

    /**
     * The icon as inline SVG. Options: "size" (px, default 20), "label"
     * (text for screen readers; without it the icon is hidden from them),
     * "class" and "stroke" (line width, default 1.6).
     *
     * @param array{size?: int, label?: string, class?: string, stroke?: float|int} $options
     */
    public static function svg(string $name, array $options = []): string
    {
        $drawing = self::ICONS[$name] ?? self::ICONS['square'];
        $size = max(8, min(96, (int) ($options['size'] ?? 20)));
        $stroke = (float) ($options['stroke'] ?? 1.6);
        $label = isset($options['label']) && trim((string) $options['label']) !== '' ? (string) $options['label'] : null;
        $classes = ['fe-icon'];
        if (in_array($name, self::DIRECTIONAL, true)) { $classes[] = 'fe-icon--flip'; }
        if (isset($options['class']) && $options['class'] !== '') { $classes[] = (string) $options['class']; }
        $attributes = [
            'class' => $classes,
            'width' => $size,
            'height' => $size,
            'viewBox' => '0 0 24 24',
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-width' => rtrim(rtrim(number_format($stroke, 2, '.', ''), '0'), '.'),
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
            'focusable' => 'false',
        ];
        if ($label === null) {
            $attributes['aria-hidden'] = 'true';
        } else {
            $attributes['role'] = 'img';
            $attributes['aria-label'] = $label;
        }
        // The drawing is the package's own constant, never user input
        return '<svg' . Html::attributes($attributes) . '>' . $drawing . '</svg>';
    }
}
