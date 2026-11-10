<?php
class ProductsModel {
	public $id; 	
	public $category_id; 	
	public $sku;
	public $name;
	public $image;
	public $slug;
	public $con;
	public $description; 	
	public $price; 	
	public $stock; 	
	public $digital; 	
	public $download_url; 	
	public $keys_left; // unused keys in product_keys, null when the product has none (see ProductKeysModel)
	public $active; 	
	public $newest; 	
	public $featured; 	
	public $badge; 	
	public $created_at;	
	public $updated_at;

	// joined in by getBySlug / getProduct for the product page
	public $category_name;
	public $category_route;

	// -- Admin forms -- //
	// the columns a form is allowed to fill
	const FIELDS = array('category_id', 'sku', 'name', 'slug', 'con', 'description', 'price', 'stock', 'digital', 'download_url', 'active', 'newest', 'featured', 'badge');

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	public static function getNewest($display = 8) {
		$db = new DatabaseLibrary();
	    $select = $db->pdo->prepare("
	        SELECT
	            p.*,
	            pi.image
	        FROM products p
	        LEFT JOIN product_images pi
	            ON pi.product_id = p.id
	            AND pi.sort_order = 1
	        WHERE p.newest = 1 AND p.active = 1
	        LIMIT :display");

		$select->bindParam(':display', $display, PDO::PARAM_INT);
		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) $output[] = new ProductsModel($record);
		return $output;
	}

	public static function getFeatured($display = 8) {
		$db = new DatabaseLibrary();
	    $select = $db->pdo->prepare("
	        SELECT
	            p.*,
	            pi.image
	        FROM products p
	        LEFT JOIN product_images pi
	            ON pi.product_id = p.id
	            AND pi.sort_order = 1
	        WHERE p.featured = 1 AND p.active = 1
	        LIMIT :display");

		$select->bindParam(':display', $display, PDO::PARAM_INT);
		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) $output[] = new ProductsModel($record);
		return $output;
	}


