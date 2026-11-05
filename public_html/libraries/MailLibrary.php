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

	// a card in the store's own theme (styles.css light palette). solid colours only, set on every cell:
	// mail clients drop body styles, gradients and rgba, so nothing here leans on them. $lines is html already
	public static function card($title, $heading, $lines, $button = null, $link = null) {
		$font = "system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
		$action = $button && $link
			? '<tr><td align="center" style="padding-bottom:20px;"><a href="' . htmlspecialchars($link) . '" style="display:inline-block;padding:12px 22px;border-radius:8px;background-color:#4f46e5;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;font-family:' . $font . ';">' . htmlspecialchars($button) . '</a></td></tr>'
			: '';

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($heading) . '</title></head>
		<body style="margin:0;padding:0;background-color:#f5f7fa;font-family:' . $font . ';color:#333333;">
			<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f5f7fa" style="background-color:#f5f7fa;padding:40px 15px;">
				<tr><td align="center">
					<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="max-width:480px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:28px 26px;">
						<tr><td style="text-align:center;font-family:' . $font . ';font-size:20px;font-weight:600;color:#1e293b;padding-bottom:6px;">' . htmlspecialchars($title) . '</td></tr>
						<tr><td style="text-align:center;font-family:' . $font . ';font-size:13px;color:#64748b;padding-bottom:18px;">' . htmlspecialchars($heading) . '</td></tr>
						<tr><td style="font-family:' . $font . ';font-size:14px;line-height:1.6;color:#333333;padding-bottom:18px;">' . $lines . '</td></tr>
						' . $action . '
						<tr><td style="font-family:' . $font . ';font-size:11px;color:#64748b;text-align:center;padding-top:10px;border-top:1px solid #e2e8f0;">&copy; ' . date('Y') . ' ' . htmlspecialchars($title) . '</td></tr>
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
