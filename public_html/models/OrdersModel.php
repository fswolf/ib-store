<?php
// orders + order_items. an order is created pending at checkout, the payment step (later) flips it to paid
class OrdersModel {
	public $id;
	public $uuid;
	public $account_id;
	public $email;
	public $status;
	public $currency;
	public $subtotal;
	public $shipping;
	public $tax;
	public $total;
	public $has_physical;

	public $bill_first_name;
	public $bill_last_name;
	public $bill_company;
	public $bill_phone;
	public $bill_address_1;
	public $bill_address_2;
	public $bill_city;
	public $bill_province;
	public $bill_postal_code;
	public $bill_country;

	public $ship_first_name;
	public $ship_last_name;
	public $ship_company;
	public $ship_phone;
	public $ship_address_1;
	public $ship_address_2;
	public $ship_city;
	public $ship_province;
	public $ship_postal_code;
	public $ship_country;

	public $notes;
	public $admin_notes;
	public $payment_method;
	public $payment_ref;
	public $paid_at;
	public $created;
	public $updated;

	// joined in by getPage / admin views
	public $username;
	public $item_count;
	public $digital_count;

	// filled by getItems()
	public $items = array();

	const STATUSES = array('pending', 'paid', 'shipped', 'complete', 'cancelled', 'refunded');

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	// -- Create -- //
	// $cart is a CartLibrary, $billing / $shipping are AddressesModel ($shipping null for digital only)
	public static function create($user, $cart, $billing, $shipping, $notes = '') {
		$db = new DatabaseLibrary();
		$totals = $cart->totals();
		$items = $cart->items();
		if (!$items) throw new Exception('Your cart is empty');

		$uuid = self::uuid();
		$has_physical = $cart->hasPhysical() ? 1 : 0;

		$db->pdo->beginTransaction();
		try {
			$insert = $db->pdo->prepare('
				INSERT INTO orders
					(uuid, account_id, email, status, currency, subtotal, shipping, tax, total, has_physical,
					 bill_first_name, bill_last_name, bill_company, bill_phone, bill_address_1, bill_address_2, bill_city, bill_province, bill_postal_code, bill_country,
					 ship_first_name, ship_last_name, ship_company, ship_phone, ship_address_1, ship_address_2, ship_city, ship_province, ship_postal_code, ship_country,
					 notes)
				VALUES
					(:uuid, :account_id, :email, :status, :currency, :subtotal, :shipping, :tax, :total, :has_physical,
					 :bill_first_name, :bill_last_name, :bill_company, :bill_phone, :bill_address_1, :bill_address_2, :bill_city, :bill_province, :bill_postal_code, :bill_country,
					 :ship_first_name, :ship_last_name, :ship_company, :ship_phone, :ship_address_1, :ship_address_2, :ship_city, :ship_province, :ship_postal_code, :ship_country,
					 :notes)
			');

			$params = array(
				':uuid' => $uuid,
				':account_id' => (int) $user->id,
				':email' => $user->email,
				':status' => 'pending',
				':currency' => $totals['currency'],
				':subtotal' => $totals['subtotal'],
				':shipping' => $totals['shipping'],
				':tax' => $totals['tax'],
				':total' => $totals['total'],
				':has_physical' => $has_physical,
				':notes' => trim((string) $notes) ?: null,
			);
			foreach (array('first_name', 'last_name', 'company', 'phone', 'address_1', 'address_2', 'city', 'province', 'postal_code', 'country') as $field) {
				$params[':bill_' . $field] = $billing->{$field};
				$params[':ship_' . $field] = $shipping ? $shipping->{$field} : null;
			}
			$insert->execute($params);
			$order_id = $db->pdo->lastInsertId();

			$insert = $db->pdo->prepare('
				INSERT INTO order_items
					(order_id, product_id, sku, name, price, qty, digital, download_url)
				VALUES
					(:order_id, :product_id, :sku, :name, :price, :qty, :digital, :download_url)
			');
			foreach ($items as $item) {
				$insert->execute(array(
					':order_id' => $order_id,
					':product_id' => (int) $item->product->id,
					':sku' => $item->product->sku,
					':name' => $item->product->name,
					':price' => $item->product->price,
					':qty' => (int) $item->qty,
					':digital' => (int) $item->product->digital,
					':download_url' => $item->product->digital && $item->product->use_files ? $item->product->download_url : null,
				));
				// a product handing out keys holds one for this line now, the same way physical stock comes off below
				if ($item->product->digital && $item->product->use_keys) {
					if (!ProductKeysModel::claim($db->pdo, $item->product->id, $db->pdo->lastInsertId())) throw new Exception($item->product->name . ' just sold out');
				}
				// physical stock comes off now so two people cannot buy the last one. a cancelled order puts it back.
				// same pdo as the inserts so it rolls back with them (every model opens its own connection)
				if (!$item->product->digital) {
					$stock = $db->pdo->prepare('UPDATE `products` SET stock = GREATEST(stock - :qty, 0) WHERE id = :id');
					$stock->bindValue(':qty', (int) $item->qty, PDO::PARAM_INT);
					$stock->bindValue(':id', (int) $item->product->id, PDO::PARAM_INT);
					$stock->execute();
				}
			}

			$db->pdo->commit();
		}
		catch (Throwable $e) {
			$db->pdo->rollBack();
			throw $e;
		}

		return self::getByUuid($uuid);
	}

	// -- Lookups -- //
	public static function getByUuid($uuid) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `orders` WHERE uuid = :uuid');
		$select->bindValue(':uuid', $uuid, PDO::PARAM_STR);
		$select->execute();
		$record = $select->fetch();
		if ($record) return new OrdersModel($record);
		return false;
	}

	public static function get($id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `orders` WHERE id = :id');
		$select->bindValue(':id', (int) $id, PDO::PARAM_INT);
		$select->execute();
		$record = $select->fetch();
		if ($record) return new OrdersModel($record);
		return false;
	}

	// the account's own orders, newest first
	public static function getByAccount($account_id) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('
			SELECT o.*, COUNT(i.id) AS item_count, COALESCE(SUM(i.digital), 0) AS digital_count
			FROM `orders` o
			LEFT JOIN order_items i ON i.order_id = o.id
			WHERE o.account_id = :account_id
			GROUP BY o.id
			ORDER BY o.created DESC');
		$select->bindValue(':account_id', (int) $account_id, PDO::PARAM_INT);
		$select->execute();
		$result = $select->fetchAll();
		$output = array();
		foreach($result as $record) $output[] = new OrdersModel($record);
		return $output;
	}

	public function getItems() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `order_items` WHERE order_id = :order_id ORDER BY id ASC');
		$select->bindValue(':order_id', (int) $this->id, PDO::PARAM_INT);
		$select->execute();
		$this->items = $select->fetchAll();

		// each line's keys (product_keys), empty for lines without
		$keys = ProductKeysModel::forItems(array_column($this->items, 'id'));
		foreach ($this->items as &$item) $item['keys'] = $keys[$item['id']] ?? array();
		unset($item);
		return $this->items;
	}

