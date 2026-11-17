<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\Core\Config;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Style\StyleCss;

/** php flo site:doctor: checks the setup and says what to fix. */
final class DoctorCommand extends Command
{
    use Boots;

    protected string $name = 'site:doctor';

    protected string $description = 'Check the flocms-frontend setup: config, folders, layout, components, style, controllers';

    private int $problems = 0;

    protected function handle(): int
    {
        $this->bootSite();
        $root = $this->project()->root();
        $this->title('flocms-frontend ' . Frontend::VERSION);

        is_file($root . '/config/frontend.php') ? $this->ok('config/frontend.php') : $this->note('No config/frontend.php: the defaults are used (php flo site:install writes one).');
        foreach (Frontend::config()->problems() as $problem) { $this->bad('config/frontend.php: ' . $problem); }

        $cache = Frontend::cachePath();
        if ((is_dir($cache) || @mkdir($cache, 0775, true)) && is_writable($cache)) {
            $this->ok('storage/cache/frontend is writable');
        } else {
            $this->bad('storage/cache/frontend can\'t be written: make storage/ writable (the style is then built on every request).');
        }

        $template = Style::template();
        $layout = $root . '/templates/' . $template . '/layouts/default.html';
        if (!is_file($layout)) { $layout = $root . '/templates/default/layouts/default.html'; }
        $source = is_file($layout) ? (string) file_get_contents($layout) : '';
        foreach (['site_html(' => 'the <html> tag (lang, dir, light/dark)', 'site_head(' => 'the head (SEO, the style)', 'site_foot(' => 'the end of the body (scripts, cookie notice)'] as $call => $what) {
            str_contains($source, $call)
                ? $this->ok('The layout calls ' . $call . ')')
                : $this->bad('The layout (' . $this->project()->relative($layout) . ') doesn\'t call {!! ' . $call . ') !!}: ' . $what . '. php flo site:install writes a layout that does.');
        }

        foreach (Style::problems() as $problem) { $this->bad('Style: ' . $problem); }
        $contrast = count(Style::contrastProblems());
        $contrast === 0 ? $this->ok('Style colours read well (WCAG AA)') : $this->warning($contrast . ' colour pair(s) are hard to read: php flo site:style --check');
        try {
            StyleCss::build();
            $this->ok('The style builds (' . Style::fileName() . ')');
        } catch (\Throwable $error) {
            $this->bad('The style doesn\'t build: ' . $error->getMessage());
        }

        $count = count(Components::all());
        foreach (Components::problems() as $problem) { $this->bad('Component: ' . $problem); }
        foreach (Components::all() as $name => $class) {
            if (Components::file($name, 'html') === null && (new \ReflectionMethod($class, 'html'))->getDeclaringClass()->getName() === \FloCMS\Frontend\Components\Component::class) {
                $this->bad('Component "' . $name . '" has no template ' . $name . '.html');
            }
        }
        $this->ok($count . ' components');

        // A site controller with the name of one of the package's addresses takes it over
        foreach (array_keys(Frontend::CONTROLLERS) as $controller) {
            $class = 'FloCMS\\Controllers\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $controller))) . 'Controller';
            if (class_exists($class)) { $this->warning('The site\'s ' . $class . ' answers /' . $controller . '/ instead of the package.'); }
        }

        if (class_exists(\FloCMS\Admin\Admin::class)) {
            method_exists(\FloCMS\Admin\Admin::class, 'extend')
                ? $this->ok('flocms-admin: components as Builder blocks, Settings → Site style')
                : $this->bad('flocms-admin is older than 0.43: update it (composer require "hostkurd/flocms-admin:^0.43" -W).');
        }
        if (!class_exists(\NumberFormatter::class)) {
            $this->note('PHP\'s intl isn\'t there: numbers and dates use the package\'s own tables (en, ar, ku).');
        }

        $this->newLine();
        if ($this->problems === 0) {
            $this->success('No problems found.');
            return self::SUCCESS;
        }
        $this->error($this->problems . ' problem(s) found.');
        return self::FAILURE;
    }

    private function ok(string $text): void { $this->line('  ✓ ' . $text); }
    private function warning(string $text): void { $this->warn('  ! ' . $text); }
    private function bad(string $text): void { $this->problems++; $this->error('  ✗ ' . $text); }
}
