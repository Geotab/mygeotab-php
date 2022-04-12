<?php
namespace Geotab;

class MyGeotabException extends \Exception
{
    public $error = null;

    public function __construct($error)
    {
        $this->error = $error["error"];
        parent::__construct($this->error["message"]);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->error["data"]["type"]}]: {$this->message}\n";
    }
}
