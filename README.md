# MyGeotab PHP API Client

[![CI](https://img.shields.io/github/actions/workflow/status/Geotab/mygeotab-php/ci.yml?branch=main&label=CI)](https://github.com/Geotab/mygeotab-php/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/geotab/mygeotab-php.svg)](https://packagist.org/packages/geotab/mygeotab-php)
[![Monthly Downloads](https://img.shields.io/packagist/dm/geotab/mygeotab-php.svg)](https://packagist.org/packages/geotab/mygeotab-php)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

PHP client for the [MyGeotab](https://www.geotab.com) API.

## Requirements

- PHP **>=8.1**
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
php -S localhost:7000 -t examples/top-speeding-violations/web
```

Then open `http://localhost:7000` in your browser.

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
