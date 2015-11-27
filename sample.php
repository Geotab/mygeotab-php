<?php
require __DIR__ . '/vendor/autoload.php';

$api = new Geotab\API("somename", "password", "databasename", null, "my.geotab.com");
$api->authenticate();
var_dump($api->getCredentials());

$api->call("Get", ["typeName" => "Device", "resultsLimit" => 5], function ($results) {
    var_dump($results);
}, function ($error) {
    var_dump($error);
});