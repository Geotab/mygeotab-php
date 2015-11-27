<?php
namespace Geotab\Tests;

use Geotab;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testCall() {
        $api = new Geotab\API("xyz", "pwd");
        $api->authenticate();

        $api->call("Get", ["typeName" => "Device", "search" => ["id" => "b3"]], function ($result) {
            var_dump($result);  
        });

        $this->assertEquals(true, true);
    }
}
