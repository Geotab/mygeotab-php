<?php
namespace Geotab;

class MyGeotabException extends \Exception
{
    public $error = null;

    public function __construct($error)
    {
        $this->error = is_array($error) ? ($error["error"] ?? null) : null;
        $message = is_array($this->error) ? ($this->error["message"] ?? null) : null;
        parent::__construct(is_string($message) ? $message : "MyGeotab API error");
    }

    public function __toString()
    {
        $data = is_array($this->error) ? ($this->error["data"] ?? null) : null;
        $type = is_array($data) ? ($data["type"] ?? null) : null;
        $type = is_string($type) ? $type : "Unknown";
        return __CLASS__ . ": [{$type}]: {$this->message}\n";
    }
}
