<?php
// Return whether $haystack ends with $needle
// function used by autoloader dont rename (obsolete)
function endsWith($haystack, $needle) { 
    $length = strlen($needle);
    return $length === 0 || (substr($haystack, -$length) === $needle);
}

function black_list($string) {
	$string =  strtolower($string);
	$blacklist = file(APP_ROOT."assets/black_list_name.txt");
	return in_array($string, $blacklist);
}

function check_symbols($string) {
	return preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $string);
}

function is_uuid($string) { //seems kinda wonky
	return preg_match('/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/', $string);
}

function esc_url($url) { //make this prettyer 

    if (is_null($url)) return false;
    
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
    $strip = array('%0d', '%0a', '%0D', '%0A');
    $url = (string) $url;
 
    $count = 1;
    while ($count) $url = str_replace($strip, '', $url, $count);
 
    $url = str_replace(';//', '://', $url);
    $url = htmlentities($url);
    $url = str_replace('&amp;', '&#038;', $url);
    $url = str_replace("'", '&#039;', $url);
 
 	// We're only interested in relative links from $_SERVER['PHP_SELF']
    if ($url[0] !== '/') return false; 
    return $url;
}

function generate_random_string($len) {
	$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $pass = array(); 
    $alphaLength = strlen($alphabet) - 1; 
    for ($i = 0; $i < $len; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
	return implode($pass);
}

function base64decode($string) {
	if(base64_decode(urldecode($string), true)) 
		return base64_decode(urldecode($string), true);
	return false;
}

function base64encode($string) {
	return urlencode(base64_encode($string));
}

// the store's own address for links that leave the page (emails, gateway return urls). BASE_URL from config,
// never the request's Host header: a visitor can send any host they like and get it written into a reset mail.
// the host fallback is only there so a local test server without BASE_URL still works
function base_url() {
	if (defined('BASE_URL') && BASE_URL !== '') return rtrim(BASE_URL, '/');
	return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// the visitor's address for the rate limits. behind a reverse proxy REMOTE_ADDR is the proxy for everyone, so
// when the request comes from TRUSTED_PROXY take the last X-Forwarded-For entry, the one the proxy added.
// anything left of it came from the visitor and can be made up
function client_ip() {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	if (defined('TRUSTED_PROXY') && TRUSTED_PROXY !== '' && $ip === TRUSTED_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$hops = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
		$last = end($hops);
		if (filter_var($last, FILTER_VALIDATE_IP)) $ip = $last;
	}
	return $ip;
}

// a site relative url for an uploaded file with its change time on the end, so a replaced tab icon or banner
// shows up instead of the browser's cached copy of the old one (same file name, new picture)
function upload_url($path) {
	$file = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
	return '/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

// a digital line's download field split into its files: one name or link, or several separated by commas
// (a build per platform, say). empty entries are dropped
function download_files($value) {
	return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
}

// what a download link shows: the file's name, from a link too (https://host/path/game.zip is game.zip)
function download_name($file) {
	$name = basename((string) parse_url($file, PHP_URL_PATH));
	return $name !== '' ? $name : $file;
}

// a digital product's file inside DOWNLOAD_DIR, false when it is missing or the name tries to climb out of the
// folder. values from before the move (/downloads/file.zip) still work, the leading folder is dropped
function download_path($name) {
	// an older config.php without DOWNLOAD_DIR gets the default, downloads/ next to public_html
	$dir = realpath(defined('DOWNLOAD_DIR') && DOWNLOAD_DIR !== '' ? DOWNLOAD_DIR : dirname(__DIR__) . '/downloads/');
	if (!$dir) return false;
	$name = preg_replace('#^/*(downloads/)?#', '', (string) $name);
	$file = realpath($dir . '/' . $name);
	if (!$file || !is_file($file) || strpos($file, $dir . DIRECTORY_SEPARATOR) !== 0) return false;
	return $file;
}

// are we on https, counting a proxy in front (the nuc terminates ssl before apache sees it)
function is_https() {
	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
	return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

// ---Rebuild the JSON class map cache manually ---
function rebuildClassCache(array $dirs, string $file): array { 
    $map = [];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $contents = file_get_contents($f->getPathname());
                $namespace = '';
                if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
                    $namespace = trim($matches[1]) . '\\';
                }
                if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $contents, $matches)) {
                    $fullClass = $namespace . $matches[1];
                    // relative to public_html, the autoloader prefixes __DIR__ when it loads
                    $map[$fullClass] = substr($f->getPathname(), strlen(dirname($file, 2)) + 1);
                }
            }
        }
    }

    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
    return $map;
}