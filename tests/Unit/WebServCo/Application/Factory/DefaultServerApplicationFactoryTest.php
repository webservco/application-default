<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\Application\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use WebServCo\Application\Contract\ApplicationRunnerFactoryInterface;
use WebServCo\Application\Contract\ApplicationRunnerInterface;
use WebServCo\Application\Factory\DefaultServerApplicationFactory;
use WebServCo\Application\Service\DefaultApplication;
use WebServCo\DependencyContainer\Contract\ServiceContainerInterface;
use WebServCo\Error\Contract\ErrorHandlingServiceFactoryInterface;
use WebServCo\Error\Contract\ErrorHandlingServiceInterface;
use WebServCo\Http\Contract\Message\Request\Server\ServerRequestFromServerDataFactoryInterface;
use WebServCo\Stopwatch\Contract\LapTimerInterface;

/**
 * The server request factory is a stub, so the PHP globals that `createDefaultServerApplication` reads do not matter.
 */
#[CoversClass(DefaultServerApplicationFactory::class)]
#[UsesClass(DefaultApplication::class)]
final class DefaultServerApplicationFactoryTest extends TestCase
{
    public function testCreateDefaultServerApplicationUsesTheGivenLapTimer(): void
    {
        // `run()` makes the laps `run: start` and `run: end`.
        $lapTimer = $this->createMock(LapTimerInterface::class);
        $lapTimer->expects(self::exactly(2))->method('lap')->willReturn(true);

        $serviceContainer = $this->createMock(ServiceContainerInterface::class);
        $serviceContainer->expects(self::never())->method('getLapTimer');
        $serviceContainer->method('getLogger')->willReturn(new NullLogger());

        $application = $this->createApplicationFactory($serviceContainer)->createDefaultServerApplication($lapTimer);

        self::assertTrue($application->run());
    }

    public function testCreateDefaultServerApplicationUsesTheLapTimerOfTheServiceContainerWithoutALapTimer(): void
    {
        // `run()` makes the laps `run: start` and `run: end`.
        $lapTimer = $this->createMock(LapTimerInterface::class);
        $lapTimer->expects(self::exactly(2))->method('lap')->willReturn(true);

        $serviceContainer = $this->createMock(ServiceContainerInterface::class);
        $serviceContainer->expects(self::once())->method('getLapTimer')->willReturn($lapTimer);
        $serviceContainer->method('getLogger')->willReturn(new NullLogger());

        $application = $this->createApplicationFactory($serviceContainer)->createDefaultServerApplication();

        self::assertTrue($application->run());
    }

    private function createApplicationFactory(
        ServiceContainerInterface $serviceContainer,
    ): DefaultServerApplicationFactory {
        $applicationRunner = self::createStub(ApplicationRunnerInterface::class);
        $applicationRunner->method('run')->willReturn(true);
        $applicationRunnerFactory = self::createStub(ApplicationRunnerFactoryInterface::class);
        $applicationRunnerFactory->method('createApplicationRunner')->willReturn($applicationRunner);

        $errorHandlingServiceFactory = self::createStub(ErrorHandlingServiceFactoryInterface::class);
        $errorHandlingServiceFactory->method('createErrorHandlingService')->willReturn(
            self::createStub(ErrorHandlingServiceInterface::class),
        );

        return new DefaultServerApplicationFactory(
            $applicationRunnerFactory,
            $errorHandlingServiceFactory,
            $serviceContainer,
            self::createStub(ServerRequestFromServerDataFactoryInterface::class),
        );
    }
}
