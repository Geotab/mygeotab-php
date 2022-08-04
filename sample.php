<?php
require __DIR__ . '/vendor/autoload.php';

$sampleUserId = "b41B4DC05";
$sampleHOSId = "aDVrUIaQ1ZUWmoTIDd--AJb";
$defaultErrorCallback = function ($error) {
    var_dump($error);
};

$api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE, "my.geotab.com");
$api->authenticate();

echo "Get User\n";
$api->get("User", ["search" => ["id" => $sampleUserId], "resultsLimit" => 1], function ($results) use (&$api, &$defaultErrorCallback) {
    $user = $results[0];
    $user["firstName"] .= "1234";

    echo "Set User\n";
    $api->set("User", $user, function ($results) {
        var_dump($results);
    }, $defaultErrorCallback);

}, $defaultErrorCallback);

echo "Add DutyStatusLog\n";
$api->add("DutyStatusLog", ["entity" => [
    "id" => $sampleHOSId,
    "dateTime" => "2015-12-05T05:24:35.095Z",
    "device" => ["id" => "b3"],
    "driver" => ["id" => $sampleUserId],
    "status" => "ON"
]], function ($results) {
    var_dump($results);
}, $defaultErrorCallback);

echo "MultiCall\n";
$api->multiCall([
    ["Get", ["typeName" => "Device", "resultsLimit" => 1]],
    ["Get", ["typeName" => "Device", "resultsLimit" => 1]]
], function ($results) {
    var_dump($results);
}, $defaultErrorCallback);
