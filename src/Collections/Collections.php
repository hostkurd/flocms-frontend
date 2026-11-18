<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Collections;

use FloCMS\Frontend\Frontend;

/**
 * The site's collections: listed in config/frontend.php, their classes in
 * the site's collections/ folder (namespace App\Collections), which the
 * package loads itself.
 *
 *     'collections' => [
 *         App\Collections\Posts::class,
 *         App\Collections\PostCategories::class,
 *     ],
 */
final class Collections
{
    /** @var array<string, class-string<Collection>>|null name => class */
    private static ?array $all = null;
    /** @var list<class-string<Collection>> registered by packages or tests */
    private static array $extra = [];
    private static bool $autoload = false;

    /** Load App\Collections\<Name> from the site's collections/<Name>.php. */
    public static function registerAutoload(): void
    {
        if (self::$autoload) { return; }
        self::$autoload = true;
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, 'App\\Collections\\')) { return; }
            $relative = str_replace('\\', '/', substr($class, strlen('App\\Collections\\')));
            if (preg_match('#^[A-Za-z0-9_/]+$#', $relative) !== 1) { return; }
            $file = Frontend::root() . '/collections/' . $relative . '.php';
            if (is_file($file)) { require_once $file; }
        });
    }

    /** A collection that is not in the config (a package's, a test's). @param class-string<Collection> $class */
    public static function register(string $class): void
    {
        if (!in_array($class, self::$extra, true)) {
            self::$extra[] = $class;
        }
        self::$all = null;
    }

    /** @return array<string, class-string<Collection>> name => class, in the order given */
    public static function all(): array
    {
        if (self::$all !== null) { return self::$all; }
        self::registerAutoload();
        $classes = Frontend::config()->collections();
        $all = [];
        foreach (array_merge($classes, self::$extra) as $class) {
            if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, Collection::class)) {
                continue;
            }
            $all[$class::name()] = $class;
        }
        return self::$all = $all;
    }

    /** @return class-string<Collection>|null */
    public static function byName(string $name): ?string
    {
        return self::all()[$name] ?? null;
    }

    /** @return list<Collection> those with pages of their own */
    public static function public(): array
    {
        $out = [];
        foreach (self::all() as $class) {
            if ($class::isPublic()) { $out[] = $class::get(); }
        }
        return $out;
    }

    /**
     * Problems with the configured classes, for site:doctor.
     *
     * @return list<string>
     */
    public static function problems(): array
    {
        self::registerAutoload();
        $problems = [];
        foreach (Frontend::config()->collections() as $class) {
            if (!is_string($class) || !class_exists($class)) {
                $problems[] = 'collections: ' . (is_string($class) ? $class : gettype($class)) . ' was not found (collections/<Name>.php, namespace App\\Collections)';
            } elseif (!is_subclass_of($class, Collection::class)) {
                $problems[] = 'collections: ' . $class . ' does not extend ' . Collection::class;
            }
        }
        $paths = [];
        foreach (self::all() as $class) {
            if (!$class::isPublic()) { continue; }
            foreach ($class::paths() as $lang => $path) {
                $key = $lang . ':' . $path;
                if (isset($paths[$key])) {
                    $problems[] = 'collections: ' . $class . ' and ' . $paths[$key] . ' both use /' . $lang . '/' . $path;
                }
                $paths[$key] = $class;
            }
        }
        return $problems;
    }

    public static function reset(): void
    {
        self::$all = null;
        Collection::forget();
    }

    /** Tests: forget the extra collections too. */
    public static function clear(): void
    {
        self::$extra = [];
        self::reset();
    }
}
