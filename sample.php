<?php
require __DIR__ . '/vendor/autoload.php';

$api = new Geotab\API("somename", "password", "databasename", null, "my.geotab.com");
$api->authenticate();

var_dump($api->getCredentials());