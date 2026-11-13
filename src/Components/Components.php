<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Components;

use FloCMS\Core\TemplateEngine;
use FloCMS\Frontend\Assets\Assets;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Log;

/**
 * The central component container: it finds every component (the package's,
 * other packages', the site's), checks the props it is given and renders it.
 *
 * Where a component comes from, last one wins:
 *   1. the package's built-in components,
 *   2. classes other packages register (Components::register()),
 *   3. classes listed in config/frontend.php 'components',
 *   4. the site's components/<name>/ folders (class App\Components\<Name>).
 *
 * A site folder without a class (only pricing.html, or only pricing.css)
 * restyles the component of that name instead of replacing it.
 */
final class Components
{
    /** @var list<class-string<Component>> */
    public const BUILT_IN = [
        Library\Section::class,
        Library\Container::class,
        Library\Grid::class,
        Library\Header::class,
        Library\Footer::class,
        Library\Hero::class,
        Library\Slider::class,
        Library\Card::class,
        Library\Cards::class,
        Library\Button::class,
        Library\Alert::class,
        Library\Accordion::class,
        Library\Tabs::class,
        Library\Dialog::class,
        Library\Gallery::class,
        Library\Breadcrumbs::class,
        Library\Pagination::class,
        Library\LanguageSwitcher::class,
        Library\ThemeSwitch::class,
        Library\CookieNotice::class,
        Library\CallToAction::class,
        Library\Pricing::class,
        Library\Faq::class,
        Library\Features::class,
    ];

    /** Site component classes live in components/<name>/ under this namespace. */
    public const SITE_NAMESPACE = 'App\\Components\\';

    /** @var list<class-string<Component>> registered by other packages */
    private static array $registered = [];

    /** @var array<string, class-string<Component>>|null */
    private static ?array $classes = null;

    /** @var array<string, Component> */
    private static array $instances = [];

    /** @var list<string> */
    private static array $problems = [];

    /** For packages: add a component class (call it from the package's bootstrap). */
    public static function register(string $class): void
    {
        if (!in_array($class, self::$registered, true)) {
            self::$registered[] = $class;
            self::$classes = null;
        }
    }

