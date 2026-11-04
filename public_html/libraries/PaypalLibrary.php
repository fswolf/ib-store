<?php
// PayPal through the Orders v2 rest api on curl, no sdk (paypal retired theirs). the customer approves on
// paypal, lands back on our payment page, and we capture there. a webhook is optional backup: with
// PAYPAL_WEBHOOK_ID set it is checked through paypal's verify call, without it the webhook is ignored
class PaypalLibrary extends PaymentLibrary {
	const METHOD = 'paypal';
	const LABEL = 'Pay with PayPal';

	public $client_id = PAYPAL_CLIENT_ID;
	public $secret = PAYPAL_SECRET;
	public $webhook_id = PAYPAL_WEBHOOK_ID;
	public $api = PAYPAL_SANDBOX ? 'https://api-m.sandbox.paypal.com/' : 'https://api-m.paypal.com/';

	private $token = null;

	public function __construct($properties = []) {
		if (defined('PAYPAL_API')) $this->api = PAYPAL_API; // tests point this at a fake
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	public static function enabled() {
		return PAYPAL_CLIENT_ID !== '' && PAYPAL_SECRET !== '';
	}

	// a paypal order for ours, itemised, with the totals broken down the way paypal insists on. returns the
	// approve link. one that is already approved just gets captured and the customer stays on our page;
	// one that is created but not approved yet is sent again rather than made twice
	public function start($order, $redirect) {
		if ($this->mine($order)) {
			$current = $this->order($order->payment_ref);
			if (($current['status'] ?? '') === 'APPROVED') {
				if ($this->capture($order->payment_ref)) $this->settle($order, $order->payment_ref);
				return $redirect;
			}
			if (($current['status'] ?? '') === 'CREATED' && ($link = $this->approveLink($current))) return $link;
		}

		if (!$order->items) $order->getItems();
		$currency = strtoupper($order->currency);
		$money = fn($v) => array('currency_code' => $currency, 'value' => number_format((float) $v, 2, '.', ''));

		$items = array();
		foreach ($order->items as $item) {
			$items[] = array(
				'name' => mb_substr($item['name'], 0, 127),
				'quantity' => (string) (int) $item['qty'],
				'unit_amount' => $money($item['price']),
				'category' => $item['digital'] ? 'DIGITAL_GOODS' : 'PHYSICAL_GOODS',
			);
		}

		$body = array(
			'intent' => 'CAPTURE',
			'payment_source' => array('paypal' => array('experience_context' => array(
				'return_url' => $redirect,
				'cancel_url' => $redirect,
				'user_action' => 'PAY_NOW',
				'shipping_preference' => 'NO_SHIPPING', // we already have the address from checkout
				'brand_name' => mb_substr((string) (StoreInfoModel::getBase()->store_name ?? ''), 0, 127),
			))),
			'purchase_units' => array(array(
				'reference_id' => $order->uuid,
				'custom_id' => $order->uuid,
				'description' => 'Order ' . substr($order->uuid, 0, 8),
				'amount' => $money($order->total) + array('breakdown' => array(
					'item_total' => $money($order->subtotal),
					'shipping' => $money($order->shipping),
					'tax_total' => $money($order->tax),
				)),
				'items' => $items,
			)),
		);

		$created = $this->request('POST', 'v2/checkout/orders', $body);
		$link = $this->approveLink($created);
		if (!$link) throw new Exception('PayPal gave no approve link');

		$order->setPayment(self::METHOD, $created['id']);
		return $link;
	}

	// CREATED, APPROVED (customer said yes, not captured yet), COMPLETED (captured), VOIDED.
	// an approved one gets captured right here: that is the normal path, the customer landing back on our page
	public function check($order) {
		$status = $this->order($order->payment_ref)['status'] ?? 'UNKNOWN';
		if ($status === 'APPROVED' && $this->capture($order->payment_ref)) $status = 'COMPLETED';
		return array('paid' => $status === 'COMPLETED', 'open' => in_array($status, array('CREATED', 'APPROVED')), 'state' => $status);
	}

	private function order($paypal_id) {
		return $this->request('GET', 'v2/checkout/orders/' . rawurlencode($paypal_id));
	}

	private function approveLink($paypal_order) {
		foreach ($paypal_order['links'] ?? array() as $l) if (in_array($l['rel'] ?? '', array('payer-action', 'approve'))) return $l['href'];
		return null;
	}

	// take the money on an approved order. true when paypal says COMPLETED
	public function capture($paypal_id) {
		$o = $this->request('POST', 'v2/checkout/orders/' . rawurlencode($paypal_id) . '/capture', new stdClass());
		return ($o['status'] ?? '') === 'COMPLETED';
	}

	// a webhook post: paypal checks its own signature for us through the verify call, so this only works with
	// PAYPAL_WEBHOOK_ID set. a capture event carries our uuid in custom_id, an order event has it on the purchase unit.
	// CHECKOUT.ORDER.APPROVED means they approved and never came back, so it gets captured from here
	public function webhook($body, $server) {
		if ($this->webhook_id === '') throw new Exception('PAYPAL_WEBHOOK_ID is not set, webhook ignored');
		$event = json_decode($body);
		if (!is_object($event) || empty($event->event_type)) throw new Exception('Not a paypal event');

		$headers = array();
		foreach ($server as $k => $v) if (str_starts_with($k, 'HTTP_PAYPAL_')) $headers[str_replace('_', '-', substr($k, 5))] = $v;

		$check = $this->request('POST', 'v1/notifications/verify-webhook-signature', array(
			'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
			'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
			'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
			'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
			'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
			'webhook_id' => $this->webhook_id,
			'webhook_event' => json_decode($body), // as an object so it goes back out exactly as it came
		));
		if (($check['verification_status'] ?? '') !== 'SUCCESS') throw new Exception('Bad webhook signature');

		$resource = $event->resource ?? new stdClass();
		$uuid = $resource->custom_id ?? ($resource->purchase_units[0]->custom_id ?? '');
		$order = $uuid ? OrdersModel::getByUuid((string) $uuid) : false;
		if ($order && $order->payment_method !== self::METHOD) $order = false;

		$paid = false;
		if ($order && $order->status === 'pending') {
			if ($event->event_type === 'PAYMENT.CAPTURE.COMPLETED') $paid = true;
			if ($event->event_type === 'CHECKOUT.ORDER.APPROVED') $paid = $this->capture($order->payment_ref);
		}
		return array('order' => $order, 'paid' => $paid);
	}

	// -- Plumbing -- //
	private function token() {
		if ($this->token) return $this->token;
		$curl = curl_init($this->api . 'v1/oauth2/token');
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_USERPWD => $this->client_id . ':' . $this->secret,
			CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
		));
		$body = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		$data = $body !== false ? json_decode($body, true) : null;
		if (empty($data['access_token'])) throw new Exception('PayPal login failed: ' . ($data['error_description'] ?? $err ?: substr((string) $body, 0, 200)));
		return $this->token = $data['access_token'];
	}

	// json in, json out, errors under message / details
	private function request($method, $path, $body = null) {
		$curl = curl_init($this->api . $path);
		$headers = array('Authorization: Bearer ' . $this->token(), 'Content-Type: application/json', 'Prefer: return=representation');
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CUSTOMREQUEST => $method,
		));
		if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));

		$out = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$err = curl_error($curl);
		curl_close($curl);

		if ($out === false) throw new Exception('PayPal unreachable: ' . $err);
		$data = json_decode($out, true);
		if ($code >= 400 || !is_array($data)) throw new Exception('PayPal ' . $code . ': ' . ($data['details'][0]['description'] ?? $data['message'] ?? substr((string) $out, 0, 200)));
		return $data;
	}
}
