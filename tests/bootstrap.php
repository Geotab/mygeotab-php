<?php
require __DIR__ . '/../vendor/autoload.php';

// Define global constants for username/password/database
define("MYGEOTAB_USERNAME", getenv('MYGEOTAB_USERNAME'));
define("MYGEOTAB_PASSWORD", getenv('MYGEOTAB_PASSWORD'));
define("MYGEOTAB_DATABASE", getenv('MYGEOTAB_DATABASE'));