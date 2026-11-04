<?php
// forgot password tokens. the raw token goes in the email, only its sha256 sits in the db
class PasswordResetsModel {
	public $account_id;
	public $token_hash;
	public $expires;
	public $created;

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	// make a token for the account and return the raw one for the email link
	public static function create($account_id) {
		$token = bin2hex(random_bytes(32));
		$hash = hash('sha256', $token);
		$expires = date('Y-m-d H:i:s', time() + RESET_LIFE);

		$db = new DatabaseLibrary();
		$upsert = $db->pdo->prepare('
			INSERT INTO password_resets
				(account_id, token_hash, expires)
			VALUES
				(:account_id, :token_hash, :expires)
			ON DUPLICATE KEY UPDATE token_hash = :token_hash2, expires = :expires2, created = NOW()
		');
		$upsert->bindValue(':account_id', (int) $account_id, PDO::PARAM_INT);
		$upsert->bindValue(':token_hash', $hash, PDO::PARAM_STR);
		$upsert->bindValue(':expires', $expires, PDO::PARAM_STR);
		$upsert->bindValue(':token_hash2', $hash, PDO::PARAM_STR);
		$upsert->bindValue(':expires2', $expires, PDO::PARAM_STR);
		$upsert->execute();

		return $token;
	}

	// the account a live token belongs to, false when unknown or expired
	public static function check($token) {
		if (!preg_match('/^[a-f0-9]{64}$/', (string) $token)) return false;

		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT account_id FROM `password_resets` WHERE token_hash = :token_hash AND expires > NOW()');
		$select->bindValue(':token_hash', hash('sha256', $token), PDO::PARAM_STR);
		$select->execute();
		$record = $select->fetch();

		if ($record) return AccountsModel::getAccount($record['account_id']);
		return false;
	}

	public static function clear($account_id) {
		$db = new DatabaseLibrary();
		$delete = $db->pdo->prepare('DELETE FROM `password_resets` WHERE account_id = :account_id');
		$delete->bindValue(':account_id', (int) $account_id, PDO::PARAM_INT);
		$delete->execute();
	}
}
