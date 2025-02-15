<?php

declare(strict_types=1);

namespace WebServCo\Application\Factory;

use UnexpectedValueException;
use WebServCo\Application\Contract\ApplicationInterface;
use WebServCo\Application\Contract\ApplicationRunnerFactoryInterface;
use WebServCo\Application\Service\DefaultApplication;
use WebServCo\Configuration\Contract\ConfigurationGetterInterface;
use WebServCo\DependencyContainer\Contract\ServiceContainerInterface;
use WebServCo\Error\Contract\ErrorHandlingServiceFactoryInterface;
use WebServCo\Http\Contract\Message\Request\Server\ServerRequestFromServerDataFactoryInterface;
use WebServCo\Stopwatch\Contract\LapTimerInterface;

use function is_string;

final class DefaultServerApplicationFactory
{
    public function __construct(
        private ApplicationRunnerFactoryInterface $applicationRunnerFactory,
        private ErrorHandlingServiceFactoryInterface $errorHandlingServiceFactory,
        private ServiceContainerInterface $serviceContainer,
        private ServerRequestFromServerDataFactoryInterface $serverRequestFactory,
    ) {
    }

    /**
     * Create default server application using the common PHP data.
     *
     * A default wrapper for `createServerApplication`.
     *
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function createDefaultServerApplication(LapTimerInterface $lapTimer): ApplicationInterface
    {
        // @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        return $this->createServerApplication($lapTimer, $_COOKIE, $_POST, $_GET, $_SERVER, $_FILES);
        // @phpcs:enable
    }

    /**
     * Following abomination needed in order to be contravariant with PSR method definitions.
     * @phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
     * @phpcs:disable SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
     * @param mixed[] $cookieParams
     * @param mixed[]|object|null $parsedBody
     * @param mixed[] $queryParams
     * @param mixed[] $serverParams
     * @param array<int|string,mixed> $uploadedFiles
     * @phpcs:enable
     */
    public function createServerApplication(
        LapTimerInterface $lapTimer,
        array $cookieParams = [],
        mixed $parsedBody = null,
        array $queryParams = [],
        array $serverParams = [],
        array $uploadedFiles = [],
    ): ApplicationInterface {
        $serverRequest = $this->serverRequestFactory->createServerRequestFromServerData(
            $this->getAllowedHosts($this->serviceContainer->getConfigurationGetter()),
            $cookieParams,
            $parsedBody,
            $queryParams,
            $serverParams,
            $uploadedFiles,
        );
        $applicationRunner = $this->applicationRunnerFactory->createApplicationRunner($serverRequest);

        return new DefaultApplication(
            $applicationRunner,
            $this->errorHandlingServiceFactory->createErrorHandlingService(),
            $this->serviceContainer->getLogger('application'),
            $lapTimer,
        );
    }

    /**
     * @return array<int,string>
     */
    private function getAllowedHosts(ConfigurationGetterInterface $configurationGetter): array
    {
        $data = $configurationGetter->getArray('ALLOWED_HOSTS');

        $result = [];
        foreach ($data as $value) {
            if (!is_string($value)) {
                throw new UnexpectedValueException('Value is not a string.');
            }
            $result[] = $value;
        }

        return $result;
    }
}
