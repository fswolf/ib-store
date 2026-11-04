<?php
class AccountsModel {
	public $id;
	public $registered;
	public $username;
	public $email;
	public $password;
	public $is_admin;
	public $verified;
	public $notes;
	// these two are not columns in ib_store, left over from the other project
	//public $access;
	//public $allow_adult;

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	// -- Password -- //
	// argon2id when php has it, sha512 fallback keeps the old rows working
	public static function hashPassword($password) {
		if(defined('PASSWORD_ARGON2ID')) return password_hash($password, PASSWORD_ARGON2ID);
		return hash('sha512', $password);
	}

	public function checkPassword($password) {
		if(defined('PASSWORD_ARGON2ID') && str_starts_with($this->password, '$')) return password_verify($password, $this->password);
		return strtolower($this->password) === hash('sha512', $password);
	}

	// -- Rules -- //
	// the same checks for register, settings and admin. each returns the cleaned value or throws a message for the user.
	// $except is the account being edited, so its own username / email do not count as taken
	public static function checkUsername($username, $except = null) {
		$username = trim((string) $username);
		if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) throw new Exception('Username is 3 to 32 letters, numbers, dots, dashes or underscores');
		$other = self::getAccountByUsername($username);
		if ($other && (!$except || $other->id != $except->id)) throw new Exception('That username is taken');
		return $username;
	}

	public static function checkEmail($email, $except = null) {
		$email = filter_var(trim((string) $email), FILTER_SANITIZE_EMAIL);
		if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) throw new Exception('Enter a valid email address');
		$other = self::getAccountByEmail($email);
		if ($other && (!$except || $other->id != $except->id)) throw new Exception('That email is already registered');
		return $email;
	}

	public static function checkNewPassword($password, $confirm) {
		$password = (string) $password;
		if (strlen($password) < 8) throw new Exception('Password needs at least 8 characters');
		if ($password !== (string) $confirm) throw new Exception('Passwords do not match');
		return $password;
	}

	public function save() {
		$db = new DatabaseLibrary();

		$update = $db->pdo->prepare('
			UPDATE accounts
			SET
				`username` = :username,
				`email` = :email,
				`password` = :password
			WHERE
				`id` = :id
		');

		$update->bindParam(':username', $this->username, PDO::PARAM_STR);
		$update->bindParam(':email', $this->email, PDO::PARAM_STR);
		$update->bindParam(':password', $this->password, PDO::PARAM_STR);
		$update->bindParam(':id', $this->id, PDO::PARAM_INT);
		$update->execute();
		return $update->rowCount();
	}

	// admin side edit, flags included
	public function saveAdmin() {
		$db = new DatabaseLibrary();

		$update = $db->pdo->prepare('
			UPDATE accounts
			SET
				`username` = :username,
				`email` = :email,
				`is_admin` = :is_admin,
				`verified` = :verified,
				`notes` = :notes
			WHERE
				`id` = :id
		');

		$update->bindParam(':username', $this->username, PDO::PARAM_STR);
		$update->bindParam(':email', $this->email, PDO::PARAM_STR);
		$update->bindParam(':is_admin', $this->is_admin, PDO::PARAM_INT);
		$update->bindParam(':verified', $this->verified, PDO::PARAM_INT);
		$update->bindParam(':notes', $this->notes, PDO::PARAM_STR);
		$update->bindParam(':id', $this->id, PDO::PARAM_INT);
		$update->execute();
		return $update->rowCount();
	}

	public static function register($username, $email, $password) {
		$username = self::checkUsername($username);
		$email = self::checkEmail($email);

		$db = new DatabaseLibrary();
		$insert = $db->pdo->prepare('
			INSERT INTO accounts
				(username, email, password)
			VALUES
				(:username, :email, :password)
		');

		$hash = self::hashPassword($password);
		$insert->bindParam(':username', $username, PDO::PARAM_STR);
		$insert->bindParam(':email', $email, PDO::PARAM_STR);
		$insert->bindParam(':password', $hash, PDO::PARAM_STR);
		try {
			$insert->execute();
		}
		catch (PDOException $e) {
			// two signups racing for the same name hit the unique key, see ib_store_update.sql
			if ($e->getCode() == 23000) throw new Exception('That username or email is already registered');
			throw $e;
		}

		return self::getAccount($db->pdo->lastInsertId());
	}

	public static function getAccount($id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `accounts` WHERE id = :id');
		$select->bindParam(':id', $id, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();

		if ($record) return new AccountsModel($record);
		return false;
	}

	public static function getAccountByEmail($email) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `accounts` WHERE email=:email');
		$select->bindParam(':email', $email, PDO::PARAM_STR);
		$select->execute();
		$record = $select->fetch();

		if ($record) return new AccountsModel($record);
		return false;
	}

	public static function getAccountByUsername($username) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `accounts` WHERE username=:username');
		$select->bindParam(':username', $username, PDO::PARAM_STR);
		$select->execute();
		$record = $select->fetch();

		if ($record) return new AccountsModel($record);
		return false;
	}

	// -- Admin lists -- //
	// shape matches what tablesorter.js posts: rows, offset, sort, order, input
	public static function count($search = '') {
		$db = new DatabaseLibrary();
		if ($search === '') {
			$select = $db->pdo->prepare('SELECT COUNT(*) FROM `accounts`');
			$select->execute();
		}
		else {
			$select = $db->pdo->prepare('SELECT COUNT(*) FROM `accounts` WHERE username LIKE :t1 OR email LIKE :t2');
			$term = '%' . addcslashes($search, '%_\\') . '%';
			$select->execute([':t1' => $term, ':t2' => $term]);
		}
		return (int) $select->fetchColumn();
	}

	public static function getPage($rows, $offset, $sort = 'id', $order = 'desc', $search = '') {
		$db = new DatabaseLibrary();

		// sort column comes from the browser, whitelist it
		$columns = array('id', 'registered', 'username', 'email', 'is_admin', 'verified');
		if (!in_array($sort, $columns)) $sort = 'id';
		$order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

		$where = '';
		$params = array();
		if ($search !== '') {
			$where = 'WHERE username LIKE :t1 OR email LIKE :t2';
			$term = '%' . addcslashes($search, '%_\\') . '%';
			$params = [':t1' => $term, ':t2' => $term];
		}

		$select = $db->pdo->prepare("
			SELECT id, registered, username, email, is_admin, verified, notes
			FROM `accounts`
			$where
			ORDER BY `$sort` $order
			LIMIT :rows OFFSET :offset");

		foreach ($params as $key => $value) $select->bindValue($key, $value, PDO::PARAM_STR);
		$select->bindValue(':rows', (int) $rows, PDO::PARAM_INT);
		$select->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
		$select->execute();
		$result = $select->fetchAll();

		$output = array();
		foreach($result as $record) $output[] = new AccountsModel($record);
		return $output;
	}

	public static function login($email, $password, $remember = false) {
		$db = new DatabaseLibrary();

		$select = $db->pdo->prepare('SELECT * FROM accounts WHERE email = ?');
		$select->execute([$email]);
		$record = $select->fetch();

		if (!$record) throw new Exception('Invalid login credentials');

		// Hash the password and compare it to what's stored in the database.
		$user = new AccountsModel($record);

		if (!$user->checkPassword($password)) {
			throw new Exception('Invalid login credentials');
		}

		/* folded into checkPassword() so register/reset share it
		if(defined('PASSWORD_ARGON2ID')) {
			if (!password_verify($password, $user->password)) {
				throw new Exception('Invalid login credentials');
			}
		}
		else {
			if (strtolower($user->password) !== hash('sha512', $password)) {
				throw new Exception('Invalid login credentials');
			}
		}
		*/

		// Valid login -- store a session id and hash to validate the user going forward
		$sessionId = md5(uniqid());
		$sessionHash = md5(uniqid());

		$ip_address = Flight::request()->ip;

		$upsert = $db->pdo->prepare('
			INSERT INTO login_sessions
				(ip_address, user_id, session_id, session_hash)
			VALUES
				(:ip_address, :user_id, :session_id, :session_hash)
			ON DUPLICATE KEY UPDATE session_id = :session_id2, session_hash = :session_hash2, ip_address = :ip_address2;
		');

		$upsert->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
		$upsert->bindParam(':user_id', $user->id, PDO::PARAM_INT);
		$upsert->bindParam(':session_id', $sessionId, PDO::PARAM_STR);
		$upsert->bindParam(':session_hash', $sessionHash, PDO::PARAM_STR);
		$upsert->bindParam(':session_id2', $sessionId, PDO::PARAM_STR);
		$upsert->bindParam(':session_hash2', $sessionHash, PDO::PARAM_STR);
		$upsert->bindParam(':ip_address2', $ip_address, PDO::PARAM_STR);

		$upsert->execute();
		if ($upsert->rowCount()) {
			// Store the session id and hash locally
			$loginSessionInfo = array('sessionId' => $sessionId, 'sessionHash' => $sessionHash, 'sessionCreated' => time());

			$session = new SessionLibrary();
			$session->regenerate();
			$session->set('loginSessionInfo', $loginSessionInfo);

			/* remember me cookie got deprecated, signin.js keeps the email in localStorage instead
			// Set a cookie if "remember me" was checked
			if ($remember) {
				setcookie('__Host-loginSessionInfo', serialize($loginSessionInfo), [
				    'expires'  => time() + 60 * 60 * 24 * 30,
				    'path'     => '/',
				    'secure'   => true,
				    'httponly' => true,
				    'samesite' => 'Lax'
				]);
			}
			*/

			// Finally, return the user object
			return $user;
		}
	}

	// kill the db row and the session, sign out
	public static function logout() {
		$session = new SessionLibrary();
		$loginSessionInfo = $session->get('loginSessionInfo');

		if ($loginSessionInfo && !empty($loginSessionInfo['sessionId'])) {
			$db = new DatabaseLibrary();
			$delete = $db->pdo->prepare('DELETE FROM `login_sessions` WHERE session_id = :session_id');
			$delete->bindParam(':session_id', $loginSessionInfo['sessionId'], PDO::PARAM_STR);
			$delete->execute();
		}

		$session->destroy();
	}

	public static function resume() {
	    $session = new SessionLibrary();

	    // Try session first
	    $loginSessionInfo = $session->get('loginSessionInfo');

	    /* remember me cookie got deprecated (see login)
	    // Fallback to secure JSON cookie
	    if (!$loginSessionInfo && isset($_COOKIE['loginSessionInfo'])) {
	        $decoded = json_decode($_COOKIE['loginSessionInfo'], true);

	        if (is_array($decoded)) $loginSessionInfo = $decoded;
	    }
	    */

	    // No session info available
	    if (!$loginSessionInfo) return false;

	    // Basic structure validation (prevents garbage cookies hitting DB)
	    if (empty($loginSessionInfo['sessionId']) || empty($loginSessionInfo['sessionHash'])) {
	        return false;
	    }

	    // Manual expiration check
	    if (defined('CHECK_SESSION_EXPIRED') && CHECK_SESSION_EXPIRED) {
	        if (isset($loginSessionInfo['sessionCreated'])) {
	            if ((time() - $loginSessionInfo['sessionCreated']) >= SESSION_LIMIT) {

	                // Clear server session
	                $session->set('loginSessionInfo', null);

	                /* remember me cookie got deprecated
	                // Properly delete modern cookie
	                setcookie('__Host-loginSessionInfo', '', [
	                    'expires'  => time() - 3600,
	                    'path'     => '/',
	                    'secure'   => true,
	                    'httponly' => true,
	                    'samesite' => 'Lax'
	                ]);
	                */

	                return false;
	            }
	        }
	    }

	    // Lookup session in DB
	    $db = new DatabaseLibrary();
	    $select = $db->pdo->prepare('
	        SELECT accounts.*
	        FROM `login_sessions`
	        JOIN accounts ON `login_sessions`.`user_id` = `accounts`.`id`
	        WHERE
	            `session_id` = :session_id AND `session_hash` = :session_hash
	    ');

	    $select->bindParam(':session_id', $loginSessionInfo['sessionId'], PDO::PARAM_STR);
	    $select->bindParam(':session_hash', $loginSessionInfo['sessionHash'], PDO::PARAM_STR);
	    $select->execute();

	    $record = $select->fetch();

	    // Return user model if valid
	    if ($record) return new AccountsModel($record);
	    return false;
	}
}
