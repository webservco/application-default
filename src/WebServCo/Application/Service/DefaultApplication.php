<?php

declare(strict_types=1);

namespace WebServCo\Application\Service;

use Psr\Log\LoggerInterface;
use WebServCo\Application\Contract\ApplicationInterface;
use WebServCo\Application\Contract\ApplicationRunnerInterface;
use WebServCo\Command\Contract\CommandRunnerInterface;
use WebServCo\Error\Contract\ErrorHandlingServiceInterface;
use WebServCo\Stopwatch\Contract\LapTimerInterface;

use function json_encode;
use function register_shutdown_function;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * A default application implementation.
 *
 * Runs general application class with custom dependencies specific to implementing project (use interfaces only).
 * Must work with interfaces only.
 * Steps:
 * - bootstrap
 * - run
 * - shutdown
 */
final class DefaultApplication implements ApplicationInterface
{
    public function __construct(
        private ApplicationRunnerInterface | CommandRunnerInterface $applicationRunner,
        private ErrorHandlingServiceInterface $errorHandlingService,
        private LoggerInterface $logger,
        private LapTimerInterface $lapTimer,
    ) {
    }

    public function bootstrap(): bool
    {
        $this->lapTimer->lap(sprintf('%s: start', __FUNCTION__));

        $this->errorHandlingService->initialize();

        register_shutdown_function([$this, 'shutdown']);

        /**
         * Handle errors that happened before script execution.
         */
        $this->errorHandlingService->handlePreExecutionErrors();

        return $this->lapTimer->lap(sprintf('%s: end', __FUNCTION__));
    }

    public function run(): bool
    {
        $this->lapTimer->lap(sprintf('%s: start', __FUNCTION__));

        $this->applicationRunner->run();

        return $this->lapTimer->lap(sprintf('%s: end', __FUNCTION__));
    }

    public function shutdown(): bool
    {
        $this->errorHandlingService->restore();

        $this->lapTimer->lap(__FUNCTION__);

        return $this->logLapTimerResult();
    }

    /**
     * Log lap timer results.
     *
     * json_encode: Despite using JSON_THROW_ON_ERROR flag, Phan 5.4.1 throws PhanPossiblyFalseTypeArgument.
     * If adding is_string check, PHPStan and Psalm instead throw error.
     * Test: @see `Tests\Misc\Phan\PhanPossiblyFalseTypeArgumentTest`
     *
     * @suppress PhanPossiblyFalseTypeArgument
     */
    private function logLapTimerResult(): bool
    {
        $this->logger->debug(
            json_encode(
                [
                    'lapTimer' => $this->lapTimer->getStatistics(),
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        return true;
    }
}
