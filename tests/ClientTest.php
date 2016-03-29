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
    
    /*
    Make an authenticate call and make sure it throws a MyGeotabException
    */
    public function testAuthenticationFailure() {
        if (!MYGEOTAB_USERNAME) {
            $this->markTestSkipped("Environment MYGEOTAB_USERNAME not defined, so no API call can be made");
        }

        try {
            $api = new Geotab\API(MYGEOTAB_USERNAME . "INCORRECTUSERNAME", MYGEOTAB_PASSWORD . "INCORRECTPWD", MYGEOTAB_DATABASE);
            $api->authenticate();    
        }
        catch (Geotab\MyGeotabException $e) {
            $this->assertEquals("Geotab\MyGeotabException", get_class($e));
        }
    }
    
    public function testDateTimeFormat() {
        if (!MYGEOTAB_USERNAME) {
            $this->markTestSkipped("Environment MYGEOTAB_USERNAME not defined, so no API call can be made");
        }
        
        $api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE);
        $api->authenticate();

        $today = new \DateTime();

        // Get a single device that is active today
        $api->get("Device", [
            "search" => [
                "activeFrom" => $today->format("c")
            ],
            "resultsLimit" => 1
        ], function ($result) {
            $this->assertEquals(1, count($result));
        }, function ($error) {
            $this->fail($error);
        });
    }
}
