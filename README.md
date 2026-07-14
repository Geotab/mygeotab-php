MyGeotab PHP API Client
======================

[![CI](https://github.com/Geotab/mygeotab-php/actions/workflows/ci.yml/badge.svg)](https://github.com/Geotab/mygeotab-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/dm/geotab/mygeotab-php.svg)](https://packagist.org/packages/geotab/mygeotab-php)

Provides a PHP client that can easily make API requests to a MyGeotab server.

This package is maintained by Geotab. For issues or questions, please open a GitHub issue.

Requirements
------------

- PHP **>=8.1**
- [Composer](https://getcomposer.org/)

Installation
------------
Install via [Composer](https://getcomposer.org/):

```
composer require geotab/mygeotab-php
```

Quick start
------------

```php
$api = new Geotab\API("user@example.com", "password", "DatabaseName", "my.geotab.com");
$api->authenticate();

$api->get("Device", ["resultsLimit" => 1], function ($results) {
    var_dump($results);
}, function ($error) {
    var_dump($error);
});
```

Instead of using the callback syntax, you can simply use the return result directly. Keep in mind, if an error occurs it will throw as a `MyGeotabException`, so remember to use try & catch.

```php
$toDate = new DateTime();
$fromDate = new DateTime();
$fromDate->modify("-1 month");

try {
    $violations = $api->get("DutyStatusViolation", [
        "search" => [
            "userSearch" => ["id" => "b1"],
            "toDate" => $toDate->format("c"),   // ISO8601, or could use "2018-11-03 00:53:29.370134"
            "fromDate" => $fromDate->format("c")
        ],
        "resultsLimit" => 10
    ]);
} catch (Exception $e) {
    // Handle this or return
}

echo "The driver has " . count($violations) . " violations!";
```

Contributing
------------
Clone the repo and install dependencies using the lockfile for a reproducible environment:

```
composer install
```

Run the test suite. Integration tests require MyGeotab credentials supplied as environment variables; without them the tests are skipped automatically:

```
MYGEOTAB_USERNAME=user@example.com \
MYGEOTAB_PASSWORD=password \
MYGEOTAB_DATABASE=DatabaseName \
vendor/bin/phpunit --configuration phpunit.xml.dist
```

Feel free to open a Pull Request with any suggested changes.

Examples
------------
In the `examples` folder, you can see the "Top Speeding Violations" example. You can use the PHP built-in web server to test out the example at `http://localhost:7000` by running:

```
php -S localhost:7000 -t examples/top-speeding-violations/web
```
