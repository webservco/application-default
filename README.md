# webservco/application-default

A PHP component/library. Please see [webservco/sandbox](https://webservco.github.io/sandbox/) for an example project using these types of components.

Default application implementation, with factories for web and command line applications.

- Requires: `ALLOWED_HOSTS` in the configuration (server application only)
- Related: `webservco/application` (interfaces), `webservco/application-runner` (HTTP runner), `webservco/dependency-container` (provides the logger and configuration), `webservco/exception` (the uncaught exception handler that `DefaultApplicationInitializer` sets)

---

## Installation

```shell
composer require webservco/application-default
```

---

## Usage

### Runner script of a command

The whole script, for example `bin/Example.php`. `DefaultApplicationInitializer` does the start that is the same for every script: the project path (made absolute, with the trailing separator), the lap timer (started at `$startTime`, with the lap `autoload`), the application dependency container, the logger of the channel `application`, the configuration file `config/.env.ini`, the uncaught exception handler, and the lap `initialization`.

```php
<?php

declare(strict_types=1);

namespace Project;

use Project\Factory\Command\ExampleCommandFactory;
use Throwable;
use WebServCo\Application\Factory\DefaultCommandApplicationFactory;
use WebServCo\Application\Service\DefaultApplicationInitializer;
use WebServCo\Error\Factory\DefaultErrorHandlingServiceFactory;

use function hrtime;

// Initialize the start time as early as possible.
$startTime = (int) hrtime(true);

require_once __DIR__ . '/../vendor/autoload.php';

try {
    // \WebServCo\DependencyContainer\Contract\ApplicationDependencyContainerInterface
    $applicationDependencyContainer = (new DefaultApplicationInitializer())->initialize(__DIR__ . '/..', $startTime);
} catch (Throwable) {
    // The initializer has logged the problem.
    echo 'Initialization exception.';
    exit(1);
}

$commandFactory = new ExampleCommandFactory($applicationDependencyContainer);

$applicationFactory = new DefaultCommandApplicationFactory(
    new DefaultErrorHandlingServiceFactory(),
    $applicationDependencyContainer->getServiceContainer(),
);
$application = $applicationFactory->createDefaultCommandApplication($commandFactory->createCommand());
$application->bootstrap();
// No need to call `$application->shutdown();` it is registered as shutdown function.
exit($application->run() ? 0 : 1);
```

The initializer prints nothing and does not end the script. When something fails (for example the project path, `config/.env.ini` or `var/log/` is missing), it logs the problem as `emergency` and throws it again. The script prints `Initialization exception.` and ends with the exit code 1. Until the logger of the channel `application` exists, the problem is logged to a `NullLogger`, so a missing `var/log/` is not logged anywhere.

The configuration value `PROJECT_PATH` is the absolute project path, with the trailing separator. A script that needs the path reads it from there.

To change the defaults, pass them to the constructor: the logger channel, the configuration directory (relative to the project path) and the configuration file.

```php
$initializer = new DefaultApplicationInitializer('application', 'config', '.env.ini');
```

### Server application

Example of what `public/index.php` does in the `webservco/sandbox` project, after `DefaultApplicationInitializer` (see above).

```php
use WebServCo\Application\Factory\ApplicationRunnerFactory;
use WebServCo\Application\Factory\DefaultServerApplicationFactory;
use WebServCo\Error\Factory\DefaultErrorHandlingServiceFactory;
use WebServCo\Http\Factory\Message\Request\Server\ServerRequestFromServerDataFactory;

$applicationFactory = new DefaultServerApplicationFactory(
    // \WebServCo\Application\Contract\ApplicationRunnerFactoryInterface
    new ApplicationRunnerFactory($requestHandlerFactory),
    // \WebServCo\Error\Contract\ErrorHandlingServiceFactoryInterface
    new DefaultErrorHandlingServiceFactory(),
    // \WebServCo\DependencyContainer\Contract\ServiceContainerInterface
    $applicationDependencyContainer->getServiceContainer(),
    // \WebServCo\Http\Contract\Message\Request\Server\ServerRequestFromServerDataFactoryInterface
    new ServerRequestFromServerDataFactory(),
);

// \WebServCo\Application\Contract\ApplicationInterface
// Without a lap timer, it uses the lap timer of the service container.
$application = $applicationFactory->createDefaultServerApplication();
$application->bootstrap();
$application->run();
```

### Command application

```php
use WebServCo\Application\Factory\DefaultCommandApplicationFactory;
use WebServCo\Error\Factory\DefaultErrorHandlingServiceFactory;

$applicationFactory = new DefaultCommandApplicationFactory(
    new DefaultErrorHandlingServiceFactory(),
    $applicationDependencyContainer->getServiceContainer(),
);

// $command: \WebServCo\Command\Contract\CommandRunnerInterface
// Uses the lap timer of the service container. For another lap timer: `createCommandApplication($lapTimer, $command)`.
$application = $applicationFactory->createDefaultCommandApplication($command);
$application->bootstrap();
// `run()` returns the command's result. The script sets the exit code, because the application also serves web requests.
exit($application->run() ? 0 : 1);
```

---

## Contracts

| Interface | Implementation | Purpose |
| --- | --- | --- |
| `Contract\ApplicationInterface` (`webservco/application`) | `Service\DefaultApplication` | Sets up error handling, runs the runner and logs timing at shutdown. |
| | `Factory\DefaultServerApplicationFactory` | Builds the server request from PHP globals and creates the application. |
| | `Factory\DefaultCommandApplicationFactory` | Creates the application around a command. |
| | `Service\DefaultApplicationInitializer` | Does the start of a runner script: project path, lap timer, application dependency container, logger, configuration, uncaught exception handler. |

Namespaces are relative to `WebServCo\Application\`.

---

## Configuration

The server factory only accepts requests whose `SERVER_NAME` is in this list.

```ini
[Application]

ALLOWED_HOSTS[] = example.ddev.site
ALLOWED_HOSTS[] = localhost
```

---

## Notes

`bootstrap()` installs the error handler (with the default factory, any PHP error becomes an `ErrorException`), registers `shutdown()` as a shutdown function, and then throws for errors that happened before the script started, such as a POST body over `post_max_size`. Install the exception handler before calling it.

`run()` returns what the runner returned: the command's result, or for a server application whether the response was emitted.

`shutdown()` restores the previous error handler and writes the lap timer statistics as JSON to the `application` log channel at debug level.

`createDefaultServerApplication()` reads `$_COOKIE`, `$_POST`, `$_GET`, `$_SERVER` and `$_FILES`. Tests can pass their own data to `createServerApplication()` instead.

---

## Development

```shell
# Start project.
ddev start
# Install dependencies.
ddev c u
# Run all validations.
ddev c c
# Run unit tests.
ddev c test:dox
```

---

## License

[MIT](LICENSE.md)
