<?php
namespace GeotabPHP;

class ExceptionCalculator
{
    public static function GetExceptionCountByDevice($exceptions)
    {
        $deviceCount = [];
        foreach ($exceptions as $exception) {
            if (!array_key_exists($exception["device"]["id"], $deviceCount)) {
                $deviceCount[$exception["device"]["id"]] = 0;
            }
            $deviceCount[$exception["device"]["id"]]++;
        }
        arsort($deviceCount);
        return $deviceCount;
    }
    
    public static function GetDeviceNames($api, $deviceIds)
    {
        $calls = [];
        $devices = [];
        foreach ($deviceIds as $id) {
            $calls[] = ["Get", ["typeName" => "Device", "search" => [ "id" => $id ] ] ];
        }
        $results = $api->multiCall($calls);
        foreach ($results as $device) {
            $device = $device[0];
            if (!array_key_exists("id", $device)) {
                continue;
            }
            
            $devices[$device["id"]] = $device["name"];
        }
        return $devices;
    }
}
