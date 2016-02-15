<?php
namespace Geotab\Tests;

use Geotab;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testCall() {
        $api = new Geotab\API("xyz", "pwd");

//        $api->authenticate();
//
//        $api->call("Get", ["typeName" => "Device", "search" => ["id" => "b3"]], function ($result) {
//            var_dump($result);
//        });

        var_dump($_ENV);
        var_dump($_SERVER);

        $this->assertEquals("testdb", getenv("MYGEOTAB_DATABASE"));
        $this->assertEquals(true, true);
    }
}
