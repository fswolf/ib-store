<?php 

class NonceLibrary {
	public $life = 86400; //24 hours defualt
	public $action = "none";
	public $id = 0;
	public $offset = 0;
	public $salt = "salt";

	public function __construct($properties = []) {
		if(!empty($properties)) 
			foreach($properties as $key => $value) $this->{$key} = $value;
	}

	private function tick() {
		return ceil(time() / ($this->life / 2));
	}

	public function create() {
		return substr(hash_hmac('sha512', ($this->tick() - $this->offset) . $this->action . $this->id, $this->salt), -12, 10);
	}
	
	public function verify($nonce) {
		if ($this->create($this->action, $this->id, null, $this->salt) == $nonce) return 1; // nonce under half age
		if ($this->create($this->action, $this->id, 1, $this->salt) == $nonce) return 2; // nonce over half age
		return false; 
	}
}
?>