<?php
namespace Geotab;

/**
* Class API
* @package Geotab
*/
class API
{
    private $username, $password, $server, $database;
    private $jsonp = false;

    public function __construct() {

    }

    public function authenticate() {
        $response = $this->call("Authenticate", array("database" => $this->database, "userName" => $this->username, "password" => $this->password));
        //var_dump($response);
    }

    public function call($method, $params = null) {
        return $this->request($method, $params);
    }

    public function multiCall() {

    }

    private function request($method, array $post = NULL) {
        $url = "https://" . $this->server . "/apiv1";

        $json = ["method" => $method, "params" => $post];

        $contentLength = strlen("JSON-RPC=" . urlencode(json_encode($json)));

        $defaults = array( 
            CURLOPT_URL => $url, 
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => "JSON-RPC=" . urlencode(json_encode($json)),
            CURLOPT_HTTPHEADER => ["Connection: keep-alive", "Content-Type: application/x-www-form-urlencoded; charset=UTF-8", "Transfer-Encoding: chunked"],
            CURLOPT_HEADER => 1,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 0,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false     //need CA certificates, but this is a hack
        );

        $ch = curl_init();
        curl_setopt_array($ch, $defaults); 
        
        if( ! $result = curl_exec($ch)) 
        { 
            trigger_error(curl_error($ch)); 
        } 
        curl_close($ch); 

        return $result;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getPassword() {
        return $this->password;
    }
    
    public function getServer() {
        return $this->server;
    }
    
    public function getDatabase() {
        return $this->database;
    }

    public function setUsername($val) {
        $this->username = $val;
        return $this;
    }

    public function setPassword($val) {
        $this->password = $val;
        return $this;
    }
    
    public function setServer($val) {
        $this->server = is_null($val) || $val == "" ? "my.geotab.com" : $val;
        return $this;
    }
    
    public function setDatabase($val) {
        $this->database = $val;
        return $this;
    }
}
