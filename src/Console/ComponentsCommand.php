<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Frontend;

/** php flo site:components: every component, where it comes from and which of its files the site changed. */
final class ComponentsCommand extends Command
{
    use Boots;

    protected string $name = 'site:components';

    protected string $description = 'List the components: the package\'s, other packages\' and the site\'s';

    protected function handle(): int
    {
        $this->bootSite();
        $rows = [];
        foreach (Components::all() as $name => $class) {
            $from = str_starts_with($class, 'FloCMS\\Frontend\\') ? 'package' : (str_starts_with($class, Components::SITE_NAMESPACE) ? 'site' : 'other');
            $changed = [];
            foreach (['html', 'css', 'js'] as $extension) {
                $file = Components::file($name, $extension);
                if ($file !== null && str_starts_with($file, Frontend::root() . '/components/')) { $changed[] = $extension; }
            }
            $props = [];
            foreach ((new $class())->props() as $prop) { $props[] = $prop->name() . ($prop->isRequired() ? '*' : ''); }
            $rows[] = [$name, $from . ($changed !== [] && $from !== 'site' ? ' (site ' . implode(', ', $changed) . ')' : ''), $class::isBlock() ? 'yes' : '', implode(', ', $props)];
        }
        $this->table(['Component', 'From', 'Block', 'Props (* required)'], $rows);
        foreach (Components::problems() as $problem) {
            $this->error($problem);
        }
        return Components::problems() === [] ? self::SUCCESS : self::FAILURE;
    }
}
