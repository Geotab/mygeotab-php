<?php
namespace Geotab;

/**
* Class API
* @package Geotab
*/
class API
{
    private $credentials = null;
    //private $jsonp = false;

    public function __construct($username, $password = null, $database = null, $sessionId = null, $server = "my.geotab.com") {
        if ($username == null) {
            throw new \Exception("Username is required");
        }
        if ($password == null && $sessionId == null) {
            throw new \Exception("You need at least a password or a sessionId");
        }
        
        $this->credentials = new Credentials($username, $password, $database, $sessionId, $server);

        return $this;
    }

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

    public function call($method, $params = null, $successCallback = null, $errorCallback = null) {
        if ($this->credentials) {
            $params["credentials"] = $this->credentials;
        }
        $result = $this->request($method, $params, $header);
        $arrayResult = json_decode($result, true);

        if ($this->array_check("result", $arrayResult)) {
            is_callable($successCallback) && $successCallback($arrayResult["result"]);
        } else {
            is_callable($errorCallback) && $errorCallback($arrayResult["error"]["errors"][0]);
        }

        return $header;
    }

    public function multiCall() {

    }

    private function request($method, array $post = NULL, &$headerReturn = "") {
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
            return $error;
        } 

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerReturn = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($ch);
        return $body;
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

    private function array_check($key, $arr) {
        return (isset($arr[$key]) || array_key_exists($key, $arr));
    }
}
