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