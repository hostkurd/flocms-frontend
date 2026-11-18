<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Console;

use FloCMS\CLI\Console\Command;
use FloCMS\CLI\Console\Input\Argument;
use FloCMS\CLI\Console\Input\Definition;
use FloCMS\CLI\Console\Input\Option;
use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Collections;
use FloCMS\Frontend\Collections\Schema;
use FloCMS\Frontend\Frontend;

/**
 * php flo site:collection [Name] [--kit=blog|shop|none] [--path=…] [--markdown]
 *
 * Writes a collection: its class in collections/ (extending the kit's, so
 * the kit's updates come with the package), a migration for its table, and
 * its line in config/frontend.php. A kit also writes its categories.
 */
final class CollectionCommand extends Command
{
    use Boots;

    protected string $name = 'site:collection';

    protected string $description = 'Make a collection (a blog, a shop catalogue, projects …): its class, its table\'s migration and its config line';

    protected array $examples = [
        'php flo site:collection --kit=blog',
        'php flo site:collection --kit=shop',
        'php flo site:collection News --kit=blog --path=news',
        'php flo site:collection Projects',
        'php flo site:collection Projects --markdown',
    ];

    protected function configure(Definition $definition): void
    {
        $definition
            ->argument('name', Argument::OPTIONAL, 'The class name: Posts, Products, Projects (default: the kit\'s)')
            ->option('kit', null, Option::VALUE_REQUIRED, 'blog, shop or none', 'none')
            ->option('path', null, Option::VALUE_REQUIRED, 'The address after the language (default: blog, shop, or the name)')
            ->option('markdown', null, Option::VALUE_NONE, 'Keep items as Markdown files in content/<name>/ instead of a table');
    }

    protected function handle(): int
    {
        $this->bootSite();
        $kit = strtolower((string) $this->option('kit'));
        if (!in_array($kit, ['blog', 'shop', 'none'], true)) {
            $this->invalid('--kit is blog, shop or none.');
        }
        $name = (string) ($this->argument('name') ?? '');
        if ($name === '') {
            if ($kit === 'none') {
                $this->invalid('Give a name (e.g. Projects), or a kit: --kit=blog or --kit=shop.');
            }
            $name = $kit === 'blog' ? 'Posts' : 'Products';
        }
        $class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        if (preg_match('/^[A-Z][A-Za-z0-9]{1,40}$/', $class) !== 1) {
            $this->invalid('The name is a class name in letters and digits, e.g. Posts or Projects.');
        }
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
        $path = (string) ($this->option('path') ?? '');
        if ($path === '') {
            $path = $kit === 'blog' && $class === 'Posts' ? 'blog' : ($kit === 'shop' && $class === 'Products' ? 'shop' : str_replace('_', '-', $snake));
        }
        if (preg_match('#^[\p{L}\p{N}][\p{L}\p{N}-]*(/[\p{L}\p{N}][\p{L}\p{N}-]*)?$#u', $path) !== 1) {
            $this->invalid('--path is a word or two of letters, digits and "-", e.g. blog or news.');
        }
        $markdown = (bool) $this->option('markdown');
        $categories = $kit === 'none' ? null : self::singular($class) . 'Categories';
        $files = [];
        if ($categories !== null) {
            $files['collections/' . $categories . '.php'] = $this->categoriesClass($kit, $categories, self::singular($snake) . '_categories');
        }
        $files['collections/' . $class . '.php'] = $this->itemsClass($kit, $class, $snake, $path, $categories, $markdown);
        $project = $this->project();
        foreach ($files as $relative => $code) {
            $file = $project->path($relative);
            if (is_file($file)) {
                $this->comment('Kept ' . $relative . ' (already there)');
                continue;
            }
            if (!Stub::create($file, $code)) {
                $this->fail('Could not write ' . $relative . '.');
            }
            $this->success('Created ' . $relative);
        }
        // Load them, then write the migrations from their fields
        Collections::registerAutoload();
        $classes = array_values(array_filter([$categories !== null ? 'App\\Collections\\' . $categories : null, 'App\\Collections\\' . $class]));
        $time = time();
        foreach ($classes as $fqcn) {
            if (!class_exists($fqcn) || !is_subclass_of($fqcn, Collection::class)) {
                $this->fail($fqcn . ' could not be loaded: check collections/.');
            }
            if ($markdown && $fqcn === 'App\\Collections\\' . $class) {
                $folder = 'content/' . $fqcn::name();
                if (!is_dir($project->path($folder))) {
                    @mkdir($project->path($folder), 0775, true);
                    $this->success('Created ' . $folder . '/ (one .md file per item; <slug>.ar.md for Arabic)');
                }
                continue;
            }
            $table = $fqcn::table();
            $existing = glob($project->path('database/migrations') . DIRECTORY_SEPARATOR . '*_create_' . $table . '_table.php') ?: [];
            if ($existing !== []) {
                $this->comment('A migration for ' . $table . ' already exists: ' . basename($existing[0]));
                continue;
            }
            $relative = 'database/migrations/' . date('Y_m_d_His', $time++) . '_create_' . $table . '_table.php';
            if (!Stub::create($project->path($relative), Schema::migration($fqcn::get()))) {
                $this->fail('Could not write ' . $relative . '.');
            }
            $this->success('Created ' . $relative);
        }
        $this->register($classes);
        $this->newLine();
        $this->line('Next:');
        $n = 1;
        if (!$markdown || $categories !== null) {
            $this->line('  ' . $n++ . '. php flo migrate');
        }
        $this->line('  ' . $n++ . '. Open /' . $path . ($markdown ? ' (add .md files to content/' . $snake . '/)' : ''));
        if (class_exists(\FloCMS\Admin\Admin::class)) {
            $this->line('  ' . $n++ . '. Add items in the admin (flocms-frontend 0.2.1 adds the screens), or in the table');
        }
        $this->line('  ' . $n . '. Change pages by copying them: templates/<template>/collections/' . $snake . '/list.html, group.html, item.html');
        return self::SUCCESS;
    }

