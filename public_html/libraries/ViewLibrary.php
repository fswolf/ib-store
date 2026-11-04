<?php

class ViewLibrary {
	public $template;
	public $variables;

	public function set($key, $value) {
		$this->variables[$key] = $value;
	}

	public function render() {
		$path = 'views/' . $this->template;
		if (file_exists($path)) {
			ob_start();
			
			if ($this->variables) extract($this->variables);
			require($path);

			ob_end_flush();
		}
	}
}