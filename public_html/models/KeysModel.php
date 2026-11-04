<?php

class KeysModel {
	
	public $username;
	Public $apikey;
	public $purpose; 	
	public $registered; 	
	public $account;
	
	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	public static function searchKey($apikey) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT purpose FROM `api_keys` WHERE apikey = :apikey;');
		$select->bindParam(':apikey', $apikey, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();
		
		if ($record) return new KeysModel($record);
		return false;
	}
}


?>