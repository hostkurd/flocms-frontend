<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests;

use FloCMS\Frontend\Components\Components;
use FloCMS\Frontend\Components\Slots;
use FloCMS\Frontend\Frontend;
use FloCMS\Frontend\Seo\Sitemap;
use FloCMS\Frontend\Style\Style;
use FloCMS\Frontend\Support\Format;
use FloCMS\Frontend\Support\Languages;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanSite();
        Frontend::reset();
        Frontend::useConfig([], ROOT);
        Languages::use('en');
        Format::useIntl(null);
        Style::useValues(null);
        Sitemap::clear();
        Components::clearRegistered();
        if (class_exists(\FloCMS\Admin\Admin::class)) {
            \FloCMS\Admin\Admin::useConfig([], true, ROOT); // flocms-admin's Pages off unless a test turns them on
            \FloCMS\Admin\Blocks\BlockRenderer::reset();
        }
        $_COOKIE = [];
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/en/';
        $this->debug(false);
    }

    protected function tearDown(): void
    {
        Slots::reset();
        Languages::use(null);
        $this->debug(false);
        parent::tearDown();
    }

    protected function debug(bool $on): void
    {
        $_ENV['APP_DEBUG'] = $on ? 'true' : 'false';
        putenv('APP_DEBUG=' . ($on ? 'true' : 'false'));
    }

    /** Files the tests wrote in the site folder go between tests. */
    protected function cleanSite(): void
    {
        foreach (['components', 'storage/cache/frontend', 'storage/frontend', 'templates/site', 'views/pages', 'config'] as $folder) {
            exec('rm -rf ' . escapeshellarg(ROOT . '/' . $folder));
        }
        @unlink(ROOT . '/templates/default/style.php');
    }

    protected function write(string $relative, string $contents): void
    {
        $path = ROOT . '/' . $relative;
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0777, true); }
        file_put_contents($path, $contents);
    }

    /** Render a template string as FloCMS does (compiled, with the package's directives). */
    protected function template(string $source, array $data = []): string
    {
        $file = ROOT . '/views/t-' . sha1($source) . '.html';
        file_put_contents($file, $source);
        return \FloCMS\Core\TemplateEngine::renderFile($file, $data);
    }
}
