<?php

class SessionLibrary {
	public function __construct() {
		if (session_status() == PHP_SESSION_NONE) {
			// lock the session cookie down before it gets sent. secure only when we are actually on https
			// so the local php -S test server still works
			session_set_cookie_params([
				'lifetime' => 0,
				'path'     => '/',
				'secure'   => is_https(),
				'httponly' => true,
				'samesite' => 'Lax'
			]);
			session_start();
		}
	}

	public function set($key, $value) {
		$_SESSION[$key] = $value;
	}

	public function get($key) {
		if (array_key_exists($key, $_SESSION)) return $_SESSION[$key];
		else return false;
	}

	// drop one key
	public function remove($key) {
		unset($_SESSION[$key]);
	}

	// fresh id, data kept (after sign in, so a session id handed out before login is worthless)
	public function regenerate() {
		session_regenerate_id(true);
	}

	// wipe everything and hand out a fresh id (sign out)
	public function destroy() {
		$_SESSION = array();
		session_regenerate_id(true);
	}
}
