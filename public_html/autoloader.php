<?php
// -- Optimized PSR-4 + fallback loader with JSON cache + full namespace support -- //
$baseDirs = [
    __DIR__ . '/controllers/',
    __DIR__ . '/controllers/api/',
    __DIR__ . '/models/',
    __DIR__ . '/libraries/',
    __DIR__ . '/assets/vendor/',   // third party, namespaced (BTCPayServer\Client\Invoice -> assets/vendor/BTCPayServer/Client/Invoice.php)
];

$cacheDir  = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/class_map.json';
$cacheTTL  = 86400; // 24 hours
$classMap  = [];

// --- Ensure cache folder exists ---
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// --- Load cache if exists and not expired ---
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) <= $cacheTTL) {
    $json = file_get_contents($cacheFile);
    $classMap = json_decode($json, true);
}

// --- Generate cache if missing or expired ---
if (empty($classMap)) {
    $classMap = rebuildClassCache($baseDirs, $cacheFile);
}

// --- Register autoloader ---
spl_autoload_register(function ($class) use (&$classMap, $baseDirs, $cacheFile) {

    // --- Cached class map lookup ---
    // the map stores paths relative to public_html so the cache survives a move between servers
    // (an old cache with absolute paths still works until it expires)
    if (isset($classMap[$class])) {
        $file = $classMap[$class];
        if (!is_file($file)) $file = __DIR__ . '/' . $file;
        if (is_file($file)) {
            require $file;
            return;
        }
        // stale entry, fall through and find it the slow way
    }

    // --- PSR-4 fallback ---
    foreach ($baseDirs as $baseDir) {
        $file = $baseDir . str_replace('\\', '/', $class) . '.php';
        if (is_file($file)) {
            $classMap[$class] = substr($file, strlen(__DIR__) + 1);
            require $file;
            return;
        }
    }

    // --- Top-level fallback for legacy classes ---
    foreach ($baseDirs as $baseDir) {
        $file = $baseDir . basename($class) . '.php';
        if (is_file($file)) {
            $classMap[$class] = substr($file, strlen(__DIR__) + 1);
            require $file;
            return;
        }
    }

});

// -- Tiny Ez Auto Load -- //
/*spl_autoload_register(function ($class) {
    $paths = [
        __DIR__.'/controllers/',
        __DIR__.'/controllers/api/',
        __DIR__.'/models/',
        __DIR__.'/libraries/',
    ];

    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});*/

// FlightPHP V3 Autoloader
//Flight::path(__DIR__.'/controllers/');
//Flight::path(__DIR__.'/controllers/api/');
//Flight::path(__DIR__.'/models/');
//Flight::path(__DIR__.'/libraries/');

?>