<?php

class ProductImgModel {
	public $id;
	public $product_id;
	public $image;
	public $sort_order;

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	// every image for one product, main one (sort_order 1) first
	public static function getAll($product_id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `product_images` WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC');
		$select->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) $output[] = new ProductImgModel($record);
		return $output;
	}

	public static function getMain($product_id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `product_images` WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC LIMIT 1');
		$select->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();
		if ($record) return new ProductImgModel($record);
		return false;
	}

	public static function get($id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `product_images` WHERE id = :id');
		$select->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();
		if ($record) return new ProductImgModel($record);
		return false;
	}

	// -- Admin -- //
	// new image goes on the end of the list
	public static function add($product_id, $image) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `product_images` WHERE product_id = :product_id');
		$select->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$select->execute();
		$sort_order = (int) $select->fetchColumn();

		$insert = $db->pdo->prepare('INSERT INTO `product_images` (product_id, image, sort_order) VALUES (:product_id, :image, :sort_order)');
		$insert->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$insert->bindValue(':image', $image, PDO::PARAM_STR);
		$insert->bindValue(':sort_order', $sort_order, PDO::PARAM_INT);
		$insert->execute();
		return self::get($db->pdo->lastInsertId());
	}

	// drop a row and renumber the rest so sort_order 1 is always the main image
	public static function delete($id) {
		$image = self::get($id);
		if (!$image) return false;

		$db = new DatabaseLibrary();
		$delete = $db->pdo->prepare('DELETE FROM `product_images` WHERE id = :id');
		$delete->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$delete->execute();

		self::renumber($image->product_id);
		return $image;
	}

	// make this one the main image
	public static function setMain($id) {
		$image = self::get($id);
		if (!$image) return false;

		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare('UPDATE `product_images` SET sort_order = 0 WHERE id = :id');
		$update->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$update->execute();

		self::renumber($image->product_id);
		return true;
	}

	private static function renumber($product_id) {
		$db = new DatabaseLibrary();
		$n = 1;
		foreach (self::getAll($product_id) as $image) {
			$update = $db->pdo->prepare('UPDATE `product_images` SET sort_order = :sort_order WHERE id = :id');
			$update->bindValue(':sort_order', $n++, PDO::PARAM_INT);
			$update->bindValue(':id', (int) $image->id, PDO::PARAM_INT);
			$update->execute();
		}
	}
}
