<?php

class DatabaseLibrary {
	public $pdo;

	public function __construct() {
		$connectionStr = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
		$options = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		];
		$this->pdo = new PDO($connectionStr, DB_USER, DB_PASSWORD, $options);
	}
}

/* not used by the store and SL_DB_* are not in config.php, left over from another project
class SlDatabaseLibrary {
	public $pdo;

	public function __construct() {
		$connectionStr = 'mysql:host=' . DB_HOST . ';dbname=' . SL_DB_NAME . ';charset=utf8mb4';
		$options = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		];
		$this->pdo = new PDO($connectionStr, SL_DB_USER, SL_DB_PASSWORD, $options);
	}
}
*/

?>