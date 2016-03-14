<?php
namespace Geotab\Tests;

use Geotab;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testCall() {
        if (!MYGEOTAB_USERNAME) {
            $this->markTestSkipped("Environment MYGEOTAB_USERNAME not defined, so no API call can be made");
        }

        $api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE);
        $api->authenticate();

        // First try closure syntax
        $api->call("GetVersion", [], function ($result) {
            $version = explode(".", $result);

            // There should be 4 parts of the version
            $this->assertEquals(4, count($version));
        }, function ($error) {
            $this->fail($error);
        });
        
        // Then try the "synchronous" return method
        $result = $api->call("GetVersion", []);
        $version = explode(".", $result);

        // There should be 4 parts of the version
        $this->assertEquals(4, count($version));
    }
}
