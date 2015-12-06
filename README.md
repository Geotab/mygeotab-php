MyGeotab PHP API Client
======================

.. image:: https://travis-ci.org/colonelchlorine/mygeotab-php.svg?branch=master
    :target: https://travis-ci.org/colonelchlorine/mygeotab-php
    :alt: Build Status

Summary
------------
Provides a PHP client that can easily make API requests to a MyGeotab server. Sample:

```php
$api = new Geotab\API("user@example.com", "password", "DatabaseName", "my.geotab.com");
$api->authenticate();

$api->call("Get", "Device", ["resultsLimit" => 1], function ($results) {
    var_dump($results);
}, function ($error) {
    var_dump($error);
});
```