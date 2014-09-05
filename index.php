<?php
include ('Geotab\API.php');

$api = new Geotab\API();
$api->setServer('my.geotab.com')->setDatabase('mydb')
    ->setUsername('xyz')->setPassword('zzz');

$api->Authenticate();

$api->call("Get", ["typeName" => "Device", "search" => ["id" => "b3"]], function ($result) {
	var_dump($result);	
});