    /** Adds the classes to 'collections' in config/frontend.php. @param list<string> $classes */
    private function register(array $classes): void
    {
        $file = $this->project()->path('config/frontend.php');
        if (!is_file($file)) {
            $this->warn('No config/frontend.php (php flo site:install writes one). Add to it: \'collections\' => [' . implode(', ', array_map(static fn (string $c): string => '\\' . $c . '::class', $classes)) . '],');
            return;
        }
        $original = (string) file_get_contents($file);
        $listed = self::listedIn($file);
        if ($listed === null) {
            $this->warn('config/frontend.php could not be read: add to \'collections\' yourself: ' . self::lines($classes));
            return;
        }
        $missing = array_values(array_filter($classes, static fn (string $c): bool => !in_array(ltrim($c, '\\'), $listed, true)));
        if ($missing === []) {
            $this->comment('config/frontend.php lists them already');
            return;
        }
        $config = self::insert($original, $missing);
        // Written only when the result is valid PHP that lists them; the file is never left broken
        if ($config === null || !self::parses($config)) {
            $this->warn('config/frontend.php was not changed (its \'collections\' could not be found safely). Add to it yourself:' . "\n    'collections' => [\n" . self::lines($missing) . "    ],");
            return;
        }
        if (@file_put_contents($file, $config, LOCK_EX) === false) {
            $this->warn('config/frontend.php could not be written: add to \'collections\' yourself: ' . self::lines($missing));
            return;
        }
        $now = self::listedIn($file);
        if ($now === null || array_diff(array_map(static fn (string $c): string => ltrim($c, '\\'), $missing), $now) !== []) {
            @file_put_contents($file, $original, LOCK_EX);
            $this->warn('config/frontend.php was put back as it was. Add to \'collections\' yourself: ' . self::lines($missing));
            return;
        }
        Frontend::setRoot($this->project()->root());
        Collections::reset();
        $this->success('Added to config/frontend.php: ' . implode(', ', array_map(static fn (string $c): string => substr($c, strrpos($c, '\\') + 1), $missing)));
    }

    private function itemsClass(string $kit, string $class, string $name, string $path, ?string $categories, bool $markdown): string
    {
        $source = $markdown ? "\n    protected function makeSource(): Source\n    {\n        return new MarkdownSource(\$this); // content/{$name}/\n    }\n" : '';
        $uses = $markdown ? "use FloCMS\\Frontend\\Collections\\Sources\\MarkdownSource;\nuse FloCMS\\Frontend\\Collections\\Sources\\Source;\n" : '';
        if ($kit === 'blog' || $kit === 'shop') {
            $base = $kit === 'blog' ? 'BlogPosts' : 'ShopProducts';
            return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Collections;\n\nuse FloCMS\\Frontend\\Collections\\Kits\\{$base};\n{$uses}\n"
                . "/**\n * The {$kit} kit's " . ($kit === 'blog' ? 'posts' : 'products') . " at /<lang>/{$path}. Everything comes from {$base}: add\n"
                . " * fields with [...parent::fields(), Prop::…], change \$label or \$lead, or override\n * jsonLd(). Its pages: templates/<template>/collections/{$name}/item.html etc.\n */\n"
                . "final class {$class} extends {$base}\n{\n    protected static string \$name = '{$name}';\n    protected static string|array \$path = '{$path}';\n"
                . "    protected static string \$categories = {$categories}::class;\n{$source}}\n";
        }
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Collections;\n\nuse FloCMS\\Frontend\\Collections\\Collection;\nuse FloCMS\\Frontend\\Components\\Prop;\n{$uses}\n"
            . "/** {$class} at /<lang>/{$path}: a list page, and a page for each. */\n"
            . "final class {$class} extends Collection\n{\n    protected static string \$name = '{$name}';\n    protected static string|array \$path = '{$path}';\n"
            . "    protected static string|array|null \$label = ['en' => '" . ucfirst(str_replace('_', ' ', $name)) . "'];\n"
            . "    protected static string \$date = 'publish_at';\n\n"
            . "    public function fields(): array\n    {\n        return [\n"
            . "            Prop::text('title')->required()->translatable()->searchable(),\n"
            . "            Prop::slug('slug')->from('title')->translatable(),\n"
            . "            Prop::textarea('summary')->translatable()->searchable(),\n"
            . "            Prop::richText('body')->translatable(),\n"
            . "            Prop::image('image'),\n"
            . "        ];\n    }\n{$source}}\n";
    }