	public static function getAll() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare("
		    SELECT
		        p.*,
		        pi.image
		    FROM products p
		    LEFT JOIN product_images pi
		        ON pi.product_id = p.id
		        AND pi.sort_order = 1
		    WHERE p.active = 1
		    ORDER BY p.name ASC
		");

		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) $output[] = new ProductsModel($record);
		return $output;
	}

	public static function getProduct($id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('
			SELECT p.*, c.name AS category_name, c.route AS category_route
			FROM `products` p
			LEFT JOIN categories c ON c.id = p.category_id
			WHERE p.`id` = :id');
		$select->bindParam(':id', $id, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();
		if($record) return new ProductsModel($record);
		return false;
	}

	// product page lookup, slug is what the cards link to. a bare number still works for old links
	public static function getBySlug($slug) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('
			SELECT p.*, c.name AS category_name, c.route AS category_route
			FROM `products` p
			LEFT JOIN categories c ON c.id = p.category_id
			WHERE p.`slug` = :slug');
		$select->bindParam(':slug', $slug, PDO::PARAM_STR);
		$select->execute();
		$record = $select->fetch();
		if($record) return new ProductsModel($record);
		if (ctype_digit((string) $slug)) return self::getProduct((int) $slug); // old /item/<id> links
		return false;
	}

	// other active items in the same category, for the carousel under a product
	public static function getRelated($product, $display = 8) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare("
	        SELECT
	            p.*,
	            pi.image
	        FROM products p
	        LEFT JOIN product_images pi
	            ON pi.product_id = p.id
	            AND pi.sort_order = 1
	        WHERE p.category_id = :category_id AND p.id != :id AND p.active = 1
	        ORDER BY p.featured DESC, p.created_at DESC
	        LIMIT :display");

		$select->bindValue(':category_id', (int) $product->category_id, PDO::PARAM_INT);
		$select->bindValue(':id', (int) $product->id, PDO::PARAM_INT);
		$select->bindValue(':display', (int) $display, PDO::PARAM_INT);
		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) $output[] = new ProductsModel($record);
		return $output;
	}

	// the cart keeps ids and quantities only, this pulls the rest in one query
	public static function getMany($ids) {
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if (!$ids) return array();

		$db = new DatabaseLibrary();
		$marks = implode(',', array_fill(0, count($ids), '?'));
		$select = $db->pdo->prepare("
	        SELECT
	            p.*,
	            pi.image
	        FROM products p
	        LEFT JOIN product_images pi
	            ON pi.product_id = p.id
	            AND pi.sort_order = 1
	        WHERE p.id IN ($marks)");
		$select->execute($ids);
		$result = $select->fetchAll();

		// keyed by id so the cart can look them up
		$output = array();
		foreach($result as $record) $output[$record['id']] = new ProductsModel($record);
		return $output;
	}

	// take stock off, never below zero (checkout does this inside its own transaction, see OrdersModel::create)
	public static function reduceStock($id, $qty) {
		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare('UPDATE `products` SET stock = GREATEST(stock - :qty, 0) WHERE id = :id');
		$update->bindValue(':qty', (int) $qty, PDO::PARAM_INT);
		$update->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$update->execute();
	}

	// -- Admin -- //
	// fill from posted data, checks the lot and throws on the first problem
	public function fill($data) {
		$name = trim((string) ($data->name ?? ''));
		if ($name === '' || mb_strlen($name) > 255) throw new Exception('Name is required');

		$slug = strtolower(trim((string) ($data->slug ?? '')));
		if ($slug === '') $slug = $name;
		$slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)), '-');
		if ($slug === '') throw new Exception('Slug is required');

		$category_id = (int) ($data->category_id ?? 0);
		if ($category_id < 1) throw new Exception('Pick a category');

		$price = (string) ($data->price ?? '0');
		if (!is_numeric($price) || $price < 0) throw new Exception('Price must be a number');

		$this->category_id = $category_id;
		$this->sku = trim((string) ($data->sku ?? '')) ?: null;
		$this->name = $name;
		$this->slug = $slug;
		$con = (int) ($data->con ?? 0);
		$this->con = in_array($con, array(0, 1, 2)) ? $con : 0;
		if (!empty($data->digital)) $this->con = 0; // a download is always new, the form disables the field too
		$this->description = trim((string) ($data->description ?? '')) ?: null;
		$this->price = number_format((float) $price, 2, '.', '');
		$this->stock = max(0, (int) ($data->stock ?? 0));
		$this->digital = !empty($data->digital) ? 1 : 0;
		$this->download_url = trim((string) ($data->download_url ?? '')) ?: null;
		$this->active = !empty($data->active) ? 1 : 0;
		$this->newest = !empty($data->newest) ? 1 : 0;
		$this->featured = !empty($data->featured) ? 1 : 0;
		$badge = (int) ($data->badge ?? 0);
		$this->badge = in_array($badge, array(0, 1, 2, 3, 4)) ? $badge : 0;
		return $this;
	}

	// what the public product api hands out, the fields a product card reads. never the whole model:
	// download_url is the paid file, and the admin columns are nobody else's business
	public function toArray() {
		return array(
			'id' => (int) $this->id,
			'name' => $this->name,
			'slug' => $this->slug,
			'image' => $this->image,
			'price' => $this->price,
			'stock' => (int) $this->stock,
			'digital' => (int) $this->digital,
			'keys_left' => $this->keys_left === null ? null : (int) $this->keys_left,
			'badge' => (int) $this->badge,
		);
	}

	// can it go in a cart: in stock and not tagged coming soon. digital is always in stock, unless it hands out keys
	// and they ran out. the badge numbers: 0 none, 1 new, 2 sale, 3 discontinued, 4 coming soon
	public function buyable() {
		return (int) $this->badge !== 4 && ($this->digital ? !$this->soldOut() : $this->stock > 0);
	}

	// a digital product with keys and none left
	public function soldOut() {
		return $this->keys_left !== null && (int) $this->keys_left < 1;
	}

	// insert when there is no id yet, update otherwise. slug and sku are unique in the table so the
	// duplicate key error gets turned into a readable message
	public function save() {
		$db = new DatabaseLibrary();

		if ($this->id) {
			$sql = 'UPDATE `products` SET ';
			foreach (self::FIELDS as $field) $sql .= "`$field` = :$field, ";
			$sql = rtrim($sql, ', ') . ' WHERE `id` = :id';
		}
		else {
			$sql = 'INSERT INTO `products` (`' . implode('`, `', self::FIELDS) . '`) VALUES (:' . implode(', :', self::FIELDS) . ')';
		}

		$query = $db->pdo->prepare($sql);
		if ($this->id) $query->bindValue(':id', (int) $this->id, PDO::PARAM_INT);
		foreach (self::FIELDS as $field) {
			$value = $this->{$field};
			if ($value === null) $query->bindValue(":$field", null, PDO::PARAM_NULL);
			elseif (in_array($field, array('category_id', 'con', 'stock', 'digital', 'active', 'newest', 'featured', 'badge'))) $query->bindValue(":$field", (int) $value, PDO::PARAM_INT);
			else $query->bindValue(":$field", (string) $value, PDO::PARAM_STR);
		}

		try {
			$query->execute();
		}
		catch (PDOException $e) {
			if ($e->getCode() == 23000) throw new Exception('That slug or SKU is already used by another product');
			throw $e;
		}

		if (!$this->id) $this->id = $db->pdo->lastInsertId();
		return $this->id;
	}

	public static function delete($id) {
		$db = new DatabaseLibrary();
		// images go with it (ON DELETE CASCADE in the db), order_items keep their copy of the name and sku, and the
		// keys already on orders stay so buyers keep seeing them. the unused ones go
		$keys = $db->pdo->prepare('DELETE FROM `product_keys` WHERE product_id = :id AND order_item_id IS NULL');
		$keys->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$keys->execute();

		$unlink = $db->pdo->prepare('UPDATE `order_items` SET product_id = NULL WHERE product_id = :id');
		$unlink->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$unlink->execute();

		$delete = $db->pdo->prepare('DELETE FROM `products` WHERE id = :id');
		$delete->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$delete->execute();
		return $delete->rowCount();
	}

	// admin table, same shape tablesorter.js posts: rows, offset, sort, order, input
	public static function count($search = '') {
		$db = new DatabaseLibrary();
		if ($search === '') {
			$select = $db->pdo->prepare('SELECT COUNT(*) FROM `products`');
			$select->execute();
		}
		else {
			$select = $db->pdo->prepare('SELECT COUNT(*) FROM `products` WHERE name LIKE :t1 OR sku LIKE :t2 OR slug LIKE :t3');
			$term = '%' . addcslashes($search, '%_\\') . '%';
			$select->execute([':t1' => $term, ':t2' => $term, ':t3' => $term]);
		}
		return (int) $select->fetchColumn();
	}

	public static function getPage($rows, $offset, $sort = 'id', $order = 'desc', $search = '') {
		$db = new DatabaseLibrary();

		// sort column comes from the browser, whitelist it (qualified, categories has id and name too)
		$columns = array('id' => 'p.id', 'sku' => 'p.sku', 'name' => 'p.name', 'price' => 'p.price', 'stock' => 'p.stock', 'active' => 'p.active', 'category_name' => 'c.name', 'updated_at' => 'p.updated_at');
		$sort = $columns[$sort] ?? 'p.id';
		$order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

		$where = '';
		$params = array();
		if ($search !== '') {
			$where = 'WHERE p.name LIKE :t1 OR p.sku LIKE :t2 OR p.slug LIKE :t3';
			$term = '%' . addcslashes($search, '%_\\') . '%';
			$params = [':t1' => $term, ':t2' => $term, ':t3' => $term];
		}

		$select = $db->pdo->prepare("
			SELECT p.*, pi.image, c.name AS category_name, c.route AS category_route
			FROM `products` p
			LEFT JOIN categories c ON c.id = p.category_id
			LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.sort_order = 1
			$where
			ORDER BY $sort $order
			LIMIT :rows OFFSET :offset");

		foreach ($params as $key => $value) $select->bindValue($key, $value, PDO::PARAM_STR);
		$select->bindValue(':rows', (int) $rows, PDO::PARAM_INT);
		$select->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
		$select->execute();
		$result = $select->fetchAll();

		$output = array();
		foreach($result as $record) $output[] = new ProductsModel($record);
		return $output;
	}

	public static function getCategory($route) {
	    $db = new DatabaseLibrary();

	    $select = $db->pdo->prepare("
	        SELECT
	            p.*,
	            pi.image
	        FROM products p
	        INNER JOIN categories c
	            ON p.category_id = c.id
	        LEFT JOIN product_images pi
	            ON pi.product_id = p.id
	            AND pi.sort_order = 1
	        WHERE c.route = :route AND p.active = 1
	        ORDER BY p.name ASC");

	    $select->bindParam(':route', $route, PDO::PARAM_STR);
	    $select->execute();
	    $result = $select->fetchAll();

	    $output = array();
	    foreach ($result as $record) $output[] = new ProductsModel($record);

	    return $output;
	}

	public static function search($item) {
	    $db = new DatabaseLibrary();

	    $select = $db->pdo->prepare("
	        SELECT
	            p.*,
	            pi.image
	        FROM products p
	        LEFT JOIN product_images pi
	            ON pi.product_id = p.id
	            AND pi.sort_order = 1
	        WHERE p.active = 1
	            AND (p.name LIKE :t1 OR p.sku LIKE :t2 OR p.description LIKE :t3)
	        ORDER BY p.name ASC");

	    // native prepares (EMULATE_PREPARES=false) can't reuse a named placeholder
	    $term = '%' . addcslashes($item, '%_\\') . '%';
	    $select->execute([':t1' => $term, ':t2' => $term, ':t3' => $term]);
	    $result = $select->fetchAll();

	    $output = array();
	    foreach ($result as $record) $output[] = new ProductsModel($record);

	    return $output;
	}
}

?>