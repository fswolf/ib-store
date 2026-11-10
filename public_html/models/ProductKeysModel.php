<?php
// keys handed out with a digital product: itch.io download key links, steam keys, any code. pasted in on the admin
// product page, one is held for each order line at checkout (like physical stock, so two buyers never get the same
// one), shown to the buyer once the order is paid, and put back when the order is cancelled before it was paid.
// products.keys_left keeps the unused count for the cards and the buy button. whether a product hands keys out at
// all is its Digital key box (products.use_keys), so a pool can sit there while it is off
class ProductKeysModel {
	// a pasted list and / or a text file, one key per line. returns [added, listed twice, already in the pool],
	// both kinds of repeat are skipped
	public static function add($product_id, $text) {
		$text = str_replace("\xEF\xBB\xBF", '', (string) $text); // the mark some editors put at the start of a utf-8 file
		$lines = array_filter(array_map('trim', preg_split('/\R/', $text)), 'strlen');
		$keys = array_unique($lines);
		if (!$keys) throw new Exception('Paste some keys or pick a file');
		foreach ($keys as $key) {
			if (mb_strlen($key) > 255) throw new Exception('A key is longer than 255 characters: ' . mb_substr($key, 0, 40) . '...');
		}

		// one transaction, 500 keys an insert: a list of thousands goes in a few queries instead of one commit each
		$db = new DatabaseLibrary();
		$added = 0;
		$db->pdo->beginTransaction();
		try {
			foreach (array_chunk($keys, 500) as $chunk) {
				$insert = $db->pdo->prepare('INSERT IGNORE INTO `product_keys` (product_id, key_value) VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?)')));
				$values = array();
				foreach ($chunk as $key) array_push($values, (int) $product_id, $key);
				$insert->execute($values);
				$added += $insert->rowCount();
			}
			self::recount($db->pdo, $product_id);
			$db->pdo->commit();
		}
		catch (Throwable $e) {
			$db->pdo->rollBack();
			throw $e;
		}
		return array($added, count($lines) - count($keys), count($keys) - $added);
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
			WHERE i.order_id = :order_id AND i.digital = 1 AND p.use_keys = 1 AND k.id IS NULL');
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

	// takes the unused keys out, a wrong paste say. keys already on orders stay with them. to stop handing keys out
	// without losing the pool, untick Digital key on the product instead
	public static function clearUnused($product_id) {
		$db = new DatabaseLibrary();
		$delete = $db->pdo->prepare('DELETE FROM `product_keys` WHERE product_id = :product_id AND order_item_id IS NULL');
		$delete->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$delete->execute();
		self::recount($db->pdo, $product_id);
		return $delete->rowCount();
	}

	// products.keys_left from the pool: how many keys are unused
	private static function recount($pdo, $product_id) {
		$update = $pdo->prepare('
			UPDATE `products` SET keys_left = (
				SELECT COUNT(*) FROM `product_keys` WHERE product_id = :product_id AND order_item_id IS NULL
			) WHERE id = :id');
		$update->bindValue(':product_id', (int) $product_id, PDO::PARAM_INT);
		$update->bindValue(':id', (int) $product_id, PDO::PARAM_INT);
		$update->execute();
	}
}
