<?php
Flight::map('rateLimitFile', function() {
    $ip = Flight::request()->ip;
    $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $ip);

    $base = $_SERVER['DOCUMENT_ROOT'];
    $cacheFile = $base . '/cache/req/rate_' . $key . '.json';
    $window = 60;

    $dir = $base . '/cache/req/';

    // only scan if directory is getting large
    $files = glob($dir . 'rate_*.json');

    if ($files !== false && count($files) > 200) {
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $age = $now - filemtime($file);
                if ($age > $window) {
                    unlink($file);
                }
            }
        }
    }

    if (file_exists($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        if ($age > $window) unlink($cacheFile);
    }

    $attempts = 0;

    if (file_exists($cacheFile)) {
        $decoded = json_decode(file_get_contents($cacheFile), true);
        if (isset($decoded['attempts'])) {
            $attempts = (int)$decoded['attempts'];
        }
    }

    $attempts++;

    if ($attempts >= REQUEST_LIMIT) {
        Flight::halt(429, 'Too many requests');
    }

    file_put_contents($cacheFile, json_encode(['attempts' => $attempts]), LOCK_EX);
});

Flight::map('rateLimitRedis', function() {
    static $redis = null;

    // lazy init (only connect once)
    if ($redis === null) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
    }

    $ip = Flight::request()->ip;
    $key = 'rate:' . preg_replace('/[^a-zA-Z0-9_]/', '_', $ip);

    $window = 60;

    // atomic increment
    $attempts = $redis->incr($key);

    // set expiry ONLY on first hit
    if ($attempts === 1) {
        $redis->expire($key, $window);
    }

    if ($attempts >= REQUEST_LIMIT) {
        Flight::halt(429, 'Too many requests');
    }
});

Flight::map('rateLimit', function() {
    if (class_exists('Redis')) {
        Flight::rateLimitRedis();
    } else {
        Flight::rateLimitFile();
    }
});

Flight::before('start', function() {
    if(THROTTLE) Flight::rateLimit();

    // Set the X-Frame-Options header to prevent clickjacking
    Flight::response()->header('X-Frame-Options', 'SAMEORIGIN');
    // Set the Content-Security-Policy header to prevent XSS
    Flight::response()->header("Content-Security-Policy", "default-src 'self';");
    // Set the X-XSS-Protection header to prevent XSS
    Flight::response()->header('X-XSS-Protection', '1; mode=block');
    // Set the X-Content-Type-Options header to prevent MIME sniffing
    Flight::response()->header('X-Content-Type-Options', 'nosniff');
    // Set the Referrer-Policy header to control how much referrer information is sent
    Flight::response()->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    // Set the Strict-Transport-Security header to force HTTPS
    Flight::response()->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    // Set the Permissions-Policy header to control what features and APIs can be used
    Flight::response()->header('Permissions-Policy','geolocation=(), microphone=(), camera=(), payment=()');
});

?>