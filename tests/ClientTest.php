<?php
namespace Geotab\Tests;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testCall() {
        $api = new Geotab\API();
        $api->setServer('my.geotab.com')->setDatabase('mydb')
            ->setUsername('xyz')->setPassword('zzz');

        $api->Authenticate();

        $api->call("Get", ["typeName" => "Device", "search" => ["id" => "b3"]], function ($result) {
            var_dump($result);  
        });

        $this->assertEquals(true, true);
    }
}
