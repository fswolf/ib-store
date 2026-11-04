<?php
// the cart lives in the session as product_id => qty. products are looked up fresh every time so a
// price change in admin shows up in the cart right away
class CartLibrary {
	private $session;
	private $lines = array();   // product_id => qty
	private $products = null;   // cache for items()

	const KEY = 'cart';
	const MAX_QTY = 99;

	public function __construct() {
		$this->session = new SessionLibrary();
		$lines = $this->session->get(self::KEY);
		if (is_array($lines)) $this->lines = $lines;
	}

	private function store() {
		$this->session->set(self::KEY, $this->lines);
		$this->products = null;
	}

	// -- Changes -- //
	public function add($product_id, $qty = 1) {
		$product = ProductsModel::getProduct($product_id);
		if (!$product || !$product->active) throw new Exception('That item is not available');

		$qty = max(1, (int) $qty);
		$have = $this->lines[$product->id] ?? 0;
		$want = min($have + $qty, self::MAX_QTY);

		// digital items are one per order, physical ones are capped by stock
		if ($product->digital) $want = 1;
		elseif ($want > $product->stock) {
			if ($product->stock < 1) throw new Exception($product->name . ' is out of stock');
			$want = (int) $product->stock;
		}

		$this->lines[$product->id] = $want;
		$this->store();
		return $want;
	}

	public function update($product_id, $qty) {
		$product_id = (int) $product_id;
		if (!isset($this->lines[$product_id])) throw new Exception('That item is not in your cart');

		$qty = (int) $qty;
		if ($qty < 1) return $this->remove($product_id);

		$product = ProductsModel::getProduct($product_id);
		if (!$product) return $this->remove($product_id);
		if ($product->digital) $qty = 1;
		elseif ($qty > $product->stock) $qty = max(1, (int) $product->stock);

		$this->lines[$product_id] = min($qty, self::MAX_QTY);
		$this->store();
		return $this->lines[$product_id];
	}

	public function remove($product_id) {
		unset($this->lines[(int) $product_id]);
		$this->store();
		return 0;
	}

	public function clear() {
		$this->lines = array();
		$this->store();
	}

	// -- Reads -- //
	public function count() {
		return array_sum($this->lines);
	}

	// lines with their product attached: [ {product: ProductsModel, qty: n, line_total: 0.00}, ... ]
	// items that vanished or went inactive since they were added get dropped here
	public function items() {
		if ($this->products === null) $this->products = ProductsModel::getMany(array_keys($this->lines));

		$out = array();
		$changed = false;
		foreach ($this->lines as $id => $qty) {
			if (!isset($this->products[$id]) || !$this->products[$id]->active) {
				unset($this->lines[$id]);
				$changed = true;
				continue;
			}
			$product = $this->products[$id];
			$out[] = (object) array(
				'product' => $product,
				'qty' => $qty,
				'line_total' => number_format($product->price * $qty, 2, '.', ''),
			);
		}
		if ($changed) $this->session->set(self::KEY, $this->lines);
		return $out;
	}

	public function hasPhysical() {
		foreach ($this->items() as $item) if (!$item->product->digital) return true;
		return false;
	}

	// subtotal, shipping (flat, waived over the threshold, none for digital only), tax on the subtotal
	public function totals() {
		$rates = StoreInfoModel::getRates();
		$subtotal = 0;
		foreach ($this->items() as $item) $subtotal += $item->product->price * $item->qty;

		$shipping = 0;
		if ($this->hasPhysical()) {
			$shipping = (float) $rates->shipping_flat;
			if ($rates->free_shipping_over !== null && $subtotal >= (float) $rates->free_shipping_over) $shipping = 0;
		}

		$tax = round($subtotal * ((float) $rates->tax_rate / 100), 2);

		return array(
			'currency' => $rates->currency ?: 'USD',
			'count' => $this->count(),
			'subtotal' => number_format($subtotal, 2, '.', ''),
			'shipping' => number_format($shipping, 2, '.', ''),
			'tax' => number_format($tax, 2, '.', ''),
			'tax_rate' => number_format((float) $rates->tax_rate, 2, '.', ''),
			'total' => number_format($subtotal + $shipping + $tax, 2, '.', ''),
			'has_physical' => $this->hasPhysical(),
		);
	}

	// what the cart api hands to the browser
	public function toArray() {
		$items = array();
		foreach ($this->items() as $item) {
			$items[] = array(
				'id' => (int) $item->product->id,
				'name' => $item->product->name,
				'slug' => $item->product->slug,
				'sku' => $item->product->sku,
				'image' => $item->product->image,
				'price' => $item->product->price,
				'qty' => (int) $item->qty,
				'stock' => (int) $item->product->stock,
				'digital' => (int) $item->product->digital,
				'line_total' => $item->line_total,
			);
		}
		return array('items' => $items, 'totals' => $this->totals());
	}
}
