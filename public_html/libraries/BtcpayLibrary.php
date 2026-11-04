<?php
// bitcoin through a self hosted BTCPay Server, using the vendored Greenfield client in assets/vendor/BTCPayServer.
// one invoice per order: the customer pays on btcpay's checkout page, btcpay calls our webhook when it settles
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Client\Webhook;
use BTCPayServer\Util\PreciseNumber;

class BtcpayLibrary extends PaymentLibrary {
	const METHOD = 'btcpay';
	const LABEL = 'Pay with Bitcoin';

	public $url = BTCPAY_URL;
	public $api_key = BTCPAY_API_KEY;
	public $store_id = BTCPAY_STORE_ID;
	public $webhook_secret = BTCPAY_WEBHOOK_SECRET;

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	public static function enabled() {
		return BTCPAY_URL !== '' && BTCPAY_API_KEY !== '' && BTCPAY_STORE_ID !== '';
	}

	private function client() {
		return new Invoice(rtrim($this->url, '/'), $this->api_key);
	}

	// an invoice that is still open gets reused so a refresh does not stack them up, otherwise a new one
	public function start($order, $redirect) {
		if ($this->mine($order)) {
			$invoice = $this->client()->getInvoice($this->store_id, $order->payment_ref);
			if (in_array($invoice->getStatus(), array('New', 'Processing'))) return $invoice->getCheckoutLink();
		}

		$options = InvoiceCheckoutOptions::create(
			InvoiceCheckoutOptions::SPEED_MEDIUM, // 1 confirmation, btcpay's store setting can override
			null,          // payment methods: whatever the btcpay store offers (on chain, lightning)
			30,            // minutes before the invoice expires
			null, null,    // monitoring minutes, payment tolerance: btcpay defaults
			$redirect,     // back to our payment page when they are done
			true,          // redirect automatically
			null           // language: btcpay's default
		);

		$invoice = $this->client()->createInvoice(
			$this->store_id,
			$order->currency,
			PreciseNumber::parseString($order->total),
			$order->uuid,
			$order->email,
			array('orderUrl' => $redirect, 'itemDesc' => 'Order ' . substr($order->uuid, 0, 8)),
			$options
		);

		$order->setPayment(self::METHOD, $invoice->getId());
		return $invoice->getCheckoutLink();
	}

	// btcpay's word on the invoice: New, Processing, Settled, Expired, Invalid
	public function check($order) {
		$status = $this->client()->getInvoice($this->store_id, $order->payment_ref)->getStatus();
		return array('paid' => $status === 'Settled', 'open' => in_array($status, array('New', 'Processing')), 'state' => $status);
	}

	// signed with the webhook secret, the library checks the hmac. InvoiceSettled is the one that pays the order,
	// Expired / Invalid need nothing from us: the customer can start a fresh invoice from the payment page
	public function webhook($body, $server) {
		if (!Webhook::isIncomingWebhookRequestValid($body, (string) ($server['HTTP_BTCPAY_SIG'] ?? ''), $this->webhook_secret)) throw new Exception('Bad webhook signature');
		$event = json_decode($body);
		if (!is_object($event) || empty($event->type) || empty($event->invoiceId)) throw new Exception('Not a btcpay event');

		$order = OrdersModel::getByPaymentRef(self::METHOD, $event->invoiceId);
		return array('order' => $order, 'paid' => $event->type === 'InvoiceSettled');
	}
}
