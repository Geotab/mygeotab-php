<?php
namespace Geotab\Tests;

use Geotab;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        if (!MYGEOTAB_USERNAME) {
            $this->assertFalse(true, "Environment MYGEOTAB_USERNAME not defined, so no API call can be made");
        }
    }

    public function testCall()
    {
        $api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE);
        $api->authenticate();

        // First try closure syntax
        $api->call("GetVersion", [], function ($result) {
            $version = explode(".", $result);

            // There should be 4 parts of the version
            $this->assertEquals(3, count($version));
        }, function ($error) {
            $this->fail($error["error"]["message"]);
        });

        // Then try the "synchronous" return method
        $result = $api->call("GetVersion", []);
        $version = explode(".", $result);

        // There should be 4 parts of the version
        $this->assertEquals(3, count($version));
    }
    
    /*
    Make an authenticate call and make sure it throws a MyGeotabException
    */
    public function testAuthenticationFailure()
    {
        try {
            $api = new Geotab\API(MYGEOTAB_USERNAME . "INCORRECTUSERNAME", MYGEOTAB_PASSWORD . "INCORRECTPWD");
            $api->authenticate();
        }
        catch (Geotab\MyGeotabException $e) {
            $this->assertEquals("Geotab\MyGeotabException", get_class($e));
            $this->assertEquals("InvalidUserException", $e->error["data"]["type"]);
        }
    }
    
    public function testDateTimeFormat()
    {
        $today = new \DateTime();

        $api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE);
        $api->authenticate();

        // Get a single device that is active today
        $api->get("Device", [
            "search" => [
                "activeFrom" => $today->format("c")
            ],
            "resultsLimit" => 1
        ], function ($result) {
            $this->assertEquals(1, count($result));
        }, function ($error) {
            $this->fail($error["error"]["message"]);
        });
    }

    public function testSuccessfulCallWithoutAResultOrError()
    {
        $api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE);
        $api->authenticate();

        // Get a single device & try to set it equal to it's downloaded result. Expect a successful result
        $api->get("Device", [
            "resultsLimit" => 1
        ], function ($result) use ($api) {
            $this->assertEquals(1, count($result));
            $api->set("Device", $result[0], function($result) {
                $this->assertEquals(null, $result);
            }, function ($error) {
                $this->fail("Shouldn't be throwing an error: " . serialize($error));
            });
        }, function ($errorResult) {
            $this->fail($errorResult["error"]["message"]);
        });

        // Try it without the callbacks
        $devices = $api->get("Device", [
            "resultsLimit" => 1
        ]);
        $this->assertEquals(1, count($devices));
        $result = $api->set("Device", $devices[0]);
        $this->assertEquals(null, $result);
    }

    public function testErrorCall()
    {
        $api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE);
        $api->authenticate();

        // Get a single device that is active today
        $api->set("Device", ["entity" => [
            "id" => "b10000000000",
            "name" => "Whoops"
        ]], function ($result) use ($api) {
            $this->fail("Result shouldn't be generated");
        }, function ($errorResult) {
            $this->assertArrayHasKey("error", $errorResult);
            $this->assertStringContainsString("Exception", $errorResult["error"]["message"]);
        });

        // Now try it without the callbacks
        try {
            $api->set("Device", ["entity" => [
                "id" => "b10000000000",
                "name" => "Whoops"
            ]]);
        } catch (Geotab\MyGeotabException $e) {
            $this->assertStringContainsString("Exception", $e->getMessage());
        }
    }
}