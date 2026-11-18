<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\Frontend\Collections\Collections;
use FloCMS\Frontend\Collections\Query;

/** php flo site:collections: the site's collections, their addresses and how many items visitors see. */
final class CollectionsCommand extends Command
{
    use Boots;

    protected string $name = 'site:collections';

    protected string $description = 'List the site\'s collections, their addresses and items';

    protected function configure(Definition $definition): void
    {
    }

    protected function handle(): int
    {
        $this->bootSite();
        $all = Collections::all();
        if ($all === []) {
            $this->line('No collections yet. Make one: php flo site:collection --kit=blog');
            return self::SUCCESS;
        }
        $rows = [];
        foreach ($all as $name => $class) {
            try {
                $count = (string) $class::get()->source()->query((new Query()))->total;
            } catch (\Throwable $error) {
                $count = 'table missing? php flo migrate';
            }
            $rows[] = [$name, $class, $class::isPublic() ? '/' . $class::path() : '(its pages are its collection\'s)', $class::kit(), $count];
        }
        $this->table(['Name', 'Class', 'Address', 'Kit', 'Visible items'], $rows);
        foreach (Collections::problems() as $problem) {
            $this->warn($problem);
        }
        return self::SUCCESS;
    }
}
