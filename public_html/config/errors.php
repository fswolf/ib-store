<?php
Flight::map('notFound', function() {
	Flight::redirect('/404');
});

// Catch PHP errors and fatals
/*set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new Exception("Error [$errno]: $errstr in $errfile on line $errline");
});

set_exception_handler(function($ex) {
    Flight::redirect('/500');
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        Flight::redirect('/500');
    }
});

// Flight error map as a backup
Flight::map('error', function(Exception $ex) {
    Flight::redirect('/500');
});*/

?>