<?php
// the markdown product descriptions use, what the admin editor's toolbar writes: **bold**, *italic*, ~~struck~~,
// [text](https://...), bare https:// links, - * or • lists, 1. lists (counting from the first number), # to ###### headings,
// > quotes, --- lines, a blank line between paragraphs.
// the text is escaped first, so the only tags in the result are the ones built here
class MarkdownLibrary {
	public static function render($text) {
		$html = array();
		$type = null;
		$lines = array();
		$start = 1; // the first number of a numbered list
		foreach (preg_split('/\R/', htmlspecialchars(trim((string) $text), ENT_QUOTES)) as $line) {
			$line = trim($line);
			if ($line === '') $next = null;
			elseif (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) $next = 'hr';
			elseif (preg_match('/^(#{1,6}) +(.+)$/', $line, $m)) { $next = strlen($m[1]) < 3 ? 'h3' : 'h4'; $line = $m[2]; } // the page's h1 and h2 are taken
			elseif (preg_match('/^&gt; ?(.*)$/', $line, $m)) { $next = 'blockquote'; $line = $m[1]; }
			elseif (preg_match('/^[-*•] +(.+)$/u', $line, $m)) { $next = 'ul'; $line = $m[1]; }
			elseif (preg_match('/^(\d+)[.)] +(.+)$/', $line, $m)) { $next = 'ol'; $line = $m[2]; }
			else $next = 'p';

			// a new kind of line ends the block, a heading is always a block of its own
			if ($next !== $type || in_array($next, array('h3', 'h4', 'hr'))) {
				$html[] = self::block($type, $lines, $start);
				$type = $next;
				$lines = array();
				if ($next === 'ol') $start = (int) $m[1];
			}
			if ($next) $lines[] = $next === 'hr' ? '' : self::inline($line);
		}
		$html[] = self::block($type, $lines, $start);
		return implode("\n", array_filter($html));
	}

	private static function block($type, $lines, $start) {
		if (!$type || !$lines) return '';
		if ($type === 'hr') return '<hr>';
		if ($type === 'p') return '<p>' . implode("<br>\n", $lines) . '</p>';
		if ($type === 'blockquote') return ($lines = array_filter($lines, 'strlen')) ? '<blockquote><p>' . implode("<br>\n", $lines) . '</p></blockquote>' : '';
		if ($type === 'ul') return '<ul><li>' . implode('</li><li>', $lines) . '</li></ul>';
		if ($type === 'ol') return '<ol' . ($start > 1 ? ' start="' . $start . '"' : '') . '><li>' . implode('</li><li>', $lines) . '</li></ol>';
		return "<$type>" . $lines[0] . "</$type>";
	}

	// links in one pass so a url inside [text](url) is not linked twice, then bold and italic
	private static function inline($line) {
		// a url stops at a space or an escaped quote or bracket, a bare one also leaves trailing punctuation outside
		$char = '(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<)*])';
		$line = preg_replace_callback('~\[([^\]]+)\]\(((?:https?://|mailto:|/(?!/))' . $char . '+)\)|\bhttps?://' . $char . '*[^\s<.,!?;:)&]~i', function($m) {
			$url = $m[2] ?? $m[0];
			$label = isset($m[2]) ? $m[1] : $m[0];
			$external = preg_match('#^https?://#i', $url) ? ' target="_blank" rel="noopener"' : '';
			return '<a href="' . $url . '"' . $external . '>' . $label . '</a>';
		}, $line);
		$line = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $line);
		$line = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/', '<del>$1</del>', $line);
		return preg_replace('/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])/', '<em>$1</em>', $line);
	}
}
