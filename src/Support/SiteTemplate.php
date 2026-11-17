<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Support;

use FloCMS\Frontend\Console\Stub;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Style\Style;

/**
 * The template that shows the package's pages. A layout that doesn't call
 * site_head() never links the Site Style, so a starter on the skeleton's
 * own "default" template would show unstyled. Choosing a starter makes the
 * package's "site" template the active one (APP_TEMPLATE in .env).
 */
final class SiteTemplate
{
    public const NAME = 'site';

    /** The layout file of a template (templates/<name>/layouts/default.html). */
    public static function layout(string $template): string
    {
        return Frontend::root() . '/templates/' . $template . '/layouts/default.html';
    }

    /** Whether a template's layout shows the package's pages properly (calls site_head()). */
    public static function isReady(string $template): bool
    {
        $layout = self::layout($template);
        return is_file($layout) && str_contains((string) file_get_contents($layout), 'site_head(');
    }

    /**
     * Make sure the active template shows the package's pages. Nothing when it
     * already does; otherwise writes templates/site (only the files that are
     * missing) and sets APP_TEMPLATE=site in .env.
     *
     * @return array{changed: bool, template: string, problem: ?string}
     */
    public static function ensure(): array
    {
        $active = Style::template();
        if (self::isReady($active)) {
            return ['changed' => false, 'template' => $active, 'problem' => null];
        }
        foreach (['layouts/default.html' => 'layout', 'style.php' => 'style'] as $file => $stub) {
            $path = Frontend::root() . '/templates/' . self::NAME . '/' . $file;
            if (!is_file($path) && !Stub::create($path, Stub::render($stub))) {
                return ['changed' => false, 'template' => $active, 'problem' => 'templates/' . self::NAME . '/' . $file . ' can\'t be written'];
            }
        }
        if (!self::isReady(self::NAME)) {
            return ['changed' => false, 'template' => $active, 'problem' => 'templates/' . self::NAME . '/layouts/default.html doesn\'t call site_head()'];
        }
        $problem = self::activate(self::NAME);
        return ['changed' => $problem === null, 'template' => $problem === null ? self::NAME : $active, 'problem' => $problem];
    }

    /** APP_TEMPLATE=<name> in .env (the line changed or added). @return string|null what went wrong */
    public static function activate(string $template): ?string
    {
        $file = Frontend::root() . '/.env';
        if (!is_file($file) || !is_writable($file)) {
            return '.env can\'t be changed';
        }
        $env = (string) file_get_contents($file);
        $line = 'APP_TEMPLATE=' . $template;
        $env = preg_match('/^APP_TEMPLATE=.*$/m', $env) === 1
            ? (string) preg_replace('/^APP_TEMPLATE=.*$/m', $line, $env, 1)
            : rtrim($env) . PHP_EOL . $line . PHP_EOL;
        if (file_put_contents($file, $env, LOCK_EX) === false) {
            return '.env can\'t be changed';
        }
        $_ENV['APP_TEMPLATE'] = $template;
        if (function_exists('putenv')) { @putenv('APP_TEMPLATE=' . $template); }
        return null;
    }
}
