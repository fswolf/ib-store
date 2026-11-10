<?php
// keys handed out with a digital product: itch.io download key links, steam keys, any code. pasted in on the admin
// product page, one is held for each order line at checkout (like physical stock, so two buyers never get the same
// one), shown to the buyer once the order is paid, and put back when the order is cancelled before it was paid.
// products.keys_left keeps the unused count for the cards and the buy button, null when the product has no keys
class ProductKeysModel {
	// a pasted list, one key per line. returns [added, skipped], a key already in this product's pool is skipped
	public static function add($product_id, $text) {
		$keys = array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), 'strlen'));
		if (!$keys) throw new Exception('Paste at least one key');
		foreach ($keys as $key) {
			if (mb_strlen($key) > 255) throw new Exception('A key is longer than 255 characters: ' . mb_substr($key, 0, 40) . '...');
		}

		$db = new DatabaseLibrary();
		$insert = $db->pdo->prepare('INSERT IGNORE INTO `product_keys` (product_id, key_value) VALUES (:product_id, :key_value)');
		$added = 0;
		foreach ($keys as $key) {
			$insert->execute(array(':product_id' => (int) $product_id, ':key_value' => $key));
			$added += $insert->rowCount();
		}
		self::recount($db->pdo, $product_id);
		return array($added, count($keys) - $added);
	}

	// holds one unused key for an order line. runs on checkout's own connection so it rolls back with the order.
	// false when none are left
	public static function claim($pdo, $product_id, $order_item_id) {
		$update = $pdo->prepare('UPDATE `product_keys` SET order_item_id = :order_item_id WHERE product_id = :product_id AND order_item_id IS NULL ORDER BY id LIMIT 1');
		$update->bindValue(':order_item_id', (int) $order_item_id, PDO::PARAM_INT);
		$update->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$update->execute();
		if ($update->rowCount() !== 1) return false;

		self::recount($pdo, $product_id);
		return true;
	}

	// an order cancelled before it was paid hands its keys back to the pool
	public static function release($order_id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT DISTINCT k.product_id FROM `product_keys` k JOIN `order_items` i ON i.id = k.order_item_id WHERE i.order_id = :order_id');
		$select->bindValue(':order_id', (int) $order_id, PDO::PARAM_INT);
		$select->execute();
		$products = $select->fetchAll(PDO::FETCH_COLUMN);

		$update = $db->pdo->prepare('UPDATE `product_keys` k JOIN `order_items` i ON i.id = k.order_item_id SET k.order_item_id = NULL WHERE i.order_id = :order_id');
		$update->bindValue(':order_id', (int) $order_id, PDO::PARAM_INT);
		$update->execute();

		foreach ($products as $product_id) self::recount($db->pdo, $product_id);
	}

	// an order coming back from cancelled: each digital line of a product with keys that holds none gets one again.
	// all or nothing, throws when a product ran out so the order stays cancelled
	public static function reclaim($order_id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('
			SELECT i.id, i.product_id, i.name
			FROM `order_items` i
			JOIN `products` p ON p.id = i.product_id
			LEFT JOIN `product_keys` k ON k.order_item_id = i.id
			WHERE i.order_id = :order_id AND i.digital = 1 AND p.keys_left IS NOT NULL AND k.id IS NULL');
		$select->bindValue(':order_id', (int) $order_id, PDO::PARAM_INT);
		$select->execute();
		$lines = $select->fetchAll();
		if (!$lines) return;

		$db->pdo->beginTransaction();
		try {
			foreach ($lines as $line) {
				if (!self::claim($db->pdo, $line['product_id'], $line['id'])) throw new Exception($line['name'] . ' has no keys left, add more before taking this order out of cancelled');
			}
			$db->pdo->commit();
		}
		catch (Throwable $e) {
			$db->pdo->rollBack();
			throw $e;
		}
	}

	// the keys held for these order lines, as order_item_id => [key, ...]
	public static function forItems($order_item_ids) {
		$ids = array_values(array_filter(array_map('intval', $order_item_ids)));
		if (!$ids) return array();

		$db = new DatabaseLibrary();
		$marks = implode(',', array_fill(0, count($ids), '?'));
		$select = $db->pdo->prepare("SELECT order_item_id, key_value FROM `product_keys` WHERE order_item_id IN ($marks) ORDER BY id");
		$select->execute($ids);

		$out = array();
		foreach ($select->fetchAll() as $row) $out[$row['order_item_id']][] = $row['key_value'];
		return $out;
	}

	// for the admin product page: [unused, total]
	public static function counts($product_id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT COALESCE(SUM(order_item_id IS NULL), 0) AS unused, COUNT(*) AS total FROM `product_keys` WHERE product_id = :product_id');
		$select->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$select->execute();
		$row = $select->fetch();
		return array((int) $row['unused'], (int) $row['total']);
	}

	// takes the unused keys out (a wrong paste, or to stop selling keys). keys already on orders stay with them, and
	// the product goes back to no keys at all (keys_left null), so it sells again on its download files alone
	public static function clearUnused($product_id) {
		$db = new DatabaseLibrary();
		$delete = $db->pdo->prepare('DELETE FROM `product_keys` WHERE product_id = :product_id AND order_item_id IS NULL');
		$delete->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$delete->execute();

		$update = $db->pdo->prepare('UPDATE `products` SET keys_left = NULL WHERE id = :id');
		$update->bindValue(':id', (int) $product_id, PDO::PARAM_INT);
		$update->execute();
		return $delete->rowCount();
	}

	// products.keys_left from the pool: null when the product never had keys, otherwise how many are unused.
	// a product whose keys are all on orders keeps 0, so it shows sold out until more are pasted in
	private static function recount($pdo, $product_id) {
		$update = $pdo->prepare('
			UPDATE `products` SET keys_left = (
				SELECT IF(COUNT(*) = 0, NULL, SUM(order_item_id IS NULL)) FROM `product_keys` WHERE product_id = :product_id
			) WHERE id = :id');
		$update->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$update->bindValue(':id', (int) $product_id, PDO::PARAM_INT);
		$update->execute();
	}
}
