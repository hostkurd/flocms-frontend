<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Argument;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;
use FloCMS\Frontend\Components\Components;

/**
 * php flo site:component Pricing [--css] [--js] [--block]
 *
 * A component folder: components/pricing/ with Pricing.php and pricing.html
 * (and pricing.css, pricing.js).
 */
final class MakeComponentCommand extends Command
{
    use Boots;

    protected string $name = 'site:component';

    protected string $description = 'Create a component: components/<name>/ with its class and template (and CSS, JS)';

    protected array $examples = ['php flo site:component Pricing', 'php flo site:component TeamMembers --css --js --block'];

    protected function configure(Definition $definition): void
    {
        $definition
            ->argument('name', Argument::REQUIRED, 'Class name in StudlyCase, e.g. Pricing or TeamMembers')
            ->option('css', null, Option::VALUE_NONE, 'Add <name>.css (linked only on pages that use it)')
            ->option('js', null, Option::VALUE_NONE, 'Add <name>.js')
            ->option('block', 'b', Option::VALUE_NONE, 'Offer it as a block in flocms-admin\'s Builder');
    }

    protected function handle(): int
    {
        $class = trim((string) $this->argument('name'));
        // One capital per word (AbTest, not ABTest): the folder ab-test/ leads back to AbTest.php
        if (preg_match('/^[A-Z][a-z0-9]+(?:[A-Z][a-z0-9]+)*$/', $class) !== 1) {
            $this->invalid('The name is a class name in StudlyCase with one capital per word, e.g. Pricing, TeamMembers or FaqList (not FAQList).');
        }
        $name = strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $class));
        if (strlen($name) > 36) {
            $this->invalid('The name gives the component name "' . $name . '", which is too long; use a shorter one.');
        }
        $this->bootSite();
        if (Components::exists($name) && !str_starts_with(Components::all()[$name], Components::SITE_NAMESPACE)) {
            $this->warn('"' . $name . '" is one of the package\'s components: yours replaces it everywhere.');
        }
        $folder = 'components/' . $name;
        $files = [
            $folder . '/' . $class . '.php' => Stub::render('component-class', ['class' => $class, 'name' => $name, 'block' => $this->option('block') ? 'true' : 'false']),
            $folder . '/' . $name . '.html' => Stub::render('component-template', ['name' => $name]),
        ];
        if ($this->option('css')) {
            $files[$folder . '/' . $name . '.css'] = '.fe-' . $name . "{}\n";
        }
        if ($this->option('js')) {
            $files[$folder . '/' . $name . '.js'] = "// Runs once on every page that shows the component.\ndocument.querySelectorAll('.fe-" . $name . "').forEach(function (element) {\n});\n";
        }
        foreach (array_keys($files) as $relative) {
            if (is_file($this->project()->path($relative))) {
                $this->fail($relative . ' already exists.');
            }
        }
        foreach ($files as $relative => $contents) {
            if (!Stub::create($this->project()->path($relative), $contents)) {
                $this->fail('Could not write ' . $relative . '.');
            }
            $this->success('Created ' . $relative);
        }
        $this->note('Use it: @component(\'' . $name . '\', [\'heading\' => \'Hello\']) @endcomponent');
        return self::SUCCESS;
    }
}
