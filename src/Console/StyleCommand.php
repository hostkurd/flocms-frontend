<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Style\StyleCss;

/**
 * php flo site:style [--check] [--css]
 *
 * The style values in use and where they come from, the generated file's
 * name; --check reports colour pairs below WCAG AA; --css prints the CSS.
 */
final class StyleCommand extends Command
{
    use Boots;

    protected string $name = 'site:style';

    protected string $description = 'Show the Site Style values and the generated CSS file; --check reports contrast problems';

    protected function configure(Definition $definition): void
    {
        $definition
            ->option('check', null, Option::VALUE_NONE, 'Report text and background pairs that are hard to read (WCAG AA)')
            ->option('css', null, Option::VALUE_NONE, 'Print the generated CSS');
    }

    protected function handle(): int
    {
        $this->bootSite();
        if ($this->option('css')) {
            $this->line(StyleCss::build());
            return self::SUCCESS;
        }
        $values = Style::values();
        $file = Style::file();
        $this->title('Site Style');
        $this->definitionList([
            'Template' => Style::template(),
            'Values from' => $file === null ? 'the package\'s defaults (no style.php)' : $this->project()->relative($file),
            'CSS file' => 'storage/cache/frontend/' . Style::fileName(),
            'Dark mode' => $values['dark'],
            'Fonts' => $values['fonts']['latin'] . ' / ' . $values['fonts']['arabic'],
            'Corners, spacing, width' => $values['radius'] . ', ' . $values['spacing'] . ', ' . $values['width'] . ' px',
        ]);
        $palette = Style::palette();
        $rows = [];
        foreach ($palette['light'] as $name => $light) {
            $rows[] = [$name, $light, $palette['dark'][$name] ?? ''];
        }
        $this->table(['Colour', 'Light', 'Dark'], $rows);
        foreach (Style::problems() as $problem) {
            $this->warn($problem);
        }
        if (!$this->option('check')) {
            return self::SUCCESS;
        }
        $problems = Style::contrastProblems();
        if ($problems === []) {
            $this->success('Every text colour reads well on its background (WCAG AA).');
            return self::SUCCESS;
        }
        $this->table(['Mode', 'Text', 'On', 'Contrast', 'Needed'], array_map(static fn (array $p): array => [$p['mode'], $p['text'], $p['background'], $p['ratio'] . ' : 1', $p['needed'] . ' : 1'], $problems));
        $this->error(count($problems) . ' colour pair(s) are hard to read. Change them in ' . ($file === null ? 'the template\'s style.php' : $this->project()->relative($file)) . '.');
        return self::FAILURE;
    }
}
