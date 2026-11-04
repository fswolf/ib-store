<?php
// -- Global Variables -- //
require_once 'config/config.php'; 

// -- Load core files -- //
require_once 'flight/Flight.php'; // Flight framework
require_once 'config/security.php'; // Request Limit / Header securtiy
require_once 'utilities.php'; // Utility functions
require_once 'autoloader.php'; // Autoload classes
require_once 'routes.php'; // Route declarations
require_once 'config/errors.php'; // Error Handling

// -- Launch the application -- //
Flight::start();
?>
