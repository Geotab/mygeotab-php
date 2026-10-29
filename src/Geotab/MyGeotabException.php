<?php
namespace Geotab;

class MyGeotabException extends \Exception
{
    public $error = null;

    public function __construct($error)
    {
        $this->error = $error["error"];
        $message = $this->error["message"] ?? null;
        parent::__construct(is_string($message) ? $message : "MyGeotab API error");
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->error["data"]["type"]}]: {$this->message}\n";
    }
}
