<?php
declare(strict_types=1);

namespace FloCMS\Frontend\Tests\Unit;

use FloCMS\CLI\Console\Testing\CommandTester;
use FloCMS\CLI\Project;
use FloCMS\Frontend\Console\ComponentsCommand;
use FloCMS\Frontend\Console\DoctorCommand;
use FloCMS\Frontend\Console\InstallCommand;
use FloCMS\Frontend\Console\MakeComponentCommand;
use FloCMS\Frontend\Console\StarterCommand;
use FloCMS\Frontend\Console\StyleCommand;
use FloCMS\Frontend\Tests\TestCase;

final class ConsoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(ROOT . '/.env', "APP_TEMPLATE=default\nSITE_NAME=FloCMS\n");
    }

    protected function tearDown(): void
    {
        @unlink(ROOT . '/.env');
        unset($_ENV['APP_TEMPLATE']);
        parent::tearDown();
    }

    private function command(object $command, array $arguments = [], array $inputs = []): CommandTester
    {
        $tester = new CommandTester($command, new Project(ROOT));
        $tester->execute($arguments, $inputs, ['interactive' => $inputs !== []]);
        return $tester;
    }

    private static function shown(CommandTester $tester): string
    {
        return $tester->getDisplay() . $tester->getErrorOutput();
    }

    public function testInstallWritesTheFilesOnceAndActivatesTheTemplate(): void
    {
        $tester = $this->command(new InstallCommand(), ['--activate' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        foreach (['config/frontend.php', 'components/README.md', 'templates/site/layouts/default.html', 'templates/site/style.php'] as $file) {
            self::assertFileExists(ROOT . '/' . $file);
            self::assertStringContainsString('Created ' . $file, $tester->getDisplay());
        }
        self::assertStringContainsString('APP_TEMPLATE=site', (string) file_get_contents(ROOT . '/.env'));
        self::assertIsArray(require ROOT . '/config/frontend.php');
        self::assertSame(\FloCMS\Frontend\Style\Style::DEFAULTS['colours']['brand'], (require ROOT . '/templates/site/style.php')['colours']['brand']);
        file_put_contents(ROOT . '/config/frontend.php', '<?php return ["mine" => true];');
        $again = $this->command(new InstallCommand(), ['--activate' => true]);
        self::assertStringContainsString('Kept config/frontend.php (already there)', $again->getDisplay());
        self::assertSame('<?php return ["mine" => true];', file_get_contents(ROOT . '/config/frontend.php'));
    }

    public function testMakeComponent(): void
    {
        $tester = $this->command(new MakeComponentCommand(), ['OurTeam', '--css' => true, '--js' => true, '--block' => true]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        foreach (['OurTeam.php', 'our-team.html', 'our-team.css', 'our-team.js'] as $file) {
            self::assertFileExists(ROOT . '/components/our-team/' . $file);
        }
        self::assertStringContainsString('protected static bool $block = true;', (string) file_get_contents(ROOT . '/components/our-team/OurTeam.php'));
        \FloCMS\Frontend\Components\Components::reset();
        self::assertStringContainsString('<h2>Our team</h2>', \FloCMS\Frontend\Components\Components::render('our-team', ['heading' => 'Our team']));
        self::assertSame(1, $this->command(new MakeComponentCommand(), ['OurTeam'])->getStatusCode(), 'never overwrites');
        self::assertNotSame(0, $this->command(new MakeComponentCommand(), ['bad-name'])->getStatusCode());
        $list = $this->command(new ComponentsCommand());
        self::assertMatchesRegularExpression('/our-team\s+.*site\s+.*yes/', $list->getDisplay());
    }

    public function testStyleCheck(): void
    {
        self::assertSame(0, $this->command(new StyleCommand(), ['--check' => true])->getStatusCode());
        $this->write('templates/default/style.php', "<?php return ['colours' => ['muted' => '#B0B6BC']];");
        $tester = $this->command(new StyleCommand(), ['--check' => true]);
        self::assertSame(1, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/light\s+.\s*muted\s+.\s*background/', self::shown($tester));
        self::assertStringContainsString('--fe-brand:#0a7fa8', $this->command(new StyleCommand(), ['--css' => true])->getDisplay());
    }

    public function testStarterAndDoctor(): void
    {
        $doctor = $this->command(new DoctorCommand());
        self::assertSame(1, $doctor->getStatusCode(), 'the skeleton\'s layout does not call site_head() yet');
        self::assertStringContainsString('doesn\'t call {!! site_head() !!}', self::shown($doctor));
        $this->command(new InstallCommand(), ['--activate' => true]);
        $_ENV['APP_TEMPLATE'] = 'site'; // a new process reads .env again
        $doctor = $this->command(new DoctorCommand());
        self::assertSame(0, $doctor->getStatusCode(), self::shown($doctor));
        $starter = $this->command(new StarterCommand(), ['journal']);
        self::assertSame(0, $starter->getStatusCode(), $starter->getDisplay());
        self::assertStringContainsString('Wrote views/pages/index.html', $starter->getDisplay());
        self::assertSame(1, $this->command(new StarterCommand(), ['studio'])->getStatusCode(), 'once');
        self::assertSame(0, $this->command(new StarterCommand(), ['studio', '--force' => true])->getStatusCode());
    }
}
