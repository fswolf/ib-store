<?php

class CategoriesModel {
	public $id;
	public $name;
	public $route;
	public $description;
	public $active;
	public $created;

	// joined in by getAllAdmin
	public $product_count;

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	// menu list, active only
	public static function getAll() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `categories` WHERE active = 1 ORDER BY name ASC LIMIT 12;');
		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) array_push($output, new CategoriesModel($record));
		return $output;
	}

	public static function get($id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `categories` WHERE id = :id');
		$select->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();
		if ($record) return new CategoriesModel($record);
		return false;
	}

	// -- Admin -- //
	// everything, hidden ones too, with how many products sit in each
	public static function getAllAdmin() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('
			SELECT c.*, COUNT(p.id) AS product_count
			FROM `categories` c
			LEFT JOIN products p ON p.category_id = c.id
			GROUP BY c.id
			ORDER BY c.name ASC');
		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) array_push($output, new CategoriesModel($record));
		return $output;
	}

	public function fill($data) {
		$name = trim((string) ($data->name ?? ''));
		if ($name === '' || mb_strlen($name) > 36) throw new Exception('Name is required (36 characters max)');

		$route = strtolower(trim((string) ($data->route ?? '')));
		if ($route === '') $route = $name;
		$route = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($route)), '-');
		if ($route === '') throw new Exception('Route is required');

		$this->name = $name;
		$this->route = $route;
		$this->description = trim((string) ($data->description ?? '')) ?: null;
		$this->active = !empty($data->active) ? 1 : 0;
		return $this;
	}

	public function save() {
		$db = new DatabaseLibrary();

		if ($this->id) {
			$query = $db->pdo->prepare('UPDATE `categories` SET name = :name, route = :route, description = :description, active = :active WHERE id = :id');
			$query->bindValue(':id', (int) $this->id, PDO::PARAM_INT);
		}
		else {
			$query = $db->pdo->prepare('INSERT INTO `categories` (name, route, description, active) VALUES (:name, :route, :description, :active)');
		}

		$query->bindValue(':name', $this->name, PDO::PARAM_STR);
		$query->bindValue(':route', $this->route, PDO::PARAM_STR);
		$query->bindValue(':description', $this->description, $this->description === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
		$query->bindValue(':active', (int) $this->active, PDO::PARAM_INT);

		try {
			$query->execute();
		}
		catch (PDOException $e) {
			if ($e->getCode() == 23000) throw new Exception('That route is already used by another category');
			throw $e;
		}

		if (!$this->id) $this->id = $db->pdo->lastInsertId();
		return $this->id;
	}

	// products point at categories with a foreign key, so a category with items in it will not delete
	public static function delete($id) {
		$db = new DatabaseLibrary();
		try {
			$delete = $db->pdo->prepare('DELETE FROM `categories` WHERE id = :id');
			$delete->bindValue(':id', (int) $id, PDO::PARAM_INT);
			$delete->execute();
		}
		catch (PDOException $e) {
			if ($e->getCode() == 23000) throw new Exception('Move or delete the products in this category first');
			throw $e;
		}
		return $delete->rowCount();
	}

}

