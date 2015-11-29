<?php
namespace Geotab;

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
     * @param $username
     * @param null $password
     * @param null $database
     * @param null $sessionId
     * @param string $server
     * @throws \Exception
     */
    public function __construct($username, $password = null, $database = null, $server = "my.geotab.com") {
        if ($username == null) {
            throw new \Exception("Username is required");
        }
        
        $this->credentials = new Credentials($username, $password, $database, $sessionId, $server);

        return $this;
    }

    /**
     * Authenticates $this.credentials
     */
    public function authenticate() {
        $this->call("Authenticate", null, [
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
                throw new \Exception("Cannot authenticate " . $this->credentials->getUsername() . " on " . $this->credentials->getServer() . "/" . $this->credentials->getDatabase());
            }
        });
    }

    /**
     * Base method for performing an API call
     * @param $method
     * @param null $typeName
     * @param null $params
     * @param null $successCallback
     * @param null $errorCallback
     */
    public function call($method, $typeName = null, $params = null, $successCallback = null, $errorCallback = null) {
        if ($this->credentials) {
            $params["credentials"] = [
                "userName" => $this->credentials->getUsername(),
                "sessionId" => $this->credentials->getSessionId(),
                "database" => $this->credentials->getDatabase(),
            ];
        }

        if ($typeName) {
            $params["typeName"] = $typeName;
        }

        $this->request($method, $params, $successCallback, $errorCallback);
    }

    /**
     * Shortcut for making a series of API calls in one request
     * @param array $calls
     * @param $successCallback
     * @param $errorCallback
     */
    public function multiCall($calls = [], $successCallback, $errorCallback) {
        $callParams = [];
        foreach ($calls as $call) {
            $callParams[] = ["method" => $call[0], "params" => $call[1]];
        }

        $this->call("ExecuteMultiCall", null, ["calls" => $callParams], $successCallback, $errorCallback);
    }

    /**
     * Get or search of an entity
     * @param string $type
     * @param array $params
     */
    public function get($type, $params)
    {
        $this->call("Get", $type, $params);
    }

    /**
     * Add an entity
     * @param string $type
     * @param array $entity
     */
    public function add($type, $entity) {
        $this->call("Add", $type, ["entity" => $entity]);
    }

    /**
     * Set an entity
     * @param string $type
     * @param array $entity
     */
    public function set($type, $entity)
    {
        $this->call("Set", $type, ["entity" => $entity]);
    }

    /**
     * Remove an entity
     * @param string $type
     * @param array $entity
     */
    public function remove($type, $entity)
    {
        $this->call("Remove", $type, ["entity" => $entity]);
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
     * @param $method
     * @param array $post
     * @param null $successCallback
     * @param null $errorCallback
     */
    private function request($method, array $post = null, $successCallback = null, $errorCallback = null) {
        $url = "https://" . $this->credentials->getServer() . "/apiv1";
        $postData = "JSON-RPC=" . urlencode(json_encode(["method" => $method, "params" => $post]));

        $headers = [
            "Connection: keep-alive", 
            "Content-Type: application/x-www-form-urlencoded", 
            "Charset=UTF-8",
            "Cache-Control: no-cache",
            "Pragma: no-cache"
        ];

        $defaults = array( 
            CURLOPT_URL => $url, 
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => "gzip",
            CURLOPT_SSL_VERIFYPEER => false     //need CA certificates, but this is a hack
        );

        $ch = curl_init();
        curl_setopt_array($ch, $defaults); 
        //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');    //fiddler proxy
        
        $response = curl_exec($ch);
        $error = curl_error($ch);

        if($error != "") { 
            trigger_error(curl_error($ch));
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        //$headerReturn = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($ch);

        $result = json_decode($body, true);
        if ($this->array_check("result", $result)) {
            is_callable($successCallback) && $successCallback($result["result"]);
        } else {
            is_callable($errorCallback) && $errorCallback($result["error"]["errors"][0]);
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
