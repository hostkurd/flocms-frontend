<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Core\Database;
use FloCMS\Core\Http\Request;
use FloCMS\Frontend\Collections\CollectionPages;
use FloCMS\Frontend\Collections\Collections;
use FloCMS\Frontend\Collections\Feed;
use FloCMS\Frontend\Collections\Kits\BlogCategories;
use FloCMS\Frontend\Collections\Kits\BlogPosts;
use FloCMS\Frontend\Collections\Kits\ShopCategories;
use FloCMS\Frontend\Collections\Kits\ShopProducts;
use FloCMS\Frontend\Collections\Query;
use FloCMS\Frontend\Collections\Schema;
use FloCMS\Frontend\Collections\Sources\TableSource;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Seo\Seo;
use FloCMS\Frontend\Seo\Sitemap;
use FloCMS\Frontend\Support\Languages;
use FloCMS\Frontend\Tests\TestCase;

final class TestPostCategories extends BlogCategories
{
    protected static string $name = 'test_post_categories';
}

final class TestPosts extends BlogPosts
{
    protected static string $name = 'test_posts';
    protected static string|array $path = ['en' => 'blog', 'ar' => 'مدونة'];
    protected static string $categories = TestPostCategories::class;
}

final class TestProductCategories extends ShopCategories
{
    protected static string $name = 'test_product_categories';
}

final class TestProducts extends ShopProducts
{
    protected static string $name = 'test_products';
    protected static string $categories = TestProductCategories::class;
}

final class TestPagedPosts extends BlogPosts
{
    protected static string $name = 'test_paged';
    protected static ?string $table = 'site_test_posts';
    protected static string|array $path = 'paged';
    protected static int $perPage = 2;
    protected static string $categories = TestPostCategories::class;
}

final class TestTeam extends \FloCMS\Frontend\Collections\Collection
{
    protected static string $name = 'test_team';
    protected static string|array $path = 'team';
    protected static bool $publishing = false;

    public function fields(): array
    {
        return [\FloCMS\Frontend\Components\Prop::text('title'), \FloCMS\Frontend\Components\Prop::slug('slug'), \FloCMS\Frontend\Components\Prop::tags('tags')];
    }

    public function groups(): array
    {
        return ['tag' => 'tags']; // tags not filterable(): a group page still filters by them
    }
}

