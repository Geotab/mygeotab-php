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
                throw new \Exception("Cannot authenticate " . $this->credentials->getUsername() . " on " . $this->credentials->getServer() . "/" . $this->credentials->getDatabase());
            }
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

        $this->request($method, $params, $successCallback, $errorCallback);
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

        $this->call("ExecuteMultiCall", ["calls" => $callParams], $successCallback, $errorCallback);
    }

    /**
     * Get or search of an entity
     * @param string $typeName
     * @param array $params
     */
    public function get($typeName, $params, $successCallback, $errorCallback)
    {
        $params["typeName"] = $typeName;
        $this->call("Get", $params, $successCallback, $errorCallback);
    }

    /**
     * Add an entity
     * @param string $type
     * @param array $entity
     */
    public function add($type, $entity) {
        $this->call("Add", ["typeName" => $type, "entity" => $entity], $successCallback, $errorCallback);
    }

    /**
     * Set an entity
     * @param string $type
     * @param array $entity
     */
    public function set($type, $entity, $successCallback, $errorCallback)
    {
        $this->call("Set", ["typeName" => $type, "entity" => $entity], $successCallback, $errorCallback);
    }

    /**
     * Remove an entity
     * @param string $type
     * @param array $entity
     */
    public function remove($type, $entity, $successCallback, $errorCallback)
    {
        $this->call("Remove", ["typeName" => $type, "entity" => $entity], $successCallback, $errorCallback);
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
            CURLOPT_SSL_VERIFYPEER => false,     //need CA certificates, but this is a hack
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => 6     //CURL_SSLVERSION_TLSv1_2
        );

        $ch = curl_init();
        curl_setopt_array($ch, $defaults); 

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
        } else if (count($result) == 0) {
            is_callable($successCallback) && $successCallback($result);
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
