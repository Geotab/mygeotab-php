<?php
include ('Geotab\API.php');

$api = new Geotab\API();
$api->setServer('my3.geotab.com')->setDatabase('G560')
    ->setUsername('xyz')->setPassword('zzz');

$api->Authenticate();