<?php
namespace Geotab;

/**
* Class API
* @package Geotab
*/
class API
{
    private $username, $password, $server, $database;
    private $credentials = null;
    private $jsonp = false;

    public function __construct() {

    }

    public function authenticate() {
        $result = $this->call("Authenticate", ["database" => $this->database, "userName" => $this->username, "password" => $this->password], function ($result) {
            $this->credentials = $result["credentials"];
        }, function ($error) {
            //Nothing
        });
    }

    public function call($method, $params = null, $successCallback = null, $errorCallback = null) {
        if ($this->credentials) {
            $params["credentials"] = $this->credentials;
        }
        $result = $this->request($method, $params, $header);
        $arrayResult = json_decode($result, true);
        

        if ($arrayResult["result"] == null) {
            $errorCallback($arrayResult["error"]);
        } else {
            $successCallback($arrayResult["result"]);
        }

        return $header;
    }

    public function multiCall() {

    }

    private function request($method, array $post = NULL, &$headerReturn = "") {
        $url = "https://" . $this->server . "/apiv1";
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

    public function getUsername() {
        return $this->username;
    }

    public function setUsername($val) {
        $this->username = $val;
        return $this;
    }

    public function getPassword() {
        return $this->password;
    }
    
    public function setPassword($val) {
        $this->password = $val;
        return $this;
    }
    
    public function getDatabase() {
        return $this->database;
    }
    public function setDatabase($val) {
        $this->database = $val;
        return $this;
    }

    public function getServer() {
        return $this->server;
    }

    public function setServer($val) {
        $this->server = is_null($val) || $val == "" ? "my.geotab.com" : $val;
        return $this;
    }
}