    private function categoriesClass(string $kit, string $class, string $name): string
    {
        $base = $kit === 'blog' ? 'BlogCategories' : 'ShopCategories';
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Collections;\n\nuse FloCMS\\Frontend\\Collections\\Kits\\{$base};\n\n"
            . "/** The categories of the {$kit} kit" . ($kit === 'shop' ? ' (up to 3 levels)' : '') . ". */\n"
            . "final class {$class} extends {$base}\n{\n    protected static string \$name = '{$name}';\n}\n";
    }

    /** @param list<string> $classes */
    private static function lines(array $classes): string
    {
        return implode('', array_map(static fn (string $c): string => "        \\{$c}::class,\n", $classes));
    }

    /** @return list<string>|null the classes config/frontend.php lists (as PHP reads it), or null if it can't be read */
    private static function listedIn(string $file): ?array
    {
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($file, true); }
        try {
            $values = (static fn (string $f): mixed => require $f)($file);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($values)) {
            return null;
        }
        $list = is_array($values['collections'] ?? null) ? $values['collections'] : [];
        return array_values(array_map(static fn (mixed $c): string => ltrim((string) $c, '\\'), array_filter($list, 'is_string')));
    }

    private static function parses(string $php): bool
    {
        try {
            token_get_all($php, TOKEN_PARSE);
            return true;
        } catch (\ParseError) {
            return false;
        }
    }

    /**
     * The config with the classes added: into its 'collections' => [ … ]
     * (found by PHP's tokenizer, so a commented-out line never counts), or
     * as a new key before the returned array's end. Null when neither is
     * found.
     *
     * @param list<string> $classes
     */
    public static function insert(string $config, array $classes): ?string
    {
        $tokens = token_get_all($config);
        $significant = [];
        $offset = 0;
        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                $significant[] = ['id' => $id, 'text' => $text, 'end' => $offset + strlen($text), 'start' => $offset];
            }
            $offset += strlen($text);
        }
        $lines = self::lines($classes);
        $count = count($significant);
        for ($i = 0; $i + 2 < $count; $i++) {
            $t = $significant[$i];
            if ($t['id'] !== T_CONSTANT_ENCAPSED_STRING || trim($t['text'], '\'"') !== 'collections' || $significant[$i + 1]['id'] !== T_DOUBLE_ARROW) {
                continue;
            }
            $open = $significant[$i + 2];
            if ($open['text'] === '[') {
                $at = $open['end'];
            } elseif ($open['id'] === T_ARRAY && ($significant[$i + 3]['text'] ?? '') === '(') {
                $at = $significant[$i + 3]['end'];
            } else {
                return null; // 'collections' => something else (a variable, a function): leave it to the user
            }
            return substr($config, 0, $at) . "\n" . $lines . ltrim(substr($config, $at), "\n");
        }
        // No key yet: before the returned array's closing "];" or ");"
        if ($count >= 2 && $significant[$count - 1]['text'] === ';' && in_array($significant[$count - 2]['text'], [']', ')'], true)) {
            $close = $significant[$count - 2];
            $before = rtrim(substr($config, 0, $close['start']));
            $comma = str_ends_with($before, ',') || str_ends_with($before, '[') || str_ends_with($before, '(') ? '' : ',';
            return $before . $comma . "\n\n    // Collections (php flo site:collection)\n    'collections' => [\n" . $lines . "    ],\n" . substr($config, $close['start']);
        }
        return null;
    }

    /** Posts → Post, Categories → Category, News → News. */
    public static function singular(string $word): string
    {
        if (preg_match('/ies$/i', $word) === 1) { return substr($word, 0, -3) . (ctype_upper(substr($word, -3, 1)) ? 'Y' : 'y'); }
        if (preg_match('/(ss|us|ws|is)$/i', $word) === 1) { return $word; }
        return preg_match('/s$/i', $word) === 1 ? substr($word, 0, -1) : $word;
    }
}
