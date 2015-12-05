<?php
require __DIR__ . '/vendor/autoload.php';

$api = new Geotab\API("somename", "password", "databasename", null, "my.geotab.com");
$api->authenticate();

echo "Get User";
$api->call("Get", "User", ["search" => ["id" => "b41B4DC13"], "resultsLimit" => 1], function ($results) use ($api) {
    $user = $results[0];
    $user["firstName"] .= "1234";

    echo "Set User";
    $api->call("Set", "User", ["entity" => $user], function ($results) {
        var_dump($results);
    }, function ($error) {
        var_dump($error);
    });

    echo "Add DutyStatusLog";
    $api->call("Add", "DutyStatusLog", ["entity" => [
        "id" => "aDVrUIaQ1ZUWmoTIDd--AJb"],
        "dateTime" => "2015-12-05T05:24:35.095Z",
        "device" => ["id" => "b3"],
        "driver" => ["id" => $user["id"]],
        "status" => "ON"
    ], function ($results) {
        var_dump($results);
    }, function ($error) {
        var_dump($error);
    });
}, function ($error) {
    var_dump($error);
});