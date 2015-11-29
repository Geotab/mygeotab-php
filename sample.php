<?php
require __DIR__ . '/vendor/autoload.php';

$api = new Geotab\API("somename", "password", "databasename", null, "my.geotab.com");
$api->authenticate();

$api->call("Get", ["typeName" => "Device", "resultsLimit" => 1], function ($results) {
    var_dump($results);
}, function ($error) {
    var_dump($error);
});

$api->multiCall([
    ["Get", ["typeName" => "Device", "resultsLimit" => 1]],
    ["Get", ["typeName" => "Device", "resultsLimit" => 1]]
], function ($results) {
    var_dump($results);
}, function ($error) {
    var_dump($error);
});