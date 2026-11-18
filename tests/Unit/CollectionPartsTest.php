<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\CLI\Console\Testing\CommandTester;
use FloCMS\CLI\Project;
use FloCMS\Frontend\Collections\Collection;
use FloCMS\Frontend\Collections\Collections;
use FloCMS\Frontend\Collections\Markdown;
use FloCMS\Frontend\Collections\Query;
use FloCMS\Frontend\Collections\Sources\MarkdownSource;
use FloCMS\Frontend\Collections\Sources\Source;
use FloCMS\Frontend\Components\Prop;
use FloCMS\Frontend\Console\CollectionCommand;
use FloCMS\Frontend\Console\CollectionsCommand;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

final class TestNotes extends Collection
{
    protected static string $name = 'test_notes';
    protected static string|array $path = 'notes';
    protected static bool $publishing = true;

    public function fields(): array
    {
        return [
            Prop::text('title')->translatable(),
            Prop::slug('slug')->translatable(),
            Prop::textarea('body')->translatable(),
            Prop::tags('tags'),
        ];
    }

    protected function makeSource(): Source
    {
        return new MarkdownSource($this);
    }
}

/** 0.2: the new field types, Markdown, Markdown collections and the commands. */
final class CollectionPartsTest extends TestCase
{
    protected function tearDown(): void
    {
        Collections::clear();
        exec('rm -rf ' . escapeshellarg(ROOT . '/content') . ' ' . escapeshellarg(ROOT . '/collections') . ' ' . escapeshellarg(ROOT . '/database'));
        @unlink(ROOT . '/config/frontend.php');
        @unlink(ROOT . '/.env');
        unset($_ENV['APP_TEMPLATE']);
        parent::tearDown();
    }

    public function testTheNewFieldTypes(): void
    {
        self::assertSame('choosing-a-stone', Prop::slug('s')->clean('  Choosing a Stone! '));
        self::assertSame('اختيار-الحجر', Prop::slugify('اختيار الحجر؟'), 'letters in any script are kept');
        self::assertSame('ڕێنمایی-بەرد', Prop::slugify('ڕێنمایی بەرد'));
        self::assertSame(['stone', 'courtyard'], Prop::tags('t')->clean(' stone, courtyard ,stone,, '));
        self::assertSame(['حجر'], Prop::tags('t')->clean('["حجر"]'));
        self::assertSame('240.00', Prop::price('p')->clean('240'));
        self::assertNull(Prop::price('p')->clean('-1'));
        self::assertNull(Prop::price('p')->clean('free'));
        $images = Prop::images('i')->clean('["/a.webp", {"src": "/b.webp", "alt": "B"}, {"src": "javascript:alert(1)"}]');
        self::assertSame(['/a.webp', '/b.webp'], array_column($images, 'src'), 'unsafe addresses are dropped');
        self::assertSame([['label' => 'Size', 'value' => '45 cm'], ['label' => 'Wood', 'value' => 'Oak']], Prop::pairs('p')->clean([['label' => 'Size', 'value' => '45 cm'], ['Wood', 'Oak'], ['label' => '', 'value' => '']]));
        self::assertTrue(Prop::price('p')->filterable()->isFilterable());
    }

