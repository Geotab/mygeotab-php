<?php
namespace Geotab\Tests;

use Geotab\API;
use Geotab\Credentials;
use Geotab\MyGeotabException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeClient(array $responses): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]);
    }

    private function mockSuccess(mixed $result): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['result' => $result]));
    }

    private function mockError(string $type, string $message): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'error' => [
                'code'    => -32000,
                'data'    => ['id' => null, 'type' => $type, 'requestIndex' => 0],
                'message' => $message,
            ],
        ]));
    }

    /** Returns a realistic successful Authenticate response. */
    private function authResponse(string $path = 'ThisServer'): Response
    {
        return $this->mockSuccess([
            'credentials' => [
                'userName'  => 'user@example.com',
                'sessionId' => '3B2739EB-A123-4567-8901-ABCDEF012345',
                'database'  => 'DemoDatabase',
            ],
            'path' => $path,
        ]);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorThrowsOnNullUsername(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Username is required');
        new API(null);
    }

    public function testConstructorAcceptsInjectedClient(): void
    {
        $client = $this->makeClient([$this->mockSuccess('8.0.0.1')]);
        $api    = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com', $client);
        $this->assertSame('8.0.0.1', $api->call('GetVersion', []));
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function testAuthenticateUpdatesCredentials(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse()]));
        $api->authenticate();

        $creds = $api->getCredentials();
        $this->assertSame('user@example.com', $creds->getUsername());
        $this->assertSame('3B2739EB-A123-4567-8901-ABCDEF012345', $creds->getSessionId());
        $this->assertSame('DemoDatabase', $creds->getDatabase());
    }

    public function testAuthenticateClearsPassword(): void
    {
        $api = new API('user@example.com', 'secret', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse()]));
        $api->authenticate();

        $this->assertNull($api->getCredentials()->getPassword());
    }

    public function testAuthenticateFollowsServerRedirect(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse('my3.geotab.com')]));
        $api->authenticate();

        $this->assertSame('my3.geotab.com', $api->getCredentials()->getServer());
    }

    public function testAuthenticateKeepsServerWhenThisServer(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse('ThisServer')]));
        $api->authenticate();

        $this->assertSame('my.geotab.com', $api->getCredentials()->getServer());
    }

    public function testAuthenticateThrowsOnBadCredentials(): void
    {
        $api = new API('bad@example.com', 'wrong', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->mockError('InvalidUserException', 'Incorrect login credentials')]));

        $this->expectException(MyGeotabException::class);
        $this->expectExceptionMessage('Incorrect login credentials');
        $api->authenticate();
    }

    // -------------------------------------------------------------------------
    // call() — GetVersion
    // -------------------------------------------------------------------------

    public function testCallReturnsResultDirectly(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess('8.0.0.1')]));
        $api->authenticate();

        $this->assertSame('8.0.0.1', $api->call('GetVersion', []));
    }

    public function testCallInvokesSuccessCallback(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess('8.0.0.1')]));
        $api->authenticate();

        $called = false;
        $api->call('GetVersion', [], function ($result) use (&$called) {
            $called = true;
            $this->assertSame('8.0.0.1', $result);
        });
        $this->assertTrue($called, 'Success callback was never invoked');
    }

    // -------------------------------------------------------------------------
    // get() — various entity types
    // -------------------------------------------------------------------------

    public function testGetDevice(): void
    {
        $fixture = [[
            'id'           => 'b1',
            'name'         => 'Fleet Truck 01',
            'serialNumber' => 'GT9000000001',
            'deviceType'   => 'GO9',
            'activeFrom'   => '1986-01-01T00:00:00.000Z',
            'activeTo'     => '2050-01-01T00:00:00.000Z',
            'groups'       => [['id' => 'b3821']],
        ]];

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess($fixture)]));
        $api->authenticate();

        $results = $api->get('Device', ['resultsLimit' => 1]);
        $this->assertCount(1, $results);
        $this->assertSame('b1', $results[0]['id']);
        $this->assertSame('Fleet Truck 01', $results[0]['name']);
        $this->assertSame('GO9', $results[0]['deviceType']);
    }

    public function testGetUser(): void
    {
        $fixture = [[
            'id'        => 'b2',
            'firstName' => 'Jane',
            'lastName'  => 'Doe',
            'name'      => 'Jane Doe',
            'userName'  => 'jane.doe@example.com',
            'userAuthenticationType' => 'BasicAuthentication',
        ]];

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess($fixture)]));
        $api->authenticate();

        $results = $api->get('User', ['search' => ['userName' => 'jane.doe@example.com']]);
        $this->assertCount(1, $results);
        $this->assertSame('Jane', $results[0]['firstName']);
        $this->assertSame('Doe', $results[0]['lastName']);
        $this->assertSame('jane.doe@example.com', $results[0]['userName']);
    }

    public function testGetTrip(): void
    {
        $fixture = [[
            'id'       => 'a1B2C3D4E5F6',
            'device'   => ['id' => 'b1'],
            'driver'   => ['id' => 'b2'],
            'start'    => '2024-01-15T08:00:00.000Z',
            'stop'     => '2024-01-15T09:30:00.000Z',
            'distance' => 45.7,
        ]];

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess($fixture)]));
        $api->authenticate();

        $results = $api->get('Trip', [
            'search'       => ['deviceSearch' => ['id' => 'b1']],
            'resultsLimit' => 1,
        ]);
        $this->assertCount(1, $results);
        $this->assertSame('a1B2C3D4E5F6', $results[0]['id']);
        $this->assertSame(45.7, $results[0]['distance']);
        $this->assertSame('b1', $results[0]['device']['id']);
    }

    public function testGetZone(): void
    {
        $fixture = [[
            'id'        => 'b100',
            'name'      => 'Warehouse',
            'comment'   => 'Main distribution center',
            'zoneTypes' => [['id' => 'b1']],
            'points'    => [
                ['x' => -79.5, 'y' => 43.6],
                ['x' => -79.4, 'y' => 43.7],
                ['x' => -79.4, 'y' => 43.6],
            ],
        ]];

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess($fixture)]));
        $api->authenticate();

        $results = $api->get('Zone', ['resultsLimit' => 1]);
        $this->assertCount(1, $results);
        $this->assertSame('Warehouse', $results[0]['name']);
        $this->assertCount(3, $results[0]['points']);
    }

    public function testGetExceptionEvent(): void
    {
        $fixture = [[
            'id'         => 'b200',
            'device'     => ['id' => 'b1'],
            'rule'       => ['id' => 'b300'],
            'activeFrom' => '2024-01-15T08:15:00.000Z',
            'activeTo'   => '2024-01-15T08:16:32.000Z',
            'state'      => 'Active',
        ]];

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess($fixture)]));
        $api->authenticate();

        $results = $api->get('ExceptionEvent', ['resultsLimit' => 1]);
        $this->assertCount(1, $results);
        $this->assertSame('Active', $results[0]['state']);
        $this->assertSame('b300', $results[0]['rule']['id']);
    }

    public function testGetDutyStatusLog(): void
    {
        $fixture = [[
            'id'       => 'aDVrUIaQ1ZUWmoTIDd--AJb',
            'device'   => ['id' => 'b3'],
            'driver'   => ['id' => 'b41B4DC05'],
            'dateTime' => '2024-01-15T08:00:00.000Z',
            'status'   => 'ON',
            'origin'   => 'Manual',
        ]];

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess($fixture)]));
        $api->authenticate();

        $results = $api->get('DutyStatusLog', [
            'search'       => ['userSearch' => ['id' => 'b41B4DC05']],
            'resultsLimit' => 1,
        ]);
        $this->assertCount(1, $results);
        $this->assertSame('ON', $results[0]['status']);
        $this->assertSame('b3', $results[0]['device']['id']);
    }

    // -------------------------------------------------------------------------
    // add() / set() / remove()
    // -------------------------------------------------------------------------

    public function testAddEntityReturnsNewId(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess('b999')]));
        $api->authenticate();

        $id = $api->add('Zone', [
            'name'      => 'New Zone',
            'zoneTypes' => [['id' => 'b1']],
            'points'    => [['x' => -79.5, 'y' => 43.6]],
        ]);
        $this->assertSame('b999', $id);
    }

    public function testSetEntityReturnsNull(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess(null)]));
        $api->authenticate();

        $result = $api->set('Device', ['id' => 'b1', 'name' => 'Renamed Vehicle']);
        $this->assertNull($result);
    }

    public function testRemoveEntityReturnsNull(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess(null)]));
        $api->authenticate();

        $result = $api->remove('Zone', ['id' => 'b100']);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // multiCall()
    // -------------------------------------------------------------------------

    public function testMultiCall(): void
    {
        $multiResult = [
            '8.0.0.1',
            [['id' => 'b1', 'name' => 'Fleet Truck 01', 'deviceType' => 'GO9']],
        ];

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess($multiResult)]));
        $api->authenticate();

        $results = $api->multiCall([
            ['GetVersion', []],
            ['Get', ['typeName' => 'Device', 'resultsLimit' => 1]],
        ]);

        $this->assertCount(2, $results);
        $this->assertSame('8.0.0.1', $results[0]);
        $this->assertSame('b1', $results[1][0]['id']);
        $this->assertSame('GO9', $results[1][0]['deviceType']);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function testErrorCallbackInvokedOnFailure(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([
                $this->authResponse(),
                $this->mockError('MissingMemberException', 'The object could not be found: Exception'),
            ]));
        $api->authenticate();

        $errorCalled = false;
        $api->set('Device', ['id' => 'b99999', 'name' => 'Ghost'],
            null,
            function ($error) use (&$errorCalled) {
                $errorCalled = true;
                $this->assertArrayHasKey('error', $error);
                $this->assertStringContainsString('Exception', $error['error']['message']);
                $this->assertSame('MissingMemberException', $error['error']['data']['type']);
            }
        );
        $this->assertTrue($errorCalled, 'Error callback was never invoked');
    }

    public function testExceptionThrownWhenNoErrorCallback(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([
                $this->authResponse(),
                $this->mockError('MissingMemberException', 'The object could not be found: Exception'),
            ]));
        $api->authenticate();

        $this->expectException(MyGeotabException::class);
        $this->expectExceptionMessage('The object could not be found: Exception');
        $api->set('Device', ['id' => 'b99999', 'name' => 'Ghost']);
    }

    public function testMyGeotabExceptionContainsErrorDetails(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([
                $this->mockError('InvalidUserException', 'Incorrect login credentials'),
            ]));

        try {
            $api->authenticate();
            $this->fail('Expected MyGeotabException was not thrown');
        } catch (MyGeotabException $e) {
            $this->assertSame('InvalidUserException', $e->error['data']['type']);
            $this->assertSame('Incorrect login credentials', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Credentials management
    // -------------------------------------------------------------------------

    public function testGetCredentialsReturnsCredentialsInstance(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([]));

        $creds = $api->getCredentials();
        $this->assertInstanceOf(Credentials::class, $creds);
        $this->assertSame('user@example.com', $creds->getUsername());
        $this->assertSame('DemoDatabase', $creds->getDatabase());
    }

    public function testSetCredentialsReplacesCredentials(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([]));

        $api->setCredentials(null);
        $this->assertNull($api->getCredentials());
    }
}