    /** @return array<string, class-string<Component>> name => class */
    public static function all(): array
    {
        if (self::$classes !== null) {
            return self::$classes;
        }
        self::$problems = [];
        $classes = [];
        foreach (array_merge(self::BUILT_IN, self::$registered, Frontend::config()->components(), self::siteClasses()) as $class) {
            if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, Component::class) || (new \ReflectionClass($class))->isAbstract()) {
                self::$problems[] = (is_string($class) ? $class : get_debug_type($class)) . ' is not a component class (one that extends ' . Component::class . ')';
                continue;
            }
            try {
                $classes[$class::name()] = $class;
            } catch (\LogicException $error) {
                self::$problems[] = $error->getMessage();
            }
        }
        return self::$classes = $classes;
    }

    public static function exists(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function find(string $name): ?Component
    {
        $class = self::all()[$name] ?? null;
        if ($class === null) {
            return null;
        }
        return self::$instances[$name] ??= new $class();
    }

    /**
     * A file of a component: the site's components/<name>/<name>.<ext> first,
     * then the component's own folder. Null when neither has one.
     */
    public static function file(string $name, string $extension): ?string
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1 || !in_array($extension, ['html', 'css', 'js'], true)) {
            return null;
        }
        $site = Frontend::root() . '/components/' . $name . '/' . $name . '.' . $extension;
        if (is_file($site)) {
            return $site;
        }
        $class = self::all()[$name] ?? null;
        if ($class === null) {
            return null;
        }
        $own = $class::folder() . '/' . $name . '.' . $extension;
        if (is_file($own)) {
            return $own;
        }
        // The package's built-in components keep their files in resources/components/<name>/
        $package = Frontend::path('resources/components/' . $name . '/' . $name . '.' . $extension);
        return is_file($package) ? $package : null;
    }

    /** Whether the CSS of a component is part of the site style file (the package's own, not restyled by the site). */
    public static function cssInStyle(string $name): bool
    {
        $file = self::file($name, 'css');
        return $file !== null && str_starts_with($file, Frontend::path('resources/components/'));
    }

    /**
     * Render a component. $props are checked: a missing required prop or an
     * unknown prop is an error with APP_DEBUG on; in production the component
     * renders without it and the problem is logged once.
     *
     * @param array<string, mixed> $props
     * @param array<string, string> $slots slot name => HTML ('default' is the content between the tags)
     */
    public static function render(string $name, array $props = [], array $slots = []): string
    {
        $component = self::find($name);
        if ($component === null) {
            return self::problem('There is no component "' . $name . '".', '');
        }
        $definitions = [];
        foreach ($component->props() as $prop) {
            $definitions[$prop->name()] = $prop;
        }
        $clean = [];
        foreach ($props as $key => $value) {
            if (!isset($definitions[$key]) && !in_array($key, Component::COMMON_PROPS, true)) {
                self::problem('The component "' . $name . '" has no prop "' . $key . '".', null);
            }
        }
        foreach ($definitions as $key => $prop) {
            $problem = $prop->problem($props[$key] ?? null);
            if ($problem !== null) {
                self::problem('Component "' . $name . '": ' . $problem . '.', null);
            }
            $clean[$key] = $prop->clean($props[$key] ?? null);
        }
        $clean['id'] = is_string($props['id'] ?? null) && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $props['id']) === 1 ? $props['id'] : '';
        $clean['class'] = is_string($props['class'] ?? null) ? trim((string) preg_replace('/[^A-Za-z0-9_ -]/', '', $props['class'])) : '';

        Assets::used($name);
        $slots = array_map('strval', $slots);
        $vars = $clean + $component->data($clean) + ['slot' => $slots['default'] ?? '', 'slots' => $slots, 'component' => $name];
        $html = $component->html($vars);
        if ($html !== null) {
            return $html;
        }
        $template = self::file($name, 'html');
        if ($template === null) {
            return self::problem('The component "' . $name . '" has no template (' . $name . '.html).', '');
        }
        return TemplateEngine::renderFile($template, $vars);
    }

    /** @return list<string> component classes that could not be used (for site:doctor) */
    public static function problems(): array
    {
        self::all();
        return self::$problems;
    }

    public static function reset(): void
    {
        self::$classes = null;
        self::$instances = [];
    }

    /** For tests: forget the classes other packages registered. */
    public static function clearRegistered(): void
    {
        self::$registered = [];
        self::reset();
    }

    /**
     * The site's component classes: components/<name>/<Name>.php, class
     * App\Components\<Name> (loaded here, so the site needs no autoload entry).
     *
     * @return list<string>
     */
    private static function siteClasses(): array
    {
        $folder = Frontend::root() . '/components';
        if (!is_dir($folder)) {
            return [];
        }
        $classes = [];
        foreach (scandir($folder) ?: [] as $entry) {
            if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $entry) !== 1 || !is_dir($folder . '/' . $entry)) {
                continue;
            }
            $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $entry)));
            $file = $folder . '/' . $entry . '/' . $studly . '.php';
            if (!is_file($file)) {
                continue; // only files: restyles the component of that name
            }
            $class = self::SITE_NAMESPACE . $studly;
            if (!class_exists($class, false)) {
                try {
                    (static function (string $__file): void { require_once $__file; })($file);
                } catch (\Throwable $error) {
                    self::$problems[] = 'components/' . $entry . '/' . $studly . '.php: ' . $error->getMessage();
                    continue;
                }
            }
            if (!class_exists($class, false)) {
                self::$problems[] = 'components/' . $entry . '/' . $studly . '.php must declare ' . $class;
                continue;
            }
            if (is_subclass_of($class, Component::class) && $class::name() !== $entry) {
                self::$problems[] = $class . '::$name is "' . $class::name() . '" but its folder is components/' . $entry . '/';
                continue;
            }
            $classes[] = $class;
        }
        return $classes;
    }

    private static function problem(string $message, ?string $fallback): string
    {
        if (Frontend::debug()) {
            throw new ComponentException($message);
        }
        Log::warning($message);
        return (string) $fallback;
    }
}
