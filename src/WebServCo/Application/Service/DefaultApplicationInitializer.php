<?php

declare(strict_types=1);

namespace WebServCo\Application\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use UnexpectedValueException;
use WebServCo\Configuration\Service\ConfigurationFileProcessor;
use WebServCo\Configuration\Service\IniServerConfigurationContainer;
use WebServCo\DependencyContainer\Contract\ApplicationDependencyContainerInterface;
use WebServCo\DependencyContainer\Service\ApplicationDependencyContainer;
use WebServCo\Exception\Factory\DefaultExceptionHandlerFactory;
use WebServCo\Stopwatch\Factory\LapTimerFactory;

use function realpath;
use function rtrim;
use function set_exception_handler;

use const DIRECTORY_SEPARATOR;

/**
 * The start of a runner script, the same for every command and web entry point.
 *
 * In this order: the project path, made absolute; the lap timer, started at the start time, with the lap `autoload`;
 * the application dependency container; the logger; the configuration file; the uncaught exception handler;
 * the lap `initialization`.
 *
 * Nothing is printed. A problem is logged as `emergency`, with the logger there is by then
 * (a `NullLogger` until the logger is made), and thrown again: the script prints a message and ends.
 */
final class DefaultApplicationInitializer
{
    /**
     * @param string $loggerChannel the channel of the logger for the problems and the uncaught exceptions
     * @param string $configurationDirectory the directory of the configuration file, relative to the project path
     */
    public function __construct(
        private string $loggerChannel = 'application',
        private string $configurationDirectory = 'config',
        private string $configurationFile = '.env.ini',
    ) {
    }

    /**
     * @param string $projectPath the directory with the configuration file and `var/log/`,
     *     with or without the trailing separator, for example `__DIR__ . '/..'`
     * @param int $startTime `hrtime(true)` at the start of the script
     */
    public function initialize(string $projectPath, int $startTime): ApplicationDependencyContainerInterface
    {
        $logger = new NullLogger();

        try {
            $projectPath = $this->createAbsoluteProjectPath($projectPath);

            $lapTimerFactory = new LapTimerFactory();
            $lapTimer = $lapTimerFactory->createLapTimer();
            $lapTimer->start($startTime);
            $lapTimer->lap('autoload');

            $applicationDependencyContainer = new ApplicationDependencyContainer($projectPath, $lapTimer);
            $logger = $applicationDependencyContainer->getServiceContainer()->getLogger($this->loggerChannel);

            $this->processConfigurationFile($projectPath);
            $this->setExceptionHandler($logger);

            $lapTimer->lap('initialization');

            return $applicationDependencyContainer;
        } catch (Throwable $throwable) {
            $logger->emergency($throwable->getMessage(), ['throwable' => $throwable]);

            throw $throwable;
        }
    }

    /**
     * @return string the path with the trailing separator, the same as the configuration value `PROJECT_PATH`
     */
    private function createAbsoluteProjectPath(string $projectPath): string
    {
        $absoluteProjectPath = realpath($projectPath);
        if ($absoluteProjectPath === false) {
            throw new UnexpectedValueException('Project path does not exist.');
        }

        return rtrim($absoluteProjectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function processConfigurationFile(string $projectPath): bool
    {
        $configurationContainer = new IniServerConfigurationContainer();
        $configurationFileProcessor = new ConfigurationFileProcessor(
            $configurationContainer->getConfigurationDataProcessor(),
            $configurationContainer->getConfigurationLoader(),
            $configurationContainer->getConfigurationSetter(),
        );

        return $configurationFileProcessor->processConfigurationFile(
            $projectPath,
            $this->configurationDirectory,
            $this->configurationFile,
        );
    }

    private function setExceptionHandler(LoggerInterface $logger): bool
    {
        $exceptionHandlerFactory = new DefaultExceptionHandlerFactory();
        set_exception_handler([$exceptionHandlerFactory->createUncaughtExceptionHandler($logger), 'handle']);

        return true;
    }
}
