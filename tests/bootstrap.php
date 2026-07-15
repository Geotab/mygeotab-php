<?php
require __DIR__ . '/../vendor/autoload.php';

define("MYGEOTAB_USERNAME", getenv('MYGEOTAB_USERNAME'));
define("MYGEOTAB_PASSWORD", getenv('MYGEOTAB_PASSWORD'));
define("MYGEOTAB_DATABASE", getenv('MYGEOTAB_DATABASE'));
define("MYGEOTAB_SERVER",   getenv('MYGEOTAB_SERVER') ?: "my.geotab.com");