/** 0.2: collections with the blog and shop kits, on SQLite. */
final class CollectionsTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Database(new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]));
        TableSource::useDatabase($this->db);
        Frontend::useConfig(['collections' => [TestPostCategories::class, TestPosts::class, TestProductCategories::class, TestProducts::class]], ROOT);
        foreach ([TestPostCategories::class, TestPosts::class, TestProductCategories::class, TestProducts::class] as $class) {
            $file = ROOT . '/storage/cache/' . $class::name() . '-migration.php';
            file_put_contents($file, Schema::migration($class::get()));
            (require $file)->up($this->db);
        }
        $this->seed();
        $_GET = [];
    }

    protected function tearDown(): void
    {
        TableSource::useDatabase(null);
        Collections::clear();
        $_GET = [];
        parent::tearDown();
    }

    private function seed(): void
    {
        $j = static fn (mixed $v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE);
        $cat = fn (int $id, string $en, string $slug, string $ar = '') => $this->db->table('site_test_post_categories')->insert(['id' => $id, 'title' => $j(['en' => $en, 'ar' => $ar]), 'slug' => $j(['en' => $slug]), 'sort' => $id]);
        $cat(1, 'Projects', 'projects', 'مشاريع');
        $cat(2, 'Advice', 'advice', 'نصائح');
        $post = fn (array $row) => $this->db->table('site_test_posts')->insert($row + ['status' => 'published', 'sort' => 0]);
        $post(['id' => 1, 'title' => $j(['en' => 'Choosing a stone', 'ar' => 'اختيار الحجر']), 'slug' => $j(['en' => 'choosing-a-stone', 'ar' => 'اختيار-الحجر']), 'excerpt' => $j(['en' => 'Four stones compared.']), 'body' => $j(['en' => '<p>' . str_repeat('word ', 450) . '</p><script>alert(1)</script>', 'ar' => '<p>نص</p>']), 'category' => 2, 'tags' => $j(['en' => ['stone', 'courtyard'], 'ar' => ['حجر']]), 'publish_at' => '2026-11-04 09:00:00', 'created_at' => '2026-11-04 09:00:00']);
        $post(['id' => 2, 'title' => $j(['en' => 'A house in Ankawa']), 'slug' => $j(['en' => 'a-house-in-ankawa']), 'category' => 1, 'tags' => $j(['en' => ['houses']]), 'publish_at' => '2026-10-28 09:00:00', 'created_at' => '2026-10-28 09:00:00']);
        $post(['id' => 3, 'title' => $j(['en' => 'Lime plaster is back']), 'slug' => $j(['en' => 'lime-plaster']), 'category' => 2, 'publish_at' => '2026-10-19 09:00:00', 'created_at' => '2026-10-19 09:00:00']);
        $post(['id' => 4, 'title' => $j(['en' => 'A draft']), 'slug' => $j(['en' => 'a-draft']), 'status' => 'draft', 'created_at' => '2026-11-10 09:00:00']);
        $post(['id' => 5, 'title' => $j(['en' => 'Next week']), 'slug' => $j(['en' => 'next-week']), 'status' => 'scheduled', 'publish_at' => date('Y-m-d H:i:s', time() + 7 * 86400), 'created_at' => '2026-11-10 09:00:00']);
        $post(['id' => 7, 'title' => $j(['en' => 'Scheduled, no date']), 'slug' => $j(['en' => 'no-date']), 'status' => 'scheduled', 'created_at' => '2026-11-10 09:00:00']);
        $post(['id' => 6, 'title' => $j(['en' => 'Gone']), 'slug' => $j(['en' => 'gone']), 'unpublish_at' => '2020-01-01 00:00:00', 'created_at' => '2019-01-01 00:00:00']);

        $pc = fn (int $id, string $en, string $slug, ?int $parent) => $this->db->table('site_test_product_categories')->insert(['id' => $id, 'title' => $j(['en' => $en]), 'slug' => $j(['en' => $slug]), 'parent' => $parent, 'sort' => $id]);
        $pc(1, 'Lighting', 'lighting', null);
        $pc(2, 'Pendants', 'pendants', 1);
        $pc(3, 'Furniture', 'furniture', null);
        $product = fn (array $row) => $this->db->table('site_test_products')->insert($row + ['status' => 'published', 'sort' => 0, 'availability' => 'in_stock']);
        $product(['id' => 1, 'title' => $j(['en' => 'Linen pendant lamp', 'ar' => 'مصباح معلّق من الكتان']), 'slug' => $j(['en' => 'linen-pendant-lamp']), 'price' => '240.00', 'category' => 2, 'images' => $j(['/img/a.webp', '/img/b.webp']), 'specs' => $j(['en' => [['label' => 'Size', 'value' => '45 cm']]]), 'publish_at' => '2026-11-10 09:00:00', 'created_at' => '2026-11-10 09:00:00']);
        $product(['id' => 2, 'title' => $j(['en' => 'Brass chandelier']), 'slug' => $j(['en' => 'brass-chandelier']), 'price' => '1180.00', 'category' => 1, 'availability' => 'made_to_order', 'publish_at' => '2026-11-09 09:00:00', 'created_at' => '2026-11-09 09:00:00']);
        $product(['id' => 3, 'title' => $j(['en' => 'Oak side table']), 'slug' => $j(['en' => 'oak-side-table']), 'price' => '180.00', 'category' => 3, 'publish_at' => '2026-11-08 09:00:00', 'created_at' => '2026-11-08 09:00:00']);
        $product(['id' => 4, 'title' => $j(['en' => 'Kitchen island']), 'slug' => $j(['en' => 'kitchen-island']), 'price' => null, 'category' => 3, 'availability' => 'made_to_order', 'publish_at' => '2026-11-07 09:00:00', 'created_at' => '2026-11-07 09:00:00']);
    }

    private function page(string $path, array $get = []): ?\FloCMS\Core\Http\Response
    {
        $_GET = $get;
        Seo::reset();
        return CollectionPages::fallback($path, new Request($get, [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/' . Languages::current() . '/' . $path], [], []));
    }

    public function testOnlyWhatVisitorsMaySee(): void
    {
        $titles = array_map(static fn ($i): string => $i->title(), TestPosts::get()->source()->all());
        self::assertSame(['Choosing a stone', 'A house in Ankawa', 'Lime plaster is back'], $titles, 'newest first; no draft, no future, no expired');
    }

    public function testSearchFiltersAndOrder(): void
    {
        $source = TestProducts::get()->source();
        $query = Query::fromRequest(TestProducts::get(), ['category' => ['lighting'], 'price_from' => '50', 'price_to' => '400', 'sort' => 'price_low']);
        $result = $source->query($query);
        self::assertSame(['Linen pendant lamp'], array_map(static fn ($i): string => $i->title(), $result->items), 'Lighting includes Pendants; the chandelier costs more');
        self::assertSame(2, $query->filterCount());

        $byPrice = $source->query(Query::fromRequest(TestProducts::get(), ['sort' => 'price_high']));
        self::assertSame(['Brass chandelier', 'Linen pendant lamp', 'Oak side table', 'Kitchen island'], array_map(static fn ($i): string => $i->title(), $byPrice->items), 'no price last');

        $search = $source->query(Query::fromRequest(TestProducts::get(), ['q' => 'chandel']));
        self::assertSame(1, $search->total);
        Languages::use('ar');
        self::assertSame(1, $source->query(Query::fromRequest(TestProducts::get(), ['q' => 'الكتان']))->total, 'Arabic words are found');
        Languages::use('en');

        $facets = $source->facets(new Query(), 'availability');
        self::assertSame(['in_stock' => 2, 'made_to_order' => 2], $facets);
        // A search for "%" is not a wildcard
        self::assertSame(0, $source->query(Query::fromRequest(TestProducts::get(), ['q' => '%']))->total);
    }

    public function testTagsAndGroups(): void
    {
        $posts = TestPosts::get()->source();
        self::assertSame(1, $posts->query(Query::fromRequest(TestPosts::get(), ['tags' => 'stone']))->total);
        self::assertSame(2, $posts->query(Query::fromRequest(TestPosts::get(), ['category' => 'advice']))->total);
        $page = (string) $this->page('blog/category/advice')?->body();
        self::assertStringContainsString('Choosing a stone', $page);
        self::assertStringNotContainsString('A house in Ankawa', $page);
        self::assertStringContainsString('Lime plaster is back', (string) $this->page('blog/tag/stone')?->body() . (string) $this->page('blog/category/advice')?->body());
        self::assertNull($this->page('blog/tag/nobody-uses-this'));
        self::assertNull($this->page('blog/category/nope'));
    }

    public function testTheListPage(): void
    {
        $response = $this->page('blog');
        self::assertNotNull($response);
        $html = $response->body();
        self::assertStringContainsString('<h1>Blog</h1>', $html);
        self::assertStringContainsString('fe-post-card--featured', $html, 'the newest post is featured on page 1');
        self::assertStringContainsString('href="https://example.test/en/blog/category/advice"', $html);
        self::assertStringContainsString('/en/blog/feed.xml', $html);
        self::assertStringNotContainsString('A draft', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://example.test/en/blog">', Seo::tags());

        $search = (string) $this->page('blog', ['q' => 'zzz'])?->body();
        self::assertStringContainsString('Nothing matches “zzz”', $search);
        self::assertStringContainsString('noindex', Seo::tags(), 'search results are not pages of their own');
    }

    public function testThePostPage(): void
    {
        $html = (string) $this->page('blog/choosing-a-stone')?->body();
        self::assertStringContainsString('<h1>Choosing a stone</h1>', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html, 'rich text is cleaned');
        self::assertStringContainsString('2 min read', $html);
        self::assertStringContainsString('href="https://example.test/en/blog/tag/stone"', $html);
        self::assertStringContainsString('Older post', $html);
        self::assertStringContainsString('More in Advice', $html);
        $tags = Seo::tags();
        self::assertStringContainsString('"@type":"BlogPosting"', $tags);
        self::assertStringContainsString('hreflang="ar" href="https://example.test/ar/' . 'مدونة' . '/' . 'اختيار-الحجر' . '"', $tags, 'the Arabic page has its own address');
        self::assertStringNotContainsString('hreflang="ku"', $tags, 'only the languages it is written in');
        self::assertSame('https://example.test/ar/مدونة/اختيار-الحجر', Languages::switchUrl('ar'));
    }

    public function testAddressesPerLanguage(): void
    {
        Languages::use('ar');
        self::assertStringContainsString('اختيار الحجر', (string) $this->page('مدونة/اختيار-الحجر')?->body());
        // The English address on the Arabic site goes to the Arabic one
        $redirect = $this->page('مدونة/choosing-a-stone');
        self::assertSame(301, $redirect?->status());
        self::assertSame(CollectionPages::asciiUrl('https://example.test/ar/مدونة/اختيار-الحجر'), $redirect?->headers()['Location'] ?? null);
        // A post with no Arabic is shown in English, its canonical the English page
        self::assertStringContainsString('A house in Ankawa', (string) $this->page('مدونة/a-house-in-ankawa')?->body());
        self::assertStringContainsString('<link rel="canonical" href="https://example.test/en/blog/a-house-in-ankawa">', Seo::tags());
        Languages::use('en');
        self::assertNull($this->page('blog/a-draft'), 'drafts are a 404');
        self::assertNull($this->page('blog/choosing-a-stone/more'));
        self::assertNull(CollectionPages::fallback('blog', new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/en/blog'], [], [])));
    }

    public function testTheShopPages(): void
    {
        $list = (string) $this->page('shop', ['category' => ['lighting'], 'price_from' => '50', 'price_to' => '400'])?->body();
        self::assertStringContainsString('Linen pendant lamp', $list);
        self::assertStringNotContainsString('Oak side table', $list);
        self::assertStringContainsString('name="category[]" value="lighting" checked', $list);
        self::assertStringContainsString('aria-label="Remove the filter Lighting"', $list);
        self::assertStringContainsString('Price on request', (string) $this->page('shop')?->body());

        $group = (string) $this->page('shop/category/lighting')?->body();
        self::assertStringContainsString('Pendants', $group, 'its sub-categories');
        self::assertStringContainsString('Brass chandelier', $group);

        Frontend::useConfig(['collections' => [TestPostCategories::class, TestPosts::class, TestProductCategories::class, TestProducts::class], 'shop' => ['ask' => ['whatsapp' => '+964 750 000 0000']]], ROOT);
        $item = (string) $this->page('shop/linen-pendant-lamp')?->body();
        self::assertStringContainsString('$240.00', $item);
        self::assertStringContainsString('https://wa.me/9647500000000?text=Linen%20pendant%20lamp', $item);
        self::assertStringContainsString('<th scope="row">Size</th><td>45 cm</td>', $item);
        self::assertStringContainsString('1 / 2', $item);
        self::assertStringContainsString('"@type":"Product"', Seo::tags());
        self::assertStringContainsString('"priceCurrency":"USD"', Seo::tags());
    }

    public function testFeedAndSitemap(): void
    {
        $xml = Feed::xml(TestPosts::get());
        self::assertStringContainsString('<rss version="2.0"', $xml);
        self::assertSame(3, substr_count($xml, '<item>'));
        self::assertNotFalse(simplexml_load_string($xml));
        self::assertSame('application/rss+xml; charset=utf-8', $this->page('blog/feed.xml')?->headers()['Content-Type'] ?? null);
        self::assertNull($this->page('shop/feed.xml'), 'the shop has no feed');

        Feed::registerSitemap();
        $locs = array_column(Sitemap::entries(), 'loc');
        self::assertContains('https://example.test/en/blog/choosing-a-stone', $locs);
        self::assertContains('https://example.test/en/shop/linen-pendant-lamp', $locs);
        self::assertNotContains('https://example.test/en/blog/a-draft', $locs);
    }

    public function testASiteOverridesAPage(): void
    {
        @mkdir(ROOT . '/templates/default/collections/test_posts', 0777, true);
        file_put_contents(ROOT . '/templates/default/collections/test_posts/item.html', '<p class="mine">{{ $item->title() }}</p>');
        try {
            self::assertStringContainsString('<p class="mine">Choosing a stone</p>', (string) $this->page('blog/choosing-a-stone')?->body());
        } finally {
            exec('rm -rf ' . escapeshellarg(ROOT . '/templates/default/collections'));
        }
    }

    public function testAHandWrittenCollectionWithNoDate(): void
    {
        $file = ROOT . '/storage/cache/test_team-migration.php';
        file_put_contents($file, Schema::migration(TestTeam::get()));
        (require $file)->up($this->db);
        $this->db->table('site_test_team')->insert(['title' => 'Older', 'slug' => 'older', 'tags' => '["indoor/outdoor"]', 'sort' => 0, 'created_at' => '2026-01-01 00:00:00']);
        $this->db->table('site_test_team')->insert(['title' => 'Newer', 'slug' => 'newer', 'tags' => '["oak"]', 'sort' => 0, 'created_at' => '2026-02-01 00:00:00']);
        Collections::register(TestTeam::class);
        self::assertSame('created_at', TestTeam::dateField(), 'no publish_at column without publishing');
        self::assertSame(['Newer', 'Older'], array_map(static fn ($i): string => $i->title(), TestTeam::get()->source()->all()));
        self::assertStringContainsString('Newer', (string) $this->page('team/newer')?->body());
        self::assertSame(1, TestTeam::get()->source()->query(Query::fromRequest(TestTeam::get(), ['q' => 'newer']))->total, 'the title is always searched');
        // A group page filters by its field even when visitors can't filter by it
        $oak = (string) $this->page('team/tag/oak')?->body();
        self::assertStringContainsString('Newer', $oak);
        self::assertStringNotContainsString('Older', $oak);
        self::assertNull($this->page('team/tag/nobody'));
        self::assertStringContainsString('Older', (string) $this->page('team/tag/indoor/outdoor')?->body(), 'a tag with a "/" in it');
    }

    public function testPagesOfTheList(): void
    {
        Collections::register(TestPagedPosts::class);
        $first = (string) $this->page('paged')?->body();
        self::assertStringContainsString('href="https://example.test/en/paged?page=2"', $first, 'the pager links to page 2');
        $second = (string) $this->page('paged', ['page' => '2'])?->body();
        self::assertStringContainsString('Lime plaster is back', $second);
        self::assertStringContainsString('<link rel="canonical" href="https://example.test/en/paged?page=2">', Seo::tags());
        self::assertNull($this->page('paged', ['page' => '3']), 'past the last page');
    }

    public function testWhatTheSearchMatches(): void
    {
        $posts = TestPosts::get()->source();
        foreach (['en', 'ku', '"', 'p>', 'strong', 'script'] as $q) {
            self::assertSame(0, $posts->query(Query::fromRequest(TestPosts::get(), ['q' => $q]))->total, 'nothing matches "' . $q . '": not the JSON, not the markup');
        }
        self::assertSame(1, $posts->query(Query::fromRequest(TestPosts::get(), ['q' => 'stones compared']))->total, 'the excerpt is searched');
        self::assertSame(0, $posts->query(Query::fromRequest(TestPosts::get(), ['q' => 'الحجر']))->total, 'only the page\'s language');
        Languages::use('ar');
        self::assertSame(1, $posts->query(Query::fromRequest(TestPosts::get(), ['q' => 'الحجر']))->total);
        Languages::use('en');
        self::assertNull(Query::fromRequest(TestProducts::get(), ['price_to' => '1e999'])->range, 'not a number to compare');
    }

    public function testScheduledWithoutADateWaits(): void
    {
        self::assertNull($this->page('blog/no-date'));
        self::assertNull(TestPosts::get()->source()->byId(7)?->isVisible() ? 'visible' : null);
    }

    public function testAnotherLanguagesAddressRedirects(): void
    {
        $redirect = $this->page('blog/اختيار-الحجر');
        self::assertSame(301, $redirect?->status(), 'the Arabic address on the English site');
        self::assertSame('https://example.test/en/blog/choosing-a-stone', $redirect?->headers()['Location'] ?? null);
        Languages::use('ar');
        $location = $this->page('مدونة/choosing-a-stone')?->headers()['Location'] ?? '';
        self::assertSame('https://example.test/ar/%D9%85%D8%AF%D9%88%D9%86%D8%A9/%D8%A7%D8%AE%D8%AA%D9%8A%D8%A7%D8%B1-%D8%A7%D9%84%D8%AD%D8%AC%D8%B1', $location, 'a header carries ASCII');
        Languages::use('en');
    }

    public function testTheFeedStaysValidXml(): void
    {
        $this->db->table('site_test_posts')->where('id', '=', 3)->update(['title' => json_encode(['en' => "Lime\x0B plaster"])]);
        TestPosts::get()->source()->forget();
        self::assertNotFalse(simplexml_load_string(Feed::xml(TestPosts::get())));
    }

    public function testTheMigrationsColumns(): void
    {
        $sql = Schema::migration(TestProducts::get());
        self::assertStringContainsString('`price` DECIMAL(12,2) NULL', $sql);
        self::assertStringContainsString('`category` INTEGER NULL', $sql);
        self::assertStringContainsString("`status` VARCHAR(20) NOT NULL DEFAULT \\'draft\\'", $sql);
        self::assertStringNotContainsString('`status`', Schema::migration(TestProductCategories::get()), 'categories have no publishing');
    }
}
