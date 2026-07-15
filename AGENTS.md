# mygeotab-php — Agent Context

PHP client for the MyGeotab API. Package: `geotab/mygeotab-php` | PHP ≥ 8.1 | MIT

## Setup

```bash
composer install
```

## Test commands

```bash
# Unit tests only
vendor/bin/phpunit

# Unit + integration (credentials required)
MYGEOTAB_USERNAME=user@example.com \
MYGEOTAB_PASSWORD=password \
MYGEOTAB_DATABASE=DatabaseName \
vendor/bin/phpunit
```

Integration tests are skipped automatically when env vars are absent.

## File structure

```
src/Geotab/
  API.php               — main client class
  Credentials.php       — holds username, sessionId, database, server
  MyGeotabException.php — thrown on API errors
tests/
examples/
  cli-sample.php
  top-speeding-violations/
    src/GeotabPHP/ExceptionCalculator.php
    web/index.php          — form POST → ranked table (Zenith CSS via CDN)
    web/main.css
  gps-feed/
    web/index.php          — session auth + live GetFeed table
    web/feed.php           — AJAX endpoint (GetFeed, returns JSON)
    web/style.css
```

## Running examples

```bash
# Top speeding violations
php -S localhost:7000 -t examples/top-speeding-violations/web

# Live GPS feed (polls GetFeed every 5 s)
php -S localhost:7001 -t examples/gps-feed/web
```

## Core API usage

```php
$api = new Geotab\API($username, $password, $database);   // server defaults to my.geotab.com
$api->authenticate();   // exchanges credentials for a session token; updates $server if needed

$results = $api->get('Device', ['resultsLimit' => 10]);
$id      = $api->add('Zone', $entity);
$api->set('Zone', $entity);
$api->remove('Zone', ['id' => $id]);

// Any method by name
$feed = $api->call('GetFeed', ['typeName' => 'LogRecord', 'fromVersion' => $v]);

// Batch
$api->multiCall([
    ['Get', ['typeName' => 'Device', 'resultsLimit' => 1]],
    ['Get', ['typeName' => 'User',   'resultsLimit' => 1]],
]);
```

Errors throw `Geotab\MyGeotabException`. All methods also accept optional `$successCallback` / `$errorCallback` as the last two arguments.

## Restoring a session (no re-auth)

```php
$api = new Geotab\API($username, null, $database, $server);
$api->getCredentials()->setSessionId($sessionId);
// Ready to call API methods without authenticate()
```

## CSS conventions for examples

All example pages load Zenith CSS from CDN then a local `*.css` file. The local file **must** include:

```css
body { font-family: var(--main-font); }
h1, h2, h3, h4, h5, h6 { font-family: var(--main-font); }
```

Zenith sets `--main-font` but does not reset browser defaults on `body` or headings — the example stylesheet must claim it explicitly or serif fallback fonts appear.

## Adding a new example

1. Create `examples/<name>/web/` and place PHP files there.
2. Require the autoloader: `require __DIR__ . '/../../../vendor/autoload.php';`
3. `Geotab\API`, `Geotab\Credentials`, and `Geotab\MyGeotabException` are available immediately.
4. If you add helper classes, register their namespace in `composer.json` under `autoload-dev` and run `composer dump-autoload`.
5. Update README.md with a run command.

## Autoload namespaces

| Namespace | Path | Loaded |
|---|---|---|
| `Geotab\` | `src/Geotab/` | always |
| `Geotab\API\Tests\` | `tests/` | dev only |
| `GeotabPHP\` | `examples/top-speeding-violations/src/GeotabPHP/` | dev only |
