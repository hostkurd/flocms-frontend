<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Argument;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;
use FloCMS\Frontend\Starters\StarterSetup;
use FloCMS\Frontend\Starters\Starters;
use FloCMS\Frontend\Support\FrontendLang;

/**
 * php flo site:starter [studio|slider|shop|journal|landing|blank] [--force]
 *
 * The same choice as the starter picker: a home page made of components, in
 * Site → Pages when flocms-admin's Pages are on, else views/pages/index.html.
 */
final class StarterCommand extends Command
{
    use Boots;

    protected string $name = 'site:starter';

    protected string $description = 'Start the home page from a ready-made starter (in Site → Pages, or views/pages/index.html)';

    protected array $examples = ['php flo site:starter', 'php flo site:starter studio', 'php flo site:starter slider --force'];

    protected function configure(Definition $definition): void
    {
        $definition
            ->argument('starter', Argument::OPTIONAL, 'studio, slider, shop, journal, landing or blank')
            ->option('force', 'f', Option::VALUE_NONE, 'Create it even when a starter was chosen before (the old home page is kept)');
    }

    protected function handle(): int
    {
        $this->bootSite();
        $name = (string) ($this->argument('starter') ?? '');
        if ($name === '') {
            $choices = [];
            foreach (Starters::NAMES as $starter) {
                $choices[$starter] = FrontendLang::get('starter.' . $starter) . ': ' . FrontendLang::get('starter.' . $starter . '_text');
            }
            $name = (string) $this->choice('Which starter?', $choices, 'studio');
        }
        if (!in_array($name, Starters::NAMES, true)) {
            $this->invalid('Starters: ' . implode(', ', Starters::NAMES));
        }
        $chosen = StarterSetup::chosen();
        if ($chosen !== null && !$this->option('force')) {
            $this->fail('The "' . $chosen['name'] . '" starter was chosen on ' . date('Y-m-d', (int) $chosen['time']) . '. Use --force to create another.');
        }
        try {
            $result = StarterSetup::apply($name, (bool) $this->option('force'));
        } catch (\RuntimeException $error) {
            $this->fail($error->getMessage());
        }
        $this->success($result['target'] === 'pages'
            ? 'Created the home page in Site → Pages (page ' . $result['where'] . '), built from blocks.'
            : 'Wrote ' . $result['where'] . ', built from components.');
        return self::SUCCESS;
    }
}
