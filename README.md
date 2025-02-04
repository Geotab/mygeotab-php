MyGeotab PHP API Client
======================

[![Build Status](https://travis-ci.org/Geotab/mygeotab-php.svg?branch=master)](https://travis-ci.org/Geotab/mygeotab-php)
[![Packagist](https://img.shields.io/packagist/dm/geotab/mygeotab-php.svg)](https://packagist.org/packages/geotab/mygeotab-php)

Provides a PHP client that can easily make API requests to a MyGeotab server.


Installation
------------
You can use [composer](https://getcomposer.org/) and run the following command in your repo:

```
composer require mygeotab-php
```

This repository requires PHP >=7.1, but if you're going to try integrate this into older versions
then you can look at the code in `src` directly.

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

Instead of using the callback syntax, you can simply use the return result directly. Keep in mind, if an error occurs you won't be informed! It will throw as a `MyGeotabException`, so remember to use try & catch.

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

Examples
------------
In the `examples` folder, you can see the "Top Speeding Violations" example that was presented in the [Dev Channel video](https://www.geotab.com/video/mygeotab-php-api-client/). The code is not yet hooked up with the Node server and will likely
have difficulty with PSR, but the code is there and should be easy to understand.

You can use the built-in web server to test out the example at `http://localhost:7000:

```
php -S localhost:7000 -t examples/top-speeding-violations/web
```