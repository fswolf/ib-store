<?php
// one model for account_billing and account_shipping, the two tables have the same columns now
// $type picks the table: 'billing' or 'shipping'
class AddressesModel {
	public $id;
	public $account_id;
	public $first_name;
	public $last_name;
	public $company;
	public $email;
	public $phone;
	public $address_1;
	public $address_2;
	public $city;
	public $province;
	public $postal_code;
	public $country;
	public $is_default;
	public $created;
	public $updated;

	public $type = 'billing';

	// the columns a form is allowed to fill
	const FIELDS = array('first_name', 'last_name', 'company', 'email', 'phone', 'address_1', 'address_2', 'city', 'province', 'postal_code', 'country');
	const REQUIRED = array('first_name', 'last_name', 'address_1', 'city', 'province', 'postal_code', 'country');

	public function __construct($properties = [], $type = 'billing') {
		foreach($properties as $key => $value) $this->{$key} = $value;
		$this->type = $type;
	}

	private static function table($type) {
		return $type === 'shipping' ? 'account_shipping' : 'account_billing';
	}

	// fill from posted data, trims and drops anything not in FIELDS. throws on a missing required field
	public function fill($data) {
		if (!is_object($data)) $data = new stdClass();
		foreach (self::FIELDS as $field) {
			$value = isset($data->{$field}) ? trim((string) $data->{$field}) : '';
			if (in_array($field, self::REQUIRED) && $value === '') throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
			if (mb_strlen($value) > 255) throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is too long');
			$this->{$field} = $value === '' ? null : $value;
		}
		return $this;
	}

	public static function getDefault($account_id, $type = 'billing') {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `' . self::table($type) . '` WHERE account_id = :account_id ORDER BY is_default DESC, updated DESC LIMIT 1');
		$select->bindParam(':account_id', $account_id, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();

		if ($record) return new AddressesModel($record, $type);
		return false;
	}

	// insert or update the account's default address of this type
	public function save() {
		$db = new DatabaseLibrary();
		$table = self::table($this->type);

		$current = self::getDefault($this->account_id, $this->type);

		if ($current) {
			$this->id = $current->id;
			$sql = "UPDATE `$table` SET ";
			foreach (self::FIELDS as $field) $sql .= "`$field` = :$field, ";
			$sql .= '`is_default` = 1 WHERE `id` = :id';
			$query = $db->pdo->prepare($sql);
			$query->bindParam(':id', $this->id, PDO::PARAM_INT);
		}
		else {
			$sql = "INSERT INTO `$table` (`account_id`, `is_default`, `" . implode('`, `', self::FIELDS) . "`) VALUES (:account_id, 1, :" . implode(', :', self::FIELDS) . ")";
			$query = $db->pdo->prepare($sql);
			$query->bindParam(':account_id', $this->account_id, PDO::PARAM_INT);
		}

		foreach (self::FIELDS as $field) $query->bindValue(":$field", $this->{$field}, $this->{$field} === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
		$query->execute();

		if (!$current) $this->id = $db->pdo->lastInsertId();
		return $this->id;
	}

	// what the api hands to the browser
	public function toArray() {
		$out = array();
		foreach (self::FIELDS as $field) $out[$field] = $this->{$field} ?? '';
		return $out;
	}
}
