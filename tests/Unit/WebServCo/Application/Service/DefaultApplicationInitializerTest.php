<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\Application\Service;

use OutOfBoundsException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use WebServCo\Application\Service\DefaultApplicationInitializer;
use WebServCo\Exception\Contract\UncaughtExceptionHandlerInterface;

use function array_diff;
use function array_keys;
use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hrtime;
use function implode;
use function is_array;
use function is_dir;
use function is_link;
use function mkdir;
use function random_bytes;
use function realpath;
use function restore_exception_handler;
use function rmdir;
use function scandir;
use function set_exception_handler;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * Every test gets its own project directory. A test puts back what the initializer changed:
 * the exception handler, and the keys the configuration added to `$_SERVER`.
 *
 * @SuppressWarnings("PHPMD.Superglobals")
 */
#[CoversClass(DefaultApplicationInitializer::class)]
final class DefaultApplicationInitializerTest extends TestCase
{
    private bool $exceptionHandlerIsSet = false;
    private string $projectPath = '';

    /**
     * @var list<int|string>
     */
    private array $serverKeys = [];

    public function testInitializeReturnsTheContainerWithTheConfigurationAndTheLaps(): void
    {
        $this->createProjectDirectory(true, true);

        $applicationDependencyContainer = (new DefaultApplicationInitializer())->initialize(
            $this->projectPath,
            (int) hrtime(true),
        );
        $this->exceptionHandlerIsSet = true;

        $serviceContainer = $applicationDependencyContainer->getServiceContainer();
        self::assertSame('a value', $serviceContainer->getConfigurationGetter()->getString('INITIALIZER_TEST_STRING'));
        self::assertSame(3306, $serviceContainer->getConfigurationGetter()->getInt('INITIALIZER_TEST_INT'));
        self::assertSame(['autoload', 'initialization'], array_keys($serviceContainer->getLapTimer()->getLaps()));
    }

    public function testInitializeSetsTheUncaughtExceptionHandler(): void
    {
        $this->createProjectDirectory(true, true);

        (new DefaultApplicationInitializer())->initialize($this->projectPath, (int) hrtime(true));
        $this->exceptionHandlerIsSet = true;

        // Read the handler: set another one, which returns it, and put it back at once.
        $exceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        // The handler is `[$handler, 'handle']`.
        self::assertInstanceOf(
            UncaughtExceptionHandlerInterface::class,
            is_array($exceptionHandler) ? $exceptionHandler[0] : $exceptionHandler,
        );
    }

    public function testInitializeLogsAndThrowsWithoutTheConfigurationFile(): void
    {
        $this->createProjectDirectory(false, true);

        try {
            (new DefaultApplicationInitializer())->initialize($this->projectPath, (int) hrtime(true));
            self::fail('The initialization did not fail.');
        } catch (OutOfBoundsException $exception) {
            self::assertSame('Configuration file path not readable.', $exception->getMessage());
        }

        self::assertStringContainsString(
            'emergency Configuration file path not readable. [context]',
            $this->readLog('application'),
        );
    }

    public function testInitializeThrowsWithoutTheLogDirectory(): void
    {
        $this->createProjectDirectory(true, false);

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageIs('Log directory is not writable.');

        (new DefaultApplicationInitializer())->initialize($this->projectPath, (int) hrtime(true));
    }

    public function testInitializeMakesTheProjectPathAbsolute(): void
    {
        $this->createProjectDirectory(true, true);

        // Without the trailing separator, and with `..`, like `__DIR__ . '/..'` in a runner script.
        $applicationDependencyContainer = (new DefaultApplicationInitializer())->initialize(
            sprintf('%sconfig/..', $this->projectPath),
            (int) hrtime(true),
        );
        $this->exceptionHandlerIsSet = true;

        $absoluteProjectPath = realpath($this->projectPath);
        if ($absoluteProjectPath === false) {
            self::fail('The project directory does not exist.');
        }
        self::assertSame(
            sprintf('%s/', $absoluteProjectPath),
            $applicationDependencyContainer->getServiceContainer()->getConfigurationGetter()->getString('PROJECT_PATH'),
        );
    }

