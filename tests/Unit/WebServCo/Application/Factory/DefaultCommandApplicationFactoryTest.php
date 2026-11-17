<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\Application\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use WebServCo\Application\Factory\DefaultCommandApplicationFactory;
use WebServCo\Application\Service\DefaultApplication;
use WebServCo\Command\Contract\CommandRunnerInterface;
use WebServCo\DependencyContainer\Contract\ServiceContainerInterface;
use WebServCo\Error\Contract\ErrorHandlingServiceFactoryInterface;
use WebServCo\Error\Contract\ErrorHandlingServiceInterface;
use WebServCo\Stopwatch\Contract\LapTimerInterface;

#[CoversClass(DefaultCommandApplicationFactory::class)]
#[UsesClass(DefaultApplication::class)]
final class DefaultCommandApplicationFactoryTest extends TestCase
{
    public function testCreateDefaultCommandApplicationUsesTheLapTimerOfTheServiceContainer(): void
    {
        // `run()` makes the laps `run: start` and `run: end`.
        $lapTimer = $this->createMock(LapTimerInterface::class);
        $lapTimer->expects(self::exactly(2))->method('lap')->willReturn(true);

        $serviceContainer = $this->createMock(ServiceContainerInterface::class);
        $serviceContainer->expects(self::once())->method('getLapTimer')->willReturn($lapTimer);
        $serviceContainer->method('getLogger')->willReturn(new NullLogger());

        $command = self::createStub(CommandRunnerInterface::class);
        $command->method('run')->willReturn(true);

        $application = $this->createApplicationFactory($serviceContainer)->createDefaultCommandApplication($command);

        self::assertTrue($application->run());
    }

    private function createApplicationFactory(
        ServiceContainerInterface $serviceContainer,
    ): DefaultCommandApplicationFactory {
        $errorHandlingServiceFactory = self::createStub(ErrorHandlingServiceFactoryInterface::class);
        $errorHandlingServiceFactory->method('createErrorHandlingService')->willReturn(
            self::createStub(ErrorHandlingServiceInterface::class),
        );

        return new DefaultCommandApplicationFactory($errorHandlingServiceFactory, $serviceContainer);
    }
}