	// downloads only show once the order is paid
	public function downloadsReady() {
		return in_array($this->status, array('paid', 'shipped', 'complete'));
	}

	public function statusLabel() {
		return ucfirst($this->status);
	}

	// -- Payment -- //
	// which gateway this order is going through and its id over there (a btcpay invoice id, a paypal order id)
	public function setPayment($method, $ref) {
		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare('UPDATE `orders` SET payment_method = :payment_method, payment_ref = :payment_ref WHERE id = :id');
		$update->bindValue(':payment_method', $method, PDO::PARAM_STR);
		$update->bindValue(':payment_ref', $ref, PDO::PARAM_STR);
		$update->bindValue(':id', (int) $this->id, PDO::PARAM_INT);
		$update->execute();
		$this->payment_method = $method;
		$this->payment_ref = $ref;
		return true;
	}

	// the gateway said the money is in. one update guarded on pending, so a webhook and the page's status check
	// landing together cannot both win: exactly one caller gets true and sends the paid mails
	public function markPaid($method, $ref) {
		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare("UPDATE `orders` SET status = 'paid', paid_at = NOW(), payment_method = :payment_method, payment_ref = :payment_ref WHERE id = :id AND status = 'pending'");
		$update->bindValue(':payment_method', $method, PDO::PARAM_STR);
		$update->bindValue(':payment_ref', $ref, PDO::PARAM_STR);
		$update->bindValue(':id', (int) $this->id, PDO::PARAM_INT);
		$update->execute();
		if ($update->rowCount() !== 1) return false;

		$this->status = 'paid';
		$this->payment_method = $method;
		$this->payment_ref = $ref;
		return true;
	}

	public static function getByPaymentRef($method, $ref) {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `orders` WHERE payment_method = :payment_method AND payment_ref = :payment_ref ORDER BY id DESC LIMIT 1');
		$select->bindValue(':payment_method', $method, PDO::PARAM_STR);
		$select->bindValue(':payment_ref', $ref, PDO::PARAM_STR);
		$select->execute();
		$record = $select->fetch();
		if ($record) return new OrdersModel($record);
		return false;
	}

