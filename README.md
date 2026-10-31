# webservco/application-default

A PHP component/library. Please see [webservco/sandbox](https://webservco.github.io/sandbox/) for an example project using these types of components.

Default application implementation, with factories for web and command line applications.

- Requires: `ALLOWED_HOSTS` in the configuration (server application only)
- Related: `webservco/application` (interfaces), `webservco/application-runner` (HTTP runner), `webservco/dependency-container` (provides the logger and configuration)

---

## Installation

```shell
composer require webservco/application-default
```

---

## Usage

### Server application

Example of what `public/index.php` does in the `webservco/sandbox` project, after it has created the dependency container and loaded the configuration.

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
$application = $applicationFactory->createDefaultServerApplication($lapTimer);
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
$application = $applicationFactory->createCommandApplication($lapTimer, $command);
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
