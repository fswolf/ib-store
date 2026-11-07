<?php
// the emails an order sends out, all in one place so checkout, the payment webhook and the admin status
// select send the same things. every send is best effort: a mail problem goes in the log, never to the caller
class OrderMailLibrary {
	private $order;
	private $info;
	private $mail;
	private $link;
	private $short;

	public function __construct($order) {
		$this->order = $order;
		$this->info = StoreInfoModel::getContact();
		$this->mail = new MailLibrary(['from_name' => $this->info->store_name, 'reply_to' => (string) $this->info->email]);
		$this->link = base_url() . '/user/orders/' . $order->uuid;
		$this->short = substr($order->uuid, 0, 8);
		if (!$order->items) $order->getItems();
	}

	// -- What gets sent -- //
	// checkout: confirmation to the customer, heads up to the store
	public static function placed($order) {
		if (!MailLibrary::enabled()) return;
		$m = new OrderMailLibrary($order);
		$m->customer("Order {$m->short}", 'Thanks for your order. It is saved and waiting on payment.<br><br>' . $m->lines(), 'View Order');
		$m->store("New order {$m->short}", htmlspecialchars($order->email) . ' placed an order, status pending.<br><br>' . $m->lines());
	}

	// status went from $from to $to, by the payment webhook or the admin select
	public static function statusChanged($order, $from, $to) {
		if ($from === $to || !MailLibrary::enabled()) return;
		$m = new OrderMailLibrary($order);

		switch ($to) {
			case 'paid':
				$m->customer("Order {$m->short} paid", 'Payment received, thank you.' . ($order->has_physical ? ' We will let you know when it ships.' : '') . $m->downloads() . '<br><br>' . $m->lines(), 'View Order');
				$m->store("Order {$m->short} paid", 'Payment came in for ' . htmlspecialchars($order->email) . ($order->payment_method ? ' through ' . htmlspecialchars($order->payment_method) : '') . ($order->has_physical ? '. It needs shipping.' : '.') . '<br><br>' . $m->lines());
				break;
			case 'shipped':
				$m->customer("Order {$m->short} shipped", 'Your order is on its way.' . $m->downloads() . '<br><br>' . $m->lines(), 'View Order');
				break;
			case 'complete':
				$m->customer("Order {$m->short} complete", 'Your order is complete. Thanks for shopping with us.' . $m->downloads(), 'View Order');
				break;
			case 'cancelled':
				$m->customer("Order {$m->short} cancelled", 'This order was cancelled. If that is a surprise, reply to this email and we will sort it out.', null);
				break;
			case 'refunded':
				$m->customer("Order {$m->short} refunded", 'This order was refunded.', null);
				break;
		}
	}

	// -- Pieces -- //
	private function customer($subject, $body, $button) {
		$this->send($this->order->email, "{$this->info->store_name}: $subject", $subject, $body, $button, $this->link);
	}

	private function store($subject, $body) {
		if (!$this->info->email) return;
		$this->send($this->info->email, $subject, $subject, $body, 'Open in Admin', str_replace('/user/orders/', '/admin/orders/', $this->link));
	}

	private function send($to, $subject, $heading, $body, $button, $link) {
		if (!MailLibrary::enabled()) return;
		try {
			$this->mail->send($to, $subject, MailLibrary::card($this->info->store_name, $heading, $body, $button, $link), true);
		}
		catch (Exception $e) {
			error_log("Order mail ($heading) failed: " . $e->getMessage());
		}
	}

	// the items and totals block
	private function lines() {
		$o = $this->order;
		$lines = '';
		foreach ($o->items as $item) $lines .= htmlspecialchars($item['name']) . ' &times; ' . (int) $item['qty'] . ' <span style="float:right">$' . number_format($item['price'] * $item['qty'], 2) . '</span><br>';
		$lines .= '<br>Subtotal <span style="float:right">$' . number_format((float) $o->subtotal, 2) . '</span><br>'
				. ($o->has_physical ? 'Shipping <span style="float:right">$' . number_format((float) $o->shipping, 2) . '</span><br>' : '')
				. 'Tax <span style="float:right">$' . number_format((float) $o->tax, 2) . '</span><br>'
				. '<strong>Total <span style="float:right">$' . number_format((float) $o->total, 2) . ' ' . htmlspecialchars($o->currency) . '</span></strong>';
		return $lines;
	}

	// download links for digital lines, once the order is paid. one file links the item's name, several link each
	// file by its name (Download Vibe City: game-x64.zip · game-mac.dmg)
	private function downloads() {
		if (!$this->order->downloadsReady()) return '';
		$out = '';
		foreach ($this->order->items as $item) {
			$files = $item['digital'] ? download_files($item['download_url']) : array();
			if (!$files) continue;
			$links = array();
			foreach ($files as $n => $file) {
				$label = count($files) > 1 ? download_name($file) : $item['name'];
				$links[] = '<a href="' . htmlspecialchars($this->link . '/download/' . (int) $item['id'] . '/' . ($n + 1)) . '" style="color:#4f46e5">' . htmlspecialchars($label) . '</a>';
			}
			$out .= '<br>Download' . (count($files) > 1 ? ' ' . htmlspecialchars($item['name']) : '') . ': ' . implode(' &middot; ', $links);
		}
		return $out;
	}
}
