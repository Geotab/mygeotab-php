<?php
namespace Geotab;

use GuzzleHttp\Client;

/**
 * Class API
 * @package Geotab
 */
class API
{
    /**
     * @var Credentials|null
     */
    private $credentials = null;

    /**
     * @var Client|null
     */
    private $client = null;

    /**
     * @param string $username Username/email address for MyGeotab server
     * @param string $password Password for MyGeotab server
     * @param string $database Database name on MyGeotab
     * @param string $server Server domain name on the MyGeotab federation (i.e. my.geotab.com)
     * @throws \Exception
     */
    public function __construct($username, $password = null, $database = null, $server = "my.geotab.com") {
        if ($username == null) {
            throw new \Exception("Username is required");
        }
        
        $this->credentials = new Credentials($username, $password, $database, $server);

        $this->client = new Client([
            "base_uri" => $this->resolveUri($server)
        ]);

        return $this;
    }

    /**
     * Authenticates $this.credentials
     */
    public function authenticate() {
        $this->call("Authenticate", [
            "database" => $this->credentials->getDatabase(),
            "userName" => $this->credentials->getUsername(),
            "password" => $this->credentials->getPassword()
        ], function ($result) {
            $credentials = $result["credentials"];

            $this->credentials->setUsername($credentials["userName"]);
            $this->credentials->setDatabase($credentials["database"]);
            $this->credentials->setSessionId($credentials["sessionId"]);

            if ($result["path"] !== "ThisServer") {
                $this->credentials->setServer($result["path"]);
            }

            return $this->credentials;
        }, function ($error) {
            if ($error["name"] == "InvalidUserException") {
                throw new MyGeotabException("Cannot authenticate " . $this->credentials->getUsername() . " on " . $this->credentials->getServer() . "/" . $this->credentials->getDatabase());
            }

            throw new \Exception($error["message"]);
        });
    }

    /**
     * Base method for performing an API call
     * @param $method
     * @param null $params
     * @param null $successCallback
     * @param null $errorCallback
     */
    public function call($method, $params = null, $successCallback = null, $errorCallback = null) {
        if ($this->credentials && $method != "Authenticate") {
            $params["credentials"] = [
                "userName" => $this->credentials->getUsername(),
                "sessionId" => $this->credentials->getSessionId(),
                "database" => $this->credentials->getDatabase(),
            ];
        }

        return $this->request($method, $params, $successCallback, $errorCallback);
    }

    /**
     * Shortcut for making a series of API calls in one request
     * @param array $calls
     * @param $successCallback
     * @param $errorCallback
     */
    public function multiCall($calls = [], $successCallback = null, $errorCallback = null) {
        $callParams = [];
        foreach ($calls as $call) {
            $callParams[] = ["method" => $call[0], "params" => $call[1]];
        }

        return $this->call("ExecuteMultiCall", ["calls" => $callParams], $successCallback, $errorCallback);
    }

    /**
     * Get or search of an entity
     * @param string $typeName
     * @param array $params
     */
    public function get($typeName, $params, $successCallback = null, $errorCallback = null)
    {
        $params["typeName"] = $typeName;
        return $this->call("Get", $params, $successCallback, $errorCallback);
    }

    /**
     * Add an entity
     * @param string $type
     * @param array $entity
     */
    public function add($type, $entity, $successCallback = null, $errorCallback = null) {
        return $this->call("Add", ["typeName" => $type, "entity" => $entity], $successCallback, $errorCallback);
    }

    /**
     * Set an entity
     * @param string $type
     * @param array $entity
     */
    public function set($type, $entity, $successCallback = null, $errorCallback = null)
    {
        return $this->call("Set", ["typeName" => $type, "entity" => $entity], $successCallback, $errorCallback);
    }

    /**
     * Remove an entity
     * @param string $type
     * @param array $entity
     */
    public function remove($type, $entity, $successCallback = null, $errorCallback = null)
    {
        return $this->call("Remove", ["typeName" => $type, "entity" => $entity], $successCallback, $errorCallback);
    }

    /**
     * @return Credentials|null
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * @param Credentials|null $credentials
     */
    public function setCredentials($credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * @param uri The provided uri or server name you're resolving
     * TODO: Improve this to handle if it already has https, etc
     */
    private function resolveUri($uri) {
        return "https://" . $uri;
    }

    /**
     * @param $method The method name (e.g. Add, Set, ExecuteMultiCall)
     * @param array $post The post data object containing searches / entity properties
     * @param null $successCallback Function called when response is successful
     * @param null $errorCallback Function called when response is marked as an error
     */
    private function request($method, array $post, $successCallback, $errorCallback) {

        $response = $this->client->request("POST", "/APIV1", [
            "form_params" => [
                "JSON-RPC" => json_encode(["method" => $method, "params" => $post])
            ],
            "headers" => [
                "User-Agent" => "mygeotab-php/1.0",
                "Content-Type: application/x-www-form-urlencoded",
                "Charset=UTF-8",
                "Cache-Control: no-cache",
                "Pragma: no-cache"
            ],
            "decode_content" => "gzip",
            "verify" => false,   // Need CA certificates, but this is a hack
            // 'debug' => fopen('php://stderr', 'w')
        ]);

        $result = json_decode($response->getBody(), true);

        // If callbacks are specified - then call them. Otherwise, just return the results or throw an error
        $isResultReturned = (isset($result["result"]) || array_key_exists("result", $result));
        if ($isResultReturned) {
            if (is_callable($successCallback)) {
                $successCallback($result["result"]);   
            } else {
                return $result["result"];
            }
        } else if (count($result) == 0) {
            if (is_callable($successCallback)) {
                $successCallback($result);
            } else {
                return $result;
            }
        } else {
            if (is_callable($errorCallback)) {
                $errorCallback($result["error"]["errors"][0]);
            } else {
                throw new MyGeotabException($result["error"]["errors"][0]["message"]);
            }
        }
    }

    /**
     * @param $key
     * @param $arr
     * @return bool
     */
    private function array_check($key, $arr) {
        return (isset($arr[$key]) || array_key_exists($key, $arr));
    }
}