    public function testMarkdown(): void
    {
        [$meta, $body] = Markdown::parseFile("---\ntitle: \"Hello: world\"\ntags: [a, b]\nfeatured: true\nprice: 12.5\n---\n# Heading\n\nSome **bold** and a [link](https://example.test).\n");
        self::assertSame(['title' => 'Hello: world', 'tags' => ['a', 'b'], 'featured' => true, 'price' => 12.5], $meta);
        $html = Markdown::html($body . "\n- one\n- two\n\n<script>alert(1)</script>\n");
        self::assertStringContainsString('<h2>Heading</h2>', $html, 'the page title is the h1');
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<a href="https://example.test">link</a>', $html);
        self::assertStringContainsString('<li>one</li>', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertSame([[], 'Just text'], Markdown::parseFile('Just text'));
        self::assertSame(['status' => 'published', 'title' => 'A # b', 'url' => 'https://x.test/#top'], Markdown::frontMatter("status: published  # live\ntitle: \"A # b\"  # note\nurl: https://x.test/#top"), 'comments after a value are dropped');
    }

    public function testAMarkdownCollection(): void
    {
        $folder = ROOT . '/content/test_notes';
        mkdir($folder, 0777, true);
        file_put_contents($folder . '/first-note.md', "---\ntitle: First note\ntags: [stone]\n---\nHello.\n");
        file_put_contents($folder . '/first-note.ar.md', "---\ntitle: الملاحظة الأولى\nslug: الملاحظة-الأولى\n---\nمرحبا.\n");
        file_put_contents($folder . '/draft.md', "---\ntitle: Draft\nstatus: draft\n---\nNot yet.\n");
        Collections::register(TestNotes::class);
        $source = TestNotes::get()->source();
        self::assertSame(['First note'], array_map(static fn ($i): string => $i->title(), $source->all()), 'drafts are hidden');
        $item = $source->find('first-note', 'en');
        self::assertNotNull($item);
        self::assertSame('https://example.test/en/notes/first-note', $item->url('en'));
        self::assertSame('https://example.test/ar/notes/الملاحظة-الأولى', $item->url('ar'));
        self::assertSame(['en', 'ar'], $item->languages());
        Languages::use('ar');
        self::assertSame('الملاحظة الأولى', $item->title());
        self::assertStringContainsString('مرحبا', (string) $item->get('body'));
        Languages::use('en');
        self::assertSame(1, $source->query(Query::fromRequest(TestNotes::get(), ['tags' => 'stone']))->total);
        self::assertSame(0, $source->query(Query::fromRequest(TestNotes::get(), ['q' => 'Draft']))->total);
    }

    public function testTheConfigIsChangedSafely(): void
    {
        $add = ['App\\Collections\\Posts'];
        $commented = "<?php\nreturn [\n    // 'collections' => [App\\Collections\\Old::class],\n    'template' => 'site',\n];\n";
        $out = (string) CollectionCommand::insert($commented, $add);
        self::assertSame(['template' => 'site', 'collections' => ['App\\Collections\\Posts']], eval('?>' . $out), 'a commented-out key is not the key');
        $long = "<?php\nreturn array(\n    'collections' => array(\n        'App\\Collections\\Old',\n    ),\n    'template' => 'site'\n);\n";
        self::assertSame(['collections' => ['App\\Collections\\Posts', 'App\\Collections\\Old'], 'template' => 'site'], eval('?>' . (string) CollectionCommand::insert($long, $add)));
        $noComma = "<?php\nreturn [\n    'template' => 'site'\n];\n";
        self::assertSame(['template' => 'site', 'collections' => ['App\\Collections\\Posts']], eval('?>' . (string) CollectionCommand::insert($noComma, $add)));
        self::assertNull(CollectionCommand::insert("<?php\n\$c = ['collections' => \$x];\nreturn \$c;\n", $add), 'left to the user');
    }

    public function testSingular(): void
    {
        self::assertSame('Post', CollectionCommand::singular('Posts'));
        self::assertSame('Category', CollectionCommand::singular('Categories'));
        self::assertSame('News', CollectionCommand::singular('News'));
        self::assertSame('Class', CollectionCommand::singular('Class'));
        self::assertSame('Project', CollectionCommand::singular('Projects'));
    }

    public function testTheCollectionCommand(): void
    {
        file_put_contents(ROOT . '/.env', "APP_TEMPLATE=default\nSITE_NAME=FloCMS\n");
        @mkdir(ROOT . '/config', 0777, true);
        file_put_contents(ROOT . '/config/frontend.php', "<?php\nuse App\\Collections\\PostCategories;\n\nreturn [\n    // 'collections' => [],\n    'template' => 'site',\n    'collections' => [PostCategories::class],\n];\n");
        $tester = new CommandTester(new CollectionCommand(), new Project(ROOT));
        $tester->execute(['--kit' => 'blog'], [], ['interactive' => false]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay() . $tester->getErrorOutput());
        self::assertFileExists(ROOT . '/collections/Posts.php');
        self::assertFileExists(ROOT . '/collections/PostCategories.php');
        self::assertCount(2, glob(ROOT . '/database/migrations/*_create_site_*_table.php') ?: []);
        $config = require ROOT . '/config/frontend.php';
        self::assertEqualsCanonicalizing(['App\\Collections\\PostCategories', 'App\\Collections\\Posts'], $config['collections'], 'PostCategories was listed already (with use)');
        self::assertStringContainsString("// 'collections' => [],", (string) file_get_contents(ROOT . '/config/frontend.php'), 'the comment is left alone');
        self::assertSame('site', $config['template'], 'the rest is kept');

        $again = new CommandTester(new CollectionCommand(), new Project(ROOT));
        $again->execute(['--kit' => 'blog'], [], ['interactive' => false]);
        self::assertSame(0, $again->getStatusCode());
        self::assertCount(2, glob(ROOT . '/database/migrations/*.php') ?: [], 'never twice');
        self::assertEqualsCanonicalizing(['App\\Collections\\PostCategories', 'App\\Collections\\Posts'], (require ROOT . '/config/frontend.php')['collections']);

        $bad = new CommandTester(new CollectionCommand(), new Project(ROOT));
        $bad->execute(['--kit' => 'forum'], [], ['interactive' => false]);
        self::assertNotSame(0, $bad->getStatusCode());

        Frontend::useConfig(require ROOT . '/config/frontend.php', ROOT);
        $list = new CommandTester(new CollectionsCommand(), new Project(ROOT));
        $list->execute([], [], ['interactive' => false]);
        self::assertMatchesRegularExpression('/posts\s+.*\/blog/', $list->getDisplay());

        // Not migrated yet: the doctor says so
        $doctor = new CommandTester(new \FloCMS\Frontend\Console\DoctorCommand(), new Project(ROOT));
        $doctor->execute([], [], ['interactive' => false]);
        self::assertNotSame(0, $doctor->getStatusCode());
        self::assertStringContainsString('Collection posts can\'t be read (site_posts): php flo migrate?', $doctor->getDisplay() . $doctor->getErrorOutput());
    }
}
