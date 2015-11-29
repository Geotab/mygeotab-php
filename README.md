MyGeotab PHP API Client
======================

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