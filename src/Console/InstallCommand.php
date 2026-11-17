<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;
use FloCMS\Frontend\Starters\Starters;

/**
 * php flo site:install [--template=site] [--no-template]
 *
 * Writes config/frontend.php, components/README.md and a template folder of
 * the package's own (layout + style.php), and offers to make it the active
 * template (APP_TEMPLATE in .env). It never overwrites a file.
 */
final class InstallCommand extends Command
{
    use Boots;

    protected string $name = 'site:install';

    protected string $description = 'Set up flocms-frontend: config/frontend.php, components/, a template with the layout and style.php';

    protected array $examples = ['php flo site:install', 'php flo site:install --template=studio', 'php flo site:install --no-template'];

    protected function configure(Definition $definition): void
    {
        $definition
            ->option('template', null, Option::VALUE_REQUIRED, 'The template folder to create (templates/<name>)', 'site')
            ->option('no-template', null, Option::VALUE_NONE, 'Only the config and the components folder')
            ->option('activate', null, Option::VALUE_NONE, 'Make the template the active one without asking (APP_TEMPLATE in .env)');
    }

    protected function handle(): int
    {
        $project = $this->project();
        $this->title('flocms-frontend');
        $this->place('config/frontend.php', Stub::render('config'));
        $this->place('components/README.md', Stub::render('components-readme'));

        if (!$this->option('no-template')) {
            $template = (string) $this->option('template');
            if (preg_match('/^[a-z0-9][a-z0-9_-]{0,40}$/', $template) !== 1) {
                $this->invalid('The template name is lower-case letters, digits, "-" and "_": ' . $template);
            }
            $this->place('templates/' . $template . '/layouts/default.html', Stub::render('layout'));
            $this->place('templates/' . $template . '/style.php', Stub::render('style'));
            $current = (string) ($project->env('APP_TEMPLATE') ?? 'default');
            if ($current !== $template) {
                $activate = (bool) $this->option('activate') || $this->confirm('Make "' . $template . '" the active template (APP_TEMPLATE=' . $template . ' in .env)?', true);
                if ($activate) {
                    $this->activate($template);
                } else {
                    $this->note('Later: set APP_TEMPLATE=' . $template . ' in .env, or call site_html(), site_head() and site_foot() in your own layout.');
                }
            }
        }

        $this->newLine();
        $this->line('Next:');
        $this->line('  1. Open /site-starter while signed in to the admin (or with APP_ENV=local), or run');
        $this->line('     php flo site:starter ' . implode('|', Starters::NAMES) . '   to start the home page.');
        $this->line('  2. php flo site:doctor      checks the setup');
        $this->line('  3. php flo site:style --check   checks the style\'s contrast');
        return self::SUCCESS;
    }

    private function place(string $relative, string $contents): void
    {
        $path = $this->project()->path($relative);
        if (is_file($path)) {
            $this->comment('Kept ' . $relative . ' (already there)');
            return;
        }
        if (!Stub::create($path, $contents)) {
            $this->fail('Could not write ' . $relative . '.');
        }
        $this->success('Created ' . $relative);
    }

    /** APP_TEMPLATE=<name> in .env (the line changed or added). */
    private function activate(string $template): void
    {
        $file = $this->project()->path('.env');
        if (!is_file($file) || !is_writable($file)) {
            $this->warn('.env can\'t be changed: set APP_TEMPLATE=' . $template . ' yourself.');
            return;
        }
        $env = (string) file_get_contents($file);
        $line = 'APP_TEMPLATE=' . $template;
        $env = preg_match('/^APP_TEMPLATE=.*$/m', $env) === 1
            ? (string) preg_replace('/^APP_TEMPLATE=.*$/m', $line, $env, 1)
            : rtrim($env) . PHP_EOL . $line . PHP_EOL;
        file_put_contents($file, $env, LOCK_EX);
        $this->project()->refresh();
        $this->success('APP_TEMPLATE=' . $template . ' in .env');
    }
}
