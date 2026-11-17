<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components;

use FloCMS\Frontend\Support\FrontendLang;

/**
 * A component: written once, used in templates (@component, component()) and,
 * when flocms-admin is installed and $block is true, as a Builder block.
 *
 * It is a folder (D2): the class, its template <name>.html and, when it needs
 * them, <name>.css and <name>.js. A site's components/<name>/ folder replaces
 * any of those files of a package's component of the same name.
 *
 *     final class Pricing extends Component
 *     {
 *         protected static string $name = 'pricing';
 *         protected static string $icon = 'tag';
 *         protected static bool $block = true;
 *
 *         public function props(): array
 *         {
 *             return [Prop::text('heading')->required()->translatable(), …];
 *         }
 *     }
 */
abstract class Component
{
    /** Lower-case words joined with "-": pricing, cookie-notice. Also its folder and file names. */
    protected static string $name = '';

    /** A word key ("frontend.components.hero") or text. Default: the name. */
    protected static ?string $label = null;

    /** One of the package's icons, shown in the Builder. */
    protected static string $icon = 'square';

    /** The Builder's group: layout, content, media, navigation, site. */
    protected static string $category = 'content';

    protected static ?string $description = null;

    /** Offered as a Builder block when flocms-admin is installed. */
    protected static bool $block = false;

    /** Whether the Builder may cache its HTML (false for anything that differs per visitor or page). */
    protected static bool $cacheable = true;

    /** Props every component takes: an id for links to it, extra classes. */
    public const COMMON_PROPS = ['id', 'class'];

    /** @return list<Prop> */
    public function props(): array
    {
        return [];
    }

    /**
     * Extra values for the template, worked out from the cleaned props.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public function data(array $props): array
    {
        return [];
    }

    /**
     * The component's markup built in PHP instead of a template; null (the
     * default) uses <name>.html.
     *
     * @param array<string, mixed> $vars the props, data(), $slot and $slots
     */
    public function html(array $vars): ?string
    {
        return null;
    }

    public static function name(): string
    {
        $name = static::$name;
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $name) !== 1 || strlen($name) > 36) {
            throw new \LogicException(static::class . '::$name must be lower-case words joined with "-" (at most 36 characters), e.g. "pricing" or "cookie-notice".');
        }
        return $name;
    }

    public static function label(): string
    {
        return static::$label !== null ? FrontendLang::label(static::$label) : (FrontendLang::has('components.' . static::name())
            ? FrontendLang::get('components.' . static::name())
            : ucfirst(str_replace('-', ' ', static::name())));
    }

    public static function icon(): string { return static::$icon; }
    public static function category(): string { return static::$category; }
    public static function description(): ?string { return static::$description === null ? null : FrontendLang::label(static::$description); }
    public static function isBlock(): bool { return static::$block; }
    public static function cacheable(): bool { return static::$cacheable; }

    /**
     * An element id for the component (slider, tabs, dialog …) when the props
     * have none: made from its content, so a page from the Builder's cache and
     * the components rendered around it don't share one.
     *
     * @param array<string, mixed> $props
     */
    protected static function uid(string $prefix, array $props): string
    {
        static $seen = [];
        if (($props['id'] ?? '') !== '') {
            return (string) $props['id'];
        }
        $base = $prefix . '-' . substr(sha1((string) json_encode($props)), 0, 8);
        $seen[$base] = ($seen[$base] ?? 0) + 1;
        return $seen[$base] === 1 ? $base : $base . '-' . $seen[$base];
    }

    /** The component's own folder (where its class is). */
    public static function folder(): string
    {
        $file = (new \ReflectionClass(static::class))->getFileName();
        return is_string($file) ? dirname($file) : '';
    }
}
