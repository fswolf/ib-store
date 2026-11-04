<?php
// cards through Stripe Checkout: the customer pays on stripe's hosted page, stripe calls our webhook when it
// goes through. three rest calls on curl, no sdk (stripe's is composer only and we need a sliver of it).
// amounts go over in cents, the webhook is signed with STRIPE_WEBHOOK_SECRET
class StripeLibrary extends PaymentLibrary {
	const METHOD = 'stripe';
	const LABEL = 'Pay by Card';

	public $secret_key = STRIPE_SECRET_KEY;
	public $webhook_secret = STRIPE_WEBHOOK_SECRET;
	public $api = 'https://api.stripe.com/v1/'; // a STRIPE_API define in config points this at a fake for testing

	public function __construct($properties = []) {
		if (defined('STRIPE_API')) $this->api = STRIPE_API;
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	public static function enabled() {
		return STRIPE_SECRET_KEY !== '';
	}

	// a session that is still open gets reused so a refresh does not stack them up, otherwise a new one:
	// a checkout session for the order, itemised so the stripe receipt matches ours. returns the hosted page url
	public function start($order, $redirect) {
		if ($this->mine($order)) {
			$session = $this->session($order->payment_ref);
			if (($session['status'] ?? '') === 'open' && !empty($session['url'])) return $session['url'];
		}
		if (!$order->items) $order->getItems();

		$params = array(
			'mode' => 'payment',
			'success_url' => $redirect,
			'cancel_url' => $redirect,
			'client_reference_id' => $order->uuid,
			'customer_email' => $order->email,
			'expires_at' => time() + 60 * 60, // an hour, stripe's floor is 30 minutes
			'metadata' => array('order' => $order->uuid),
		);

		$n = 0;
		$currency = strtolower($order->currency);
		foreach ($order->items as $item) {
			$params["line_items[$n][quantity]"] = (int) $item['qty'];
			$params["line_items[$n][price_data][currency]"] = $currency;
			$params["line_items[$n][price_data][unit_amount]"] = self::cents($item['price']);
			$params["line_items[$n][price_data][product_data][name]"] = mb_substr($item['name'], 0, 250);
			$n++;
		}
		foreach (array('shipping' => 'Shipping', 'tax' => 'Tax') as $field => $label) {
			if ((float) $order->{$field} <= 0) continue;
			$params["line_items[$n][quantity]"] = 1;
			$params["line_items[$n][price_data][currency]"] = $currency;
			$params["line_items[$n][price_data][unit_amount]"] = self::cents($order->{$field});
			$params["line_items[$n][price_data][product_data][name]"] = $label;
			$n++;
		}

		$session = $this->request('POST', 'checkout/sessions', $params);
		$order->setPayment(self::METHOD, $session['id']);
		return $session['url'];
	}

	// stripe's word on the session: status open / complete / expired, payment_status paid / unpaid
	public function check($order) {
		$session = $this->session($order->payment_ref);
		$paid = ($session['payment_status'] ?? '') === 'paid';
		$status = $session['status'] ?? 'unknown';
		return array('paid' => $paid, 'open' => $status === 'open', 'state' => $paid ? 'paid' : $status);
	}

	private function session($session_id) {
		return $this->request('GET', 'checkout/sessions/' . rawurlencode($session_id));
	}

	// Stripe-Signature is t=...,v1=..., an hmac over "t.body". throws when it is wrong or older than five minutes.
	// checkout.session.completed is the pay event, async methods (bank debits) finish with async_payment_succeeded
	public function webhook($body, $server) {
		$signature = $server['HTTP_STRIPE_SIGNATURE'] ?? '';
		$parts = array();
		foreach (explode(',', (string) $signature) as $pair) {
			$kv = explode('=', $pair, 2);
			if (count($kv) === 2) $parts[trim($kv[0])][] = trim($kv[1]);
		}
		$time = (int) ($parts['t'][0] ?? 0);
		if ($time === 0 || abs(time() - $time) > 300) throw new Exception('Webhook signature is missing or stale');

		$expected = hash_hmac('sha256', $time . '.' . $body, $this->webhook_secret);
		$ok = false;
		foreach ($parts['v1'] ?? array() as $sig) if (hash_equals($expected, $sig)) $ok = true;
		if (!$ok) throw new Exception('Bad webhook signature');

		$event = json_decode($body);
		if (!is_object($event) || empty($event->type) || empty($event->data->object)) throw new Exception('Not a stripe event');

		$session = $event->data->object;
		$order = OrdersModel::getByPaymentRef(self::METHOD, (string) ($session->id ?? ''));
		$paid = in_array($event->type, array('checkout.session.completed', 'checkout.session.async_payment_succeeded')) && ($session->payment_status ?? '') === 'paid';
		return array('order' => $order, 'paid' => $paid);
	}

	// -- Plumbing -- //
	public static function cents($amount) {
		return (int) round((float) $amount * 100);
	}

	// stripe takes form encoded params, answers json, and puts errors under error.message
	private function request($method, $path, $params = array()) {
		$curl = curl_init($this->api . $path);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->secret_key, 'Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_CUSTOMREQUEST => $method,
		));
		if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));

		$body = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$err = curl_error($curl);
		curl_close($curl);

		if ($body === false) throw new Exception('Stripe unreachable: ' . $err);
		$data = json_decode($body, true);
		if ($code >= 400 || !is_array($data)) throw new Exception('Stripe ' . $code . ': ' . ($data['error']['message'] ?? substr((string) $body, 0, 200)));
		return $data;
	}
}
