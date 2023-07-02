<?php

declare(strict_types=1);

namespace WebServCo\Application\Factory;

use WebServCo\Application\Contract\ApplicationInterface;
use WebServCo\Application\Contract\ApplicationRunnerInterface;
use WebServCo\Application\Service\DefaultApplication;
use WebServCo\DependencyContainer\Contract\ServiceContainerInterface;
use WebServCo\Error\Contract\ErrorHandlingServiceFactoryInterface;
use WebServCo\Stopwatch\Contract\LapTimerInterface;

final class DefaultCommandApplicationFactory
{
    public function __construct(
        private ErrorHandlingServiceFactoryInterface $errorHandlingServiceFactory,
        private ServiceContainerInterface $serviceContainer,
    ) {
    }

    public function createCommandApplication(
        LapTimerInterface $lapTimer,
        ApplicationRunnerInterface $applicationRunner,
    ): ApplicationInterface {
        return new DefaultApplication(
            $applicationRunner,
            $this->errorHandlingServiceFactory->createErrorHandlingService(),
            $this->serviceContainer->getLogger('application'),
            $lapTimer,
        );
    }
}
