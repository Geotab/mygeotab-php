# MyGeotab PHP API Client

[![CI](https://img.shields.io/github/actions/workflow/status/Geotab/mygeotab-php/ci.yml?branch=main&label=CI)](https://github.com/Geotab/mygeotab-php/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/geotab/mygeotab-php.svg)](https://packagist.org/packages/geotab/mygeotab-php)
[![Monthly Downloads](https://img.shields.io/packagist/dm/geotab/mygeotab-php.svg)](https://packagist.org/packages/geotab/mygeotab-php)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

PHP client for the [MyGeotab](https://www.geotab.com) API.

## Requirements

- PHP **>=8.2**
- [Composer](https://getcomposer.org/)

## Installation

```bash
composer require geotab/mygeotab-php
```

## Quick Start

```php
// Store credentials in environment variables, not in source code
$api = new Geotab\API(
    getenv('MYGEOTAB_USERNAME'),
    getenv('MYGEOTAB_PASSWORD'),
    getenv('MYGEOTAB_DATABASE')
);
$api->authenticate();

$results = $api->get("Device", ["resultsLimit" => 1]);
```

**Constructor:** `new Geotab\API($username, $password, $database, $server = "my.geotab.com")`

The `$server` parameter defaults to `my.geotab.com`. After `authenticate()`, it is updated automatically if the account lives on a different server node.

### Error handling

Methods return results directly and throw `Geotab\MyGeotabException` on error:

```php
use Geotab\MyGeotabException;

try {
    $toDate   = new DateTime();
    $fromDate = new DateTime();
    $fromDate->modify("-1 month");

    $violations = $api->get("DutyStatusViolation", [
        "search" => [
            "userSearch" => ["id" => "b1"],
            "toDate"     => $toDate->format("c"),
            "fromDate"   => $fromDate->format("c"),
        ],
        "resultsLimit" => 10,
    ]);

    echo "The driver has " . count($violations) . " violations!";
} catch (MyGeotabException $e) {
    // Handle API error
}
```

All methods also accept optional success and error callbacks if you prefer that style:

```php
$api->get(
    "Device",
    ["resultsLimit" => 1],
    function ($results) { var_dump($results); },
    function ($error)   { var_dump($error); }
);
```

## Security

Use a PHP release patched for CVE-2026-109618 and CVE-2026-109619:
**8.2.35+**, **8.3.36+**, **8.4.27+**, or **8.5.12+** within those minor versions.
PHP 8.1 is end-of-life, unsupported, and has no upstream fix. See the
[first advisory](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
and [second advisory](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).

The example commands below disable upload progress and file uploads and limit POST
bodies. These settings do not replace upgrading PHP. On PHP 8.5, if you cannot
upgrade yet, also set `fatal_error_backtraces=0`. PHP's built-in server (`php -S`) is for
local development only; never expose it beyond localhost.

## Logging

The library does not log internally. To log HTTP requests and responses, pass a pre-configured Guzzle client with a [log middleware](https://docs.guzzlephp.org/en/stable/handlers-and-middleware.html) attached. This works with any [PSR-3](https://www.php-fig.org/psr/psr-3/) compatible logger such as [Monolog](https://github.com/Seldaek/monolog):

```bash
composer require monolog/monolog
```

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('mygeotab');
$logger->pushHandler(new StreamHandler('mygeotab.log'));

$stack = HandlerStack::create();
$stack->push(Middleware::log($logger, new MessageFormatter('{method} {uri} → {code}')));

$api = new Geotab\API(
    getenv('MYGEOTAB_USERNAME'),
    getenv('MYGEOTAB_PASSWORD'),
    getenv('MYGEOTAB_DATABASE'),
    'my.geotab.com',
    new Client(['handler' => $stack])
);
$api->authenticate();
```

## API Reference

| Method | Description |
|--------|-------------|
| `authenticate()` | Exchanges credentials for a session token. Updates `$server` automatically if the account is on a different node. |
| `get($type, $params)` | Retrieves or searches for entities. |
| `add($type, $entity)` | Creates a new entity. |
| `set($type, $entity)` | Updates an existing entity. |
| `remove($type, $entity)` | Deletes an entity. |
| `call($method, $params)` | Calls any MyGeotab API method by name. |
| `multiCall($calls)` | Executes multiple API calls in a single HTTP request. |
| `getCredentials()` | Returns the current `Geotab\Credentials` object. |
| `setCredentials($credentials)` | Replaces the current credentials. |

See the [Geotab SDK documentation](https://developers.geotab.com) for available entity types, methods, and search parameters.

## Examples

The `examples/` directory contains two runnable samples.

**CLI sample** — exercises Get, Set, Add, and MultiCall against a live database:

```bash
MYGEOTAB_USERNAME=user@example.com \
MYGEOTAB_PASSWORD=password \
MYGEOTAB_DATABASE=DatabaseName \
php examples/cli-sample.php
```

**Top Speeding Violations** — a web UI example. Serve with PHP's built-in server:

```bash
php -d session.upload_progress.enabled=0 -d file_uploads=0 -d post_max_size=16K -S localhost:7000 -t examples/top-speeding-violations/web
```

Then open `http://localhost:7000` in your browser.

**Live GPS Feed** — polls GetFeed for vehicle GPS positions every five seconds:

```bash
php -d session.upload_progress.enabled=0 -d file_uploads=0 -d post_max_size=16K -S localhost:7001 -t examples/gps-feed/web
```

Then open `http://localhost:7001` in your browser.

## Contributing

Clone the repo and install dependencies:

```bash
git clone https://github.com/Geotab/mygeotab-php.git
cd mygeotab-php
composer install
```

Run the test suite:

```bash
vendor/bin/phpunit
```

Integration tests require credentials supplied as environment variables; they are skipped automatically if not set:

```bash
MYGEOTAB_USERNAME=user@example.com \
MYGEOTAB_PASSWORD=password \
MYGEOTAB_DATABASE=DatabaseName \
vendor/bin/phpunit
```

Pull requests are welcome. For major changes, open an issue first to discuss what you'd like to change.

## License

MIT © Geotab. See [LICENSE](LICENSE) for details.