	// -- Admin -- //
	public function setStatus($status) {
		if (!in_array($status, self::STATUSES)) throw new Exception('Unknown status');
		$db = new DatabaseLibrary();

		// taking an order out of cancelled holds keys again for its key lines, or refuses when a product ran out
		if ($this->status === 'cancelled' && $status !== 'cancelled') ProductKeysModel::reclaim($this->id);

		// cancelling hands the stock back, and the held keys too while the order was never paid. a paid order's
		// keys were on its page and in its email already, so they stay with it instead of going to the next buyer
		if ($status === 'cancelled' && $this->status !== 'cancelled') {
			if ($this->status === 'pending') ProductKeysModel::release($this->id);
			foreach ($this->getItems() as $item) {
				if (!$item['digital'] && $item['product_id']) {
					$update = $db->pdo->prepare('UPDATE `products` SET stock = stock + :qty WHERE id = :id');
					$update->bindValue(':qty', (int) $item['qty'], PDO::PARAM_INT);
					$update->bindValue(':id', (int) $item['product_id'], PDO::PARAM_INT);
					$update->execute();
				}
			}
		}

		$update = $db->pdo->prepare('UPDATE `orders` SET status = :status, paid_at = IF(:paid = 1 AND paid_at IS NULL, NOW(), paid_at) WHERE id = :id');
		$update->bindValue(':status', $status, PDO::PARAM_STR);
		$update->bindValue(':paid', $status === 'paid' ? 1 : 0, PDO::PARAM_INT);
		$update->bindValue(':id', (int) $this->id, PDO::PARAM_INT);
		$update->execute();
		$this->status = $status;
		return true;
	}

	public function saveAdminNotes($notes) {
		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare('UPDATE `orders` SET admin_notes = :admin_notes WHERE id = :id');
		$update->bindValue(':admin_notes', trim((string) $notes) ?: null, PDO::PARAM_STR);
		$update->bindValue(':id', (int) $this->id, PDO::PARAM_INT);
		$update->execute();
		$this->admin_notes = $notes;
		return true;
	}

	// dashboard numbers
	public static function stats() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare("
			SELECT
				COUNT(*) AS orders,
				SUM(status = 'pending') AS pending,
				SUM(status = 'paid') AS paid,
				SUM(status IN ('paid', 'shipped', 'complete')) AS sales,
				COALESCE(SUM(IF(status IN ('paid', 'shipped', 'complete'), total, 0)), 0) AS revenue
			FROM `orders`");
		$select->execute();
		return $select->fetch();
	}

	// admin table, same shape tablesorter.js posts: rows, offset, sort, order, input
	public static function count($search = '', $status = '') {
		$db = new DatabaseLibrary();
		list($where, $params) = self::filter($search, $status);
		$select = $db->pdo->prepare("SELECT COUNT(*) FROM `orders` o LEFT JOIN accounts a ON a.id = o.account_id $where");
		$select->execute($params);
		return (int) $select->fetchColumn();
	}

	// $rows null = every matching order, for the csv export
	public static function getPage($rows, $offset, $sort = 'created', $order = 'desc', $search = '', $status = '') {
		$db = new DatabaseLibrary();

		// sort column comes from the browser, whitelist it (qualified, accounts has id and email too)
		$columns = array('id' => 'o.id', 'created' => 'o.created', 'status' => 'o.status', 'total' => 'o.total', 'email' => 'o.email', 'username' => 'a.username');
		$sort = $columns[$sort] ?? 'o.created';
		$order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

		list($where, $params) = self::filter($search, $status);

		$select = $db->pdo->prepare("
			SELECT o.*, a.username, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
			FROM `orders` o
			LEFT JOIN accounts a ON a.id = o.account_id
			$where
			ORDER BY $sort $order" . ($rows === null ? '' : ' LIMIT :rows OFFSET :offset'));

		foreach ($params as $key => $value) $select->bindValue($key, $value, PDO::PARAM_STR);
		if ($rows !== null) {
			$select->bindValue(':rows', (int) $rows, PDO::PARAM_INT);
			$select->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
		}
		$select->execute();
		$result = $select->fetchAll();

		$output = array();
		foreach($result as $record) $output[] = new OrdersModel($record);
		return $output;
	}

	private static function filter($search, $status) {
		$where = array();
		$params = array();
		if ($status !== '' && in_array($status, self::STATUSES)) {
			$where[] = 'o.status = :status';
			$params[':status'] = $status;
		}
		if ($search !== '') {
			$where[] = '(o.uuid LIKE :t1 OR o.email LIKE :t2 OR a.username LIKE :t3 OR o.bill_last_name LIKE :t4)';
			$term = '%' . addcslashes($search, '%_\\') . '%';
			$params += [':t1' => $term, ':t2' => $term, ':t3' => $term, ':t4' => $term];
		}
		return array($where ? 'WHERE ' . implode(' AND ', $where) : '', $params);
	}

	// -- Helpers -- //
	// random v4 uuid for the public order url
	public static function uuid() {
		$b = random_bytes(16);
		$b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
		$b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
	}
}
