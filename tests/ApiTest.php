<?php
namespace Geotab\Tests;

use Geotab\API;
use Geotab\Credentials;
use Geotab\MyGeotabException;
use Composer\InstalledVersions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
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
        return new Response(200, ['Content-Type' => 'application/json'],
            json_encode(['result' => $result, 'jsonrpc' => '2.0']));
    }

    private function mockError(string $type, string $message): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'error' => [
                'code'    => -32000,
                'data'    => ['id' => null, 'type' => $type, 'requestIndex' => 0],
                'message' => $message,
            ],
            'jsonrpc' => '2.0',
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
        $client = $this->makeClient([$this->mockSuccess('11.133.449')]);
        $api    = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com', $client);
        $this->assertSame('11.133.449', $api->call('GetVersion', []));
    }

    // -------------------------------------------------------------------------
    // Debug output and sensitive arguments
    // -------------------------------------------------------------------------

    public function testCredentialsDebugOutputRedactsSecrets(): void
    {
        $credentials = new Credentials('user@example.com', null, 'DemoDatabase', 'my.geotab.com');
        $credentials->setPassword('debug-password-secret');
        $credentials->setSessionId('debug-session-secret');

        foreach (['print_r', 'var_dump'] as $dump) {
            ob_start();
            try {
                $dump($credentials);
                $output = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            $this->assertStringNotContainsString('debug-password-secret', $output);
            $this->assertStringNotContainsString('debug-session-secret', $output);
            $this->assertStringContainsString('********', $output);
            $this->assertStringContainsString('DemoDatabase', $output);
        }
        $this->assertSame('debug-password-secret', $credentials->getPassword());
        $this->assertSame('debug-session-secret', $credentials->getSessionId());
    }

    public function testApiDebugOutputRedactsSecretsAndClientInternals(): void
    {
        $api = new API('user@example.com', 'debug-password-secret', 'DemoDatabase', 'my.geotab.com',
            new Client(['headers' => ['Authorization' => 'client-authorization-secret']]));
        $api->getCredentials()->setSessionId('debug-session-secret');

        foreach (['print_r', 'var_dump'] as $dump) {
            ob_start();
            try {
                $dump($api);
                $output = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            $this->assertStringNotContainsString('debug-password-secret', $output);
            $this->assertStringNotContainsString('debug-session-secret', $output);
            $this->assertStringNotContainsString('client-authorization-secret', $output);
            $this->assertStringContainsString('********', $output);
            $this->assertStringContainsString(Client::class, $output);
        }
    }

    public function testDebugInfoPreservesUnsetCredentials(): void
    {
        $credentials = new Credentials('user@example.com', null, 'DemoDatabase', 'my.geotab.com');
        $this->assertNull($credentials->__debugInfo()['password']);
        $this->assertNull($credentials->__debugInfo()['sessionId']);

        $api = new API('user@example.com');
        $api->setCredentials(null);
        $this->assertNull($api->__debugInfo()['credentials']);
    }

    public function testSecretParametersHaveSensitiveParameterAttribute(): void
    {
        foreach ([
            [API::class, '__construct', 'password'],
            [Credentials::class, '__construct', 'password'],
            [Credentials::class, 'setPassword', 'password'],
            [Credentials::class, 'setSessionId', 'sessionId'],
        ] as [$class, $method, $parameter]) {
            $reflection = new \ReflectionParameter([$class, $method], $parameter);
            $this->assertCount(1, $reflection->getAttributes(\SensitiveParameter::class),
                "$class::$method($parameter)");
        }
    }

    public function testDebugInfoHandlesEmptyAndZeroSecrets(): void
    {
        $credentials = new Credentials('user@example.com', null, 'DemoDatabase', 'my.geotab.com');
        foreach (['', '0'] as $secret) {
            $expected = $secret === '' ? '' : '********';
            $credentials->setPassword($secret);
            $credentials->setSessionId($secret);
            $this->assertSame($expected, $credentials->__debugInfo()['password']);
            $this->assertSame($expected, $credentials->__debugInfo()['sessionId']);
        }
    }

    public function testConstructorExceptionTraceRedactsPassword(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            new API(null, 'trace-password-secret');
            $this->fail('Expected constructor to reject a null username');
        } catch (\Exception $error) {
            $arguments = $error->getTrace()[0]['args'];
            $this->assertInstanceOf(\SensitiveParameterValue::class, $arguments[1]);
            $this->assertStringNotContainsString('trace-password-secret', print_r($error->getTrace(), true));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }

    // -------------------------------------------------------------------------
    // User-Agent
    // -------------------------------------------------------------------------

    public function testUserAgentHeaderIsSent(): void
    {
        $container = [];
        $stack     = HandlerStack::create(new MockHandler([$this->mockSuccess('11.133.449')]));
        $stack->push(Middleware::history($container));

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            new Client(['handler' => $stack]));
        $api->call('GetVersion', []);

        $userAgent = $container[0]['request']->getHeaderLine('User-Agent');
        $this->assertStringStartsWith('mygeotab-php/', $userAgent);
    }

    public function testUserAgentVersionMatchesInstalledPackage(): void
    {
        $container = [];
        $stack     = HandlerStack::create(new MockHandler([$this->mockSuccess('11.133.449')]));
        $stack->push(Middleware::history($container));

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            new Client(['handler' => $stack]));
        $api->call('GetVersion', []);

        $userAgent       = $container[0]['request']->getHeaderLine('User-Agent');
        $expectedVersion = InstalledVersions::getPrettyVersion('geotab/mygeotab-php') ?? 'dev';
        $this->assertSame("mygeotab-php/{$expectedVersion}", $userAgent);
    }

    public function testUserAgentVersionIsTagOrDev(): void
    {
        $container = [];
        $stack     = HandlerStack::create(new MockHandler([$this->mockSuccess('11.133.449')]));
        $stack->push(Middleware::history($container));

        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            new Client(['handler' => $stack]));
        $api->call('GetVersion', []);

        $version = substr($container[0]['request']->getHeaderLine('User-Agent'), strlen('mygeotab-php/'));

        // On a tagged release: "3.0.0". In development: "dev-main" or "dev".
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$|^dev/', $version);
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
            $this->makeClient([$this->authResponse(), $this->mockSuccess('11.133.449')]));
        $api->authenticate();

        $this->assertSame('11.133.449', $api->call('GetVersion', []));
    }

    public function testCallInvokesSuccessCallback(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([$this->authResponse(), $this->mockSuccess('11.133.449')]));
        $api->authenticate();

        $called = false;
        $api->call('GetVersion', [], function ($result) use (&$called) {
            $called = true;
            $this->assertSame('11.133.449', $result);
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
            '11.133.449',
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
        $this->assertSame('11.133.449', $results[0]);
        $this->assertSame('b1', $results[1][0]['id']);
        $this->assertSame('GO9', $results[1][0]['deviceType']);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public static function invalidResponses(): array
    {
        return [
            'non-JSON' => ['<html>private response</html>'],
            'null' => ['null'],
            'string' => ['"private response"'],
            'number' => ['42'],
            'boolean' => ['false'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidResponses')]
    public function testInvalidResponseThrowsMyGeotabException(string $body): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([new Response(200, [], $body)]));

        $this->expectException(MyGeotabException::class);
        $this->expectExceptionMessage('Invalid API response');
        $api->call('GetVersion', []);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidResponses')]
    public function testInvalidResponseUsesErrorCallback(string $body): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([new Response(200, [], $body)]));
        $called = false;
        $result = $api->call('GetVersion', [], function () {
            $this->fail('Invalid responses must not invoke the success callback');
        }, function ($error) use (&$called) {
            $called = true;
            $this->assertStringStartsWith('Invalid API response', $error['error']['message']);
            $this->assertStringNotContainsString('private response', $error['error']['message']);
            $this->assertSame('InvalidResponseException', $error['error']['data']['type']);
            $this->assertStringContainsString('[InvalidResponseException]', (string)new MyGeotabException($error));
        });
        $this->assertTrue($called);
        $this->assertNull($result);
    }

    public static function malformedMessages(): array
    {
        return [[[]], [['message' => null]], [['message' => 42]],
            [['message' => ['unexpected']]], [['message' => false]],
            [null], ['str'], [42], [false],
            [['data' => 'str']], [['data' => ['type' => ['unexpected']]]]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedMessages')]
    public function testMalformedErrorMessageDoesNotTypeError(mixed $error): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            $this->makeClient([new Response(200, [], json_encode(['error' => $error]))]));

        set_error_handler(function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $api->call('GetVersion', []);
            $this->fail('Expected MyGeotabException');
        } catch (MyGeotabException $e) {
            $this->assertSame('MyGeotab API error', $e->getMessage());
            $this->assertSame("Geotab\\MyGeotabException: [Unknown]: MyGeotab API error\n", (string)$e);
        } finally {
            restore_error_handler();
        }
    }

    public static function redirectStatuses(): array
    {
        return [[302], [307]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('redirectStatuses')]
    public function testHttpRedirectIsNotFollowed(int $status): void
    {
        $mock = new MockHandler([
            new Response($status, ['Location' => 'https://other.example/APIV1'], '{"result":"redirect"}'),
            $this->mockSuccess('must not be requested'),
        ]);
        $stack = HandlerStack::create($mock);
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            new Client(['handler' => $stack]));

        try {
            $api->call('GetVersion', []);
            $this->fail('Expected redirect response to be rejected');
        } catch (MyGeotabException $e) {
            $this->assertStringContainsString('redirect', $e->getMessage());
            $this->assertSame('InvalidResponseException', $e->error['data']['type']);
            $this->assertStringContainsString('[InvalidResponseException]', (string)$e);
        }
        $this->assertCount(1, $mock);
    }

    public function testRequestSecurityOptions(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([$this->mockSuccess('version')]));
        $stack->push(Middleware::history($history));
        $api = new API('user@example.com', 'password', 'DemoDatabase', 'my.geotab.com',
            new Client(['handler' => $stack, 'timeout' => 7, 'connect_timeout' => 3]));
        $api->call('GetVersion', []);

        $options = $history[0]['options'];
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(7, $options['timeout']);
        $this->assertSame(3, $options['connect_timeout']);
        $this->assertTrue($options['verify']);
    }

    public function testDefaultClientTimeouts(): void
    {
        $api = new API('user@example.com', 'password', 'DemoDatabase');
        $client = (new \ReflectionProperty(API::class, 'client'))->getValue($api);

        $this->assertSame(30, $client->getConfig('timeout'));
        $this->assertSame(10, $client->getConfig('connect_timeout'));
    }

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
