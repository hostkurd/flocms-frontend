<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\Frontend\Starters\StarterSetup;
use FloCMS\Frontend\Support\SiteTemplate;
use FloCMS\Frontend\Tests\TestCase;

/**
 * 0.1.4: choosing a starter on a template that doesn't link the Site Style
 * (the skeleton's "default") makes the package's "site" template active.
 */
final class SiteTemplateTest extends TestCase
{
    private ?string $env = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanSite();
        $this->env = $_ENV['APP_TEMPLATE'] ?? null;
        $_ENV['APP_TEMPLATE'] = 'default';
        $this->write('templates/default/layouts/default.html', "<html><head><title>Skeleton</title></head><body>{!! \$content !!}</body></html>\n");
        $this->write('.env', "APP_ENV=local\nAPP_TEMPLATE=default\nAPP_URL=https://example.test\n");
        $this->write('views/pages/index.html', '<p>Welcome</p>');
    }

    protected function tearDown(): void
    {
        $this->env === null ? $_ENV['APP_TEMPLATE'] = 'default' : $_ENV['APP_TEMPLATE'] = $this->env;
        @chmod(ROOT . '/.env', 0644);
        @unlink(ROOT . '/.env');
        $this->cleanSite();
        parent::tearDown();
    }

    public function testAStarterOnTheSkeletonsTemplateSwitchesToTheSiteTemplate(): void
    {
        self::assertFalse(SiteTemplate::isReady('default'));

        $result = StarterSetup::apply('studio');

        self::assertSame(['changed' => true, 'template' => 'site', 'problem' => null], $result['template']);
        self::assertStringContainsString("APP_TEMPLATE=site\n", (string) file_get_contents(ROOT . '/.env'));
        self::assertStringContainsString("APP_URL=https://example.test\n", (string) file_get_contents(ROOT . '/.env'), 'the rest of .env is kept');
        self::assertTrue(SiteTemplate::isReady('site'));
        self::assertFileExists(ROOT . '/templates/site/style.php');
        self::assertSame('site', $_ENV['APP_TEMPLATE']);
    }

    public function testATemplateThatLinksTheStyleIsKept(): void
    {
        $this->write('templates/default/layouts/default.html', "<html>{!! site_head() !!}<body>{!! \$content !!}</body></html>\n");

        $result = StarterSetup::apply('blank');

        self::assertSame(['changed' => false, 'template' => 'default', 'problem' => null], $result['template']);
        self::assertStringContainsString("APP_TEMPLATE=default\n", (string) file_get_contents(ROOT . '/.env'));
        self::assertDirectoryDoesNotExist(ROOT . '/templates/site');
    }

    public function testASiteTemplateOfTheSitesOwnIsKept(): void
    {
        $this->write('templates/site/layouts/default.html', "<html>{!! site_head() !!}<!-- ours --></html>\n");

        StarterSetup::apply('blank');

        self::assertStringContainsString('<!-- ours -->', (string) file_get_contents(ROOT . '/templates/site/layouts/default.html'));
        self::assertStringContainsString("APP_TEMPLATE=site\n", (string) file_get_contents(ROOT . '/.env'));
    }

    public function testAnEnvThatCantBeWrittenIsReported(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('root can write a read-only file');
        }
        chmod(ROOT . '/.env', 0444);

        $result = StarterSetup::apply('blank');

        self::assertFalse($result['template']['changed']);
        self::assertSame('.env can\'t be changed', $result['template']['problem']);
    }

    public function testTheCommandSaysSo(): void
    {
        $result = StarterSetup::apply('landing');
        self::assertTrue($result['template']['changed']);
        // And nothing more to do the second time.
        self::assertFalse(SiteTemplate::ensure()['changed']);
    }
}
