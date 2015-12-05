<?php
require __DIR__ . '/vendor/autoload.php';

$sampleUserId = "b41B4DC05";
$sampleHOSId = "aDVrUIaQ1ZUWmoTIDd--AJb";
$defaultErrorCallback = function ($error) {
    var_dump($error);
};

$api = new Geotab\API("username", "password", "database", "my.geotab.com");
$api->authenticate();

echo "Get User\n";
$api->call("Get", "User", ["search" => ["id" => $sampleUserId], "resultsLimit" => 1], function ($results) use (&$api, &$defaultErrorCallback) {
    $user = $results[0];
    $user["firstName"] .= "1234";

    echo "Set User\n";
    $api->call("Set", "User", ["entity" => $user], function ($results) {
        var_dump($results);
    }, $defaultErrorCallback);

}, $defaultErrorCallback);

echo "Add DutyStatusLog\n";
$api->call("Add", "DutyStatusLog", ["entity" => [
    "id" => $sampleHOSId,
    "dateTime" => "2015-12-05T05:24:35.095Z",
    "device" => ["id" => "b3"],
    "driver" => ["id" => $sampleUserId],
    "status" => "ON"
]], function ($results) {
    var_dump($results);
}, $defaultErrorCallback);

echo "Remove DutyStatusLog\n";
$api->call("Remove", "DutyStatusLog", ["entity" => ["id" => $sampleHOSId]], function ($results) {
    var_dump($results);
}, $defaultErrorCallback);

echo "MultiCall\n";
$api->multiCall([
    ["Get", ["typeName" => "Device", "resultsLimit" => 1]],
    ["Get", ["typeName" => "Device", "resultsLimit" => 1]]
], function ($results) {
    var_dump($results);
}, $defaultErrorCallback);