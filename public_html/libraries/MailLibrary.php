<?php
// outgoing mail the same way imbored.me does it: php mail() through the server's mta, nothing to configure
// past the from address in config.php. send() takes a plain text body, card() wraps html in the site style
class MailLibrary {
	public $from = MAIL_FROM;
	public $from_name = MAIL_FROM_NAME;
	public $reply_to = ''; // defaults to $from, order mails set it to the store's contact address

	public function __construct($properties = []) {
		foreach($properties as $key => $value) $this->{$key} = $value;
	}

	public static function enabled() {
		return function_exists('mail') && MAIL_FROM !== '';
	}

	// returns what mail() returns: true when the mta took it, which is not the same as delivered
	public function send($to, $subject, $body, $html = false) {
		if (!self::enabled()) throw new Exception('Mail is not set up on this server');
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new Exception('Bad recipient address');

		$from = $this->from_name !== '' ? $this->encode($this->from_name) . ' <' . $this->from . '>' : $this->from;
		$headers  = "From: $from\r\n";
		$headers .= 'Reply-To: ' . ($this->reply_to !== '' ? $this->reply_to : $this->from) . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= 'Content-type: ' . ($html ? 'text/html' : 'text/plain') . "; charset=UTF-8\r\n";

		if (!mail($to, $this->encode($subject), $body, $headers)) throw new Exception('The mail server did not accept the message');
		return true;
	}

	// the dark card imbored.me's reset mail uses. $lines is html already (escape what came from users)
	public static function card($title, $heading, $lines, $button = null, $link = null) {
		$action = $button && $link
			? '<tr><td align="center" style="padding-bottom:20px;"><a href="' . htmlspecialchars($link) . '" style="display:inline-block;padding:12px 22px;border-radius:22px;background:#d4af37;color:#1a1a1a;text-decoration:none;font-weight:600;font-size:14px;border:1px solid rgba(212,175,55,0.6);">' . htmlspecialchars($button) . '</a></td></tr>'
			: '';

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($heading) . '</title></head>
		<body style="margin:0;padding:0;background:#1a1a1a;font-family:Segoe UI,Arial,sans-serif;color:#f8f9fa;">
			<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#2b2b2b,#3a2f4a);padding:40px 15px;">
				<tr><td align="center">
					<table width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:rgba(50,50,50,0.35);border:1px solid rgba(255,255,255,0.15);border-radius:14px;padding:28px 26px;box-shadow:0 10px 35px rgba(0,0,0,0.5);">
						<tr><td style="text-align:center;font-size:20px;font-weight:600;padding-bottom:6px;">' . htmlspecialchars($title) . '</td></tr>
						<tr><td style="text-align:center;font-size:13px;opacity:0.65;padding-bottom:18px;">' . htmlspecialchars($heading) . '</td></tr>
						<tr><td style="font-size:14px;line-height:1.6;padding-bottom:18px;">' . $lines . '</td></tr>
						' . $action . '
						<tr><td style="font-size:11px;opacity:0.45;text-align:center;padding-top:10px;border-top:1px solid rgba(255,255,255,0.08);">&copy; ' . date('Y') . ' ' . htmlspecialchars($title) . '</td></tr>
					</table>
				</td></tr>
			</table>
		</body></html>';
	}

	private function encode($text) {
		if (preg_match('/[^\x20-\x7e]/', $text)) return '=?UTF-8?B?' . base64_encode($text) . '?=';
		return $text;
	}
}
