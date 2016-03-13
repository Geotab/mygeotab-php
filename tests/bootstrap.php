<?php
require __DIR__ . '/../vendor/autoload.php';

// Define global constants for uname/pwd/db so we don't have to in each test
define("MYGEOTAB_USERNAME", getenv('MYGEOTAB_USERNAME'));
define("MYGEOTAB_PASSWORD", getenv('MYGEOTAB_PASSWORD'));
define("MYGEOTAB_DATABASE", getenv('MYGEOTAB_DATABASE'));