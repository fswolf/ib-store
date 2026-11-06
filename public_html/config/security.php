<?php
// -- Rate limits -- //
// one counter per key per window: redis when the extension is there and the server answers, files in cache/req/
// otherwise. answers how many hits the key has had this window, the current one included
Flight::map('rateHit', function($key, $window = 60) {
    $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    $attempts = Flight::rateHitRedis($key, $window);
    return $attempts !== null ? $attempts : Flight::rateHitFile($key, $window);
});

Flight::map('rateHitFile', function($key, $window) {
    $dir = dirname(__DIR__) . '/cache/req/';
    if (!is_dir($dir)) mkdir($dir, 0755, true); // cache/ is not in git, nothing else makes this one
    $cacheFile = $dir . 'rate_' . $key . '.json';
    $now = time();

    // only scan if directory is getting large. an hour outlives every window
    $files = glob($dir . 'rate_*.json');
    if ($files !== false && count($files) > 200) {
        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) > 3600) unlink($file);
        }
    }

    // the window runs from the first hit, kept in the file (its mtime moves on every write)
    $hit = array('start' => $now, 'attempts' => 0);
    if (is_file($cacheFile)) {
        $decoded = json_decode((string) file_get_contents($cacheFile), true);
        if (isset($decoded['start'], $decoded['attempts']) && $now - (int) $decoded['start'] < $window) $hit = $decoded;
    }
    $hit['attempts']++;

    file_put_contents($cacheFile, json_encode($hit), LOCK_EX);
    return (int) $hit['attempts'];
});

// null when redis is missing or not answering, so the caller falls back to files instead of the site going down
Flight::map('rateHitRedis', function($key, $window) {
    static $redis = null;
    if ($redis === false || !class_exists('Redis')) return null;

    try {
        // lazy init (only connect once)
        if ($redis === null) {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379, 0.5);
        }

        // atomic increment, expiry set ONLY on first hit
        $attempts = $redis->incr('rate:' . $key);
        if ($attempts === 1) $redis->expire('rate:' . $key, $window);
        return (int) $attempts;
    }
    catch (Throwable $e) {
        $redis = false;
        return null;
    }
});

// every request: REQUEST_LIMIT a minute per visitor
Flight::map('rateLimit', function() {
    if (Flight::rateHit('req_' . client_ip(), 60) >= REQUEST_LIMIT) {
        Flight::halt(429, 'Too many requests');
    }
});

// sign in, register, forgot and reset: AUTH_LIMIT tries per AUTH_WINDOW per visitor and per account email, so
// guessing passwords is slow from one address and slow against one account from many. throws, the api answers it
Flight::map('authLimit', function($email = '') {
    $limit = defined('AUTH_LIMIT') ? AUTH_LIMIT : 10;
    $window = defined('AUTH_WINDOW') ? AUTH_WINDOW : 900;
    if (!$limit) return;

    $over = Flight::rateHit('auth_ip_' . client_ip(), $window) > $limit;
    $email = strtolower(trim((string) $email));
    if ($email !== '' && Flight::rateHit('auth_email_' . sha1($email), $window) > $limit) $over = true;
    if ($over) throw new Exception('Too many tries, wait a few minutes and try again.');
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