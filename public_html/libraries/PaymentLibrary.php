<?php
// what every payment gateway looks like to the rest of the store. PaymentApiController and the payment page
// only talk to this; BtcpayLibrary, StripeLibrary and PaypalLibrary fill in the three calls each.
// adding a gateway: a new *Library extending this, one line in all(), a block in config.php, an icon in the view
abstract class PaymentLibrary {
	const METHOD = ''; // what goes in orders.payment_method
	const LABEL = '';  // the button text

	// -- Registry -- //
	public static function all() {
		return array(
			'btcpay' => 'BtcpayLibrary',
			'stripe' => 'StripeLibrary',
			'paypal' => 'PaypalLibrary',
		);
	}

	// name => label for the ones customers can pay with, in button order: keys filled in config and not switched off
	// in the admin panel (store settings > payments)
	public static function enabledList() {
		$off = StoreInfoModel::getPaymentsOff();
		$out = array();
		foreach (self::all() as $name => $class) if ($class::enabled() && !in_array($name, $off)) $out[$name] = $class::LABEL;
		return $out;
	}

	// an instance by name, false when unknown or its keys are missing. one switched off in the admin panel still
	// answers here, so a payment started before the switch can be checked and settle through its webhook
	public static function get($name) {
		$class = self::all()[$name] ?? null;
		if (!$class || !$class::enabled()) return false;
		return new $class();
	}

	// -- What a gateway does -- //
	// config filled in?
	abstract public static function enabled();

	// begin (or pick up) the payment for an order: returns the url to send the customer to.
	// $redirect is our payment page, where the gateway sends them back
	abstract public function start($order, $redirect);

	// where the gateway thinks the order's payment is at:
	// array(paid => bool, open => bool (still payable), state => the gateway's own word for it)
	abstract public function check($order);

	// a webhook post from the gateway. throws when the signature does not pass.
	// returns array(order => OrdersModel or false, paid => bool) and may do work on the way (paypal captures)
	abstract public function webhook($body, $server);

	// -- Shared -- //
	// the one place an order becomes paid. markPaid is guarded on pending, so this is safe to call from the
	// webhook and the page's status check at the same time: only one of them sends the mails
	public function settle($order, $ref) {
		if (!$order->markPaid(static::METHOD, $ref)) return false;
		OrderMailLibrary::statusChanged($order, 'pending', 'paid');
		return true;
	}

	// is this order already on this gateway (has a ref over there)
	protected function mine($order) {
		return $order->payment_method === static::METHOD && $order->payment_ref;
	}
}
