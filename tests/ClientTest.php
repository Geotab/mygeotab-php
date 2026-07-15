<?php
namespace Geotab\Tests;

use Geotab;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private static ?Geotab\API $api = null;

    public static function setUpBeforeClass(): void
    {
        if (!MYGEOTAB_USERNAME) {
            return;
        }
        self::$api = new Geotab\API(MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE, MYGEOTAB_SERVER);
        self::$api->authenticate();
    }

    protected function setUp(): void
    {
        if (!MYGEOTAB_USERNAME) {
            $this->markTestSkipped('Set MYGEOTAB_USERNAME, MYGEOTAB_PASSWORD, MYGEOTAB_DATABASE, and optionally MYGEOTAB_SERVER environment variables to run integration tests.');
        }
    }

    public function testCall()
    {
        $api = self::$api;

        // First try closure syntax
        $api->call("GetVersion", [], function ($result) {
            $version = explode(".", $result);

            // Version string has 3 parts, e.g. "11.133.449"
            $this->assertEquals(3, count($version));
        }, function ($error) {
            $this->fail($error["error"]["message"]);
        });

        // Then try the "synchronous" return method
        $result = $api->call("GetVersion", []);
        $version = explode(".", $result);

        // Version string has 3 parts, e.g. "11.133.449"
        $this->assertEquals(3, count($version));
    }
    
    /*
    Make an authenticate call and make sure it throws a MyGeotabException
    */
    public function testAuthenticationFailure()
    {
        try {
            $api = new Geotab\API(MYGEOTAB_USERNAME . "INCORRECTUSERNAME", MYGEOTAB_PASSWORD . "INCORRECTPWD", MYGEOTAB_DATABASE, MYGEOTAB_SERVER);
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

        $api = self::$api;

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
        $api = self::$api;

        // Verify get() works with callbacks
        $api->get("Device", [
            "resultsLimit" => 1
        ], function ($result) {
            $this->assertEquals(1, count($result));
        }, function ($errorResult) {
            $this->fail($errorResult["error"]["message"]);
        });

        // Verify get() works with direct return (no callbacks)
        $devices = $api->get("Device", ["resultsLimit" => 1]);
        $this->assertEquals(1, count($devices));
    }

    public function testErrorCall()
    {
        $api = self::$api;

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