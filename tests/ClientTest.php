<?php
namespace Geotab\Tests;

use Geotab;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testCall() {
        $username = getenv('MYGEOTAB_USERNAME');
        $password = getenv('MYGEOTAB_PASSWORD');
        $database = getenv('MYGEOTAB_DATABASE');

        if (!$username) {
            $this->markTestSkipped("Environment MYGEOTAB_USERNAME not defined, so no API call can be made");
        }

        $api = new Geotab\API($username, $password, $database);
        $api->authenticate();

        $api->call("GetVersion", [], function ($result) {
            $version = explode(".", $result);

            // There should be 4 parts of the version
            $this->assertEquals(4, count($version));
        }, function ($error) {
            $this->fail($error);
        });
    }
}