    public function testInitializeThrowsWithoutTheProjectPath(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('Project path does not exist.');

        (new DefaultApplicationInitializer())->initialize(
            sprintf('%snot-a-directory', $this->projectPath),
            (int) hrtime(true),
        );
    }

    public function testInitializeUsesTheConfigurationFileOfTheConstructor(): void
    {
        $this->createConfigurationFile('settings', 'application.ini');
        $this->createProjectDirectory(false, true);

        $applicationDependencyContainer = (new DefaultApplicationInitializer(
            'application',
            'settings',
            'application.ini',
        ))->initialize($this->projectPath, (int) hrtime(true));
        $this->exceptionHandlerIsSet = true;

        self::assertSame(
            'a value',
            $applicationDependencyContainer->getServiceContainer()->getConfigurationGetter()->getString(
                'INITIALIZER_TEST_STRING',
            ),
        );
    }

    public function testInitializeLogsWithTheLoggerChannelOfTheConstructor(): void
    {
        $this->createProjectDirectory(false, true);

        try {
            (new DefaultApplicationInitializer('initializer'))->initialize($this->projectPath, (int) hrtime(true));
            self::fail('The initialization did not fail.');
        } catch (OutOfBoundsException $exception) {
            self::assertSame('Configuration file path not readable.', $exception->getMessage());
        }

        // The problem is logged in the channel `initializer`, not in `application`.
        self::assertStringContainsString(
            'emergency Configuration file path not readable. [context]',
            $this->readLog('initializer'),
        );
        self::assertSame('', $this->readLog('application'));
    }

    #[Override]
    protected function setUp(): void
    {
        // @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $this->serverKeys = array_keys($_SERVER);
        // @phpcs:enable
        $this->projectPath = sprintf(
            '%s/webservco-application-default-test-%s/',
            sys_get_temp_dir(),
            bin2hex(random_bytes(4)),
        );
        mkdir($this->projectPath, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->exceptionHandlerIsSet) {
            restore_exception_handler();
            $this->exceptionHandlerIsSet = false;
        }

        // @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        foreach (array_diff(array_keys($_SERVER), $this->serverKeys) as $key) {
            unset($_SERVER[$key]);
        }
        // @phpcs:enable

        $this->removeDirectory($this->projectPath);
    }

    private function createProjectDirectory(bool $withConfigurationFile, bool $withLogDirectory): bool
    {
        if ($withConfigurationFile) {
            $this->createConfigurationFile('config', '.env.ini');
        }

        if ($withLogDirectory) {
            mkdir(sprintf('%svar/log', $this->projectPath), 0o700, true);
        }

        return true;
    }

    private function createConfigurationFile(string $directory, string $file): bool
    {
        mkdir(sprintf('%s%s', $this->projectPath, $directory));

        return file_put_contents(
            sprintf('%s%s/%s', $this->projectPath, $directory, $file),
            "INITIALIZER_TEST_STRING = \"a value\"\nINITIALIZER_TEST_INT = 3306\n",
        ) !== false;
    }

    /**
     * @return string the logs of the channel, of any date
     */
    private function readLog(string $channel): string
    {
        $data = [];
        $filePaths = glob(sprintf('%svar/log/*/%s.log', $this->projectPath, $channel));
        foreach ($filePaths === false ? [] : $filePaths as $filePath) {
            $data[] = (string) file_get_contents($filePath);
        }

        return implode('', $data);
    }

    private function removeDirectory(string $directory): bool
    {
        $names = scandir($directory);
        foreach (array_diff($names === false ? [] : $names, ['.', '..']) as $name) {
            $path = sprintf('%s/%s', $directory, $name);
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);

                continue;
            }
            unlink($path);
        }

        return rmdir($directory);
    }
}
