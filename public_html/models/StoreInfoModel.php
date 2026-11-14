<?php

class StoreInfoModel {
	public $id;
	public $store_name;
	public $company_name;
	public $email;
	public $phone;
	public $address_line1;
	public $address_line2;
	public $city;
	public $state_province;
	public $postal_code;
	public $country;
	public $website;
	public $currency;
	public $tax_rate;
	public $tax_home_only;
	public $shipping_flat;
	public $free_shipping_over;
	public $timezone;
	public $logo;
	public $favicon;
	public $hero_image;
	public $hero_heading;
	public $hero_text;
	public $hero_button;
	public $hero_link;
	public $payments_off;
	public $created_at;
	public $updated_at;

	public function __construct($properties = []) {
		foreach ($properties as $key => $value) {
			$this->{$key} = $value;
		}
	}

	public static function getAll() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT * FROM `store_info` LIMIT 1');
		$select->execute();
		$record = $select->fetch(); // was fetchAll, which wrapped the row in another array

		if($record) return new StoreInfoModel($record);
		return false;
	}

	public static function getBase() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT store_name, company_name, currency, favicon FROM `store_info` LIMIT 1');
		$select->execute();
		$record = $select->fetch();

		if($record) return new StoreInfoModel($record);
		return false;
	}

	public static function getContact() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT store_name, company_name, email, phone, address_line1, address_line2, city, state_province, postal_code, country, website, currency, timezone, logo FROM `store_info` LIMIT 1');
		$select->execute();
		$record = $select->fetch();

		if($record) return new StoreInfoModel($record);
		return false;
	}

	// the home page banner. false when no image is set, so the view can skip the whole section
	public static function getHero() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT hero_image, hero_heading, hero_text, hero_button, hero_link FROM `store_info` LIMIT 1');
		$select->execute();
		$record = $select->fetch();

		if($record && $record['hero_image']) return new StoreInfoModel($record);
		return false;
	}

	// what checkout needs to total a cart
	public static function getRates() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT currency, tax_rate, tax_home_only, country, shipping_flat, free_shipping_over FROM `store_info` LIMIT 1');
		$select->execute();
		$record = $select->fetch();

		if($record) return new StoreInfoModel($record);
		return new StoreInfoModel(array('currency' => 'USD', 'tax_rate' => 0, 'tax_home_only' => 0, 'country' => null, 'shipping_flat' => 0, 'free_shipping_over' => null));
	}

	// the columns the admin settings form is allowed to touch
	const FIELDS = array('store_name', 'company_name', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state_province', 'postal_code', 'country', 'website', 'currency', 'tax_rate', 'tax_home_only', 'shipping_flat', 'free_shipping_over', 'timezone', 'logo');

	public function fill($data) {
		$store_name = trim((string) ($data->store_name ?? ''));
		if ($store_name === '' || mb_strlen($store_name) > 150) throw new Exception('Store name is required');

		$currency = strtoupper(trim((string) ($data->currency ?? 'USD')));
		if (!preg_match('/^[A-Z]{3}$/', $currency)) throw new Exception('Currency is the 3 letter code, like USD or CAD');

		foreach (array('tax_rate', 'shipping_flat') as $field) {
			$value = (string) ($data->{$field} ?? '0');
			if ($value === '') $value = '0';
			if (!is_numeric($value) || $value < 0) throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' must be a number');
			$this->{$field} = number_format((float) $value, 2, '.', '');
		}

		$free = trim((string) ($data->free_shipping_over ?? ''));
		if ($free !== '' && (!is_numeric($free) || $free < 0)) throw new Exception('Free shipping over must be a number or empty');
		$this->free_shipping_over = $free === '' ? null : number_format((float) $free, 2, '.', '');

		// a checkbox, so it is only posted when ticked
		$this->tax_home_only = empty($data->tax_home_only) ? 0 : 1;

		$this->store_name = $store_name;
		$this->currency = $currency;
		foreach (array('company_name', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state_province', 'postal_code', 'country', 'website', 'timezone', 'logo') as $field) {
			$value = trim((string) ($data->{$field} ?? ''));
			if (mb_strlen($value) > 255) throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is too long');
			$this->{$field} = $value === '' ? null : $value;
		}
		if ($this->timezone === null) $this->timezone = 'UTC';
		return $this;
	}

	public function update($data = null) {
		$db = new DatabaseLibrary();

		$sql = "UPDATE store_info SET
			store_name = :store_name,
			company_name = :company_name,
			email = :email,
			phone = :phone,
			address_line1 = :address_line1,
			address_line2 = :address_line2,
			city = :city,
			state_province = :state_province,
			postal_code = :postal_code,
			country = :country,
			website = :website,
			currency = :currency,
			tax_rate = :tax_rate,
			tax_home_only = :tax_home_only,
			shipping_flat = :shipping_flat,
			free_shipping_over = :free_shipping_over,
			timezone = :timezone,
			logo = :logo
			WHERE id = :id
			LIMIT 1";

		// no array passed: save this object (after fill())
		if ($data === null) {
			$data = array(':id' => (int) $this->id);
			foreach (self::FIELDS as $field) $data[':' . $field] = $this->{$field};
		}

		$stmt = $db->pdo->prepare($sql);
		return $stmt->execute($data);
	}

	// -- Home page banner -- //
	// the banner has its own form in the admin panel, so its words are filled and saved apart from the settings above
	const HERO_FIELDS = array('hero_heading' => 150, 'hero_text' => 255, 'hero_button' => 50, 'hero_link' => 255);

	public function fillHero($data) {
		foreach (self::HERO_FIELDS as $field => $max) {
			$value = trim((string) ($data->{$field} ?? ''));
			if (mb_strlen($value) > $max) throw new Exception(ucfirst(str_replace('hero_', '', $field)) . ' is too long');
			$this->{$field} = $value === '' ? null : $value;
		}
		// the button goes in an href, so only a path on this site or a full http(s) url
		if ($this->hero_link !== null && !preg_match('#^(/|https?://)#i', $this->hero_link)) throw new Exception('Button link must start with / or http');
		return $this;
	}

	public function updateHero() {
		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare('UPDATE `store_info` SET hero_heading = :hero_heading, hero_text = :hero_text, hero_button = :hero_button, hero_link = :hero_link LIMIT 1');
		foreach (self::HERO_FIELDS as $field => $max) $update->bindValue(":$field", $this->{$field}, $this->{$field} === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
		return $update->execute();
	}

	// -- Payment switches -- //
	// gateways switched off in the admin panel, by their PaymentLibrary::all() name. one with its keys in config is on
	// unless it is in here, so a new gateway needs no column
	public static function getPaymentsOff() {
		$db = new DatabaseLibrary();
		$select = $db->pdo->prepare('SELECT payments_off FROM `store_info` LIMIT 1');
		$select->execute();
		$value = (string) $select->fetchColumn();
		return $value === '' ? array() : explode(',', $value);
	}

	public static function setPaymentsOff($names) {
		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare('UPDATE `store_info` SET payments_off = :payments_off LIMIT 1');
		$update->bindValue(':payments_off', implode(',', $names), PDO::PARAM_STR);
		return $update->execute();
	}

	// the banner image and the tab icon are set by their uploaders, so they have their own writes. null clears
	public static function setHeroImage($path) { return self::setPath('hero_image', $path); }
	public static function setFavicon($path)   { return self::setPath('favicon', $path); }

	private static function setPath($column, $path) {
		$db = new DatabaseLibrary();
		$update = $db->pdo->prepare("UPDATE `store_info` SET `$column` = :path LIMIT 1");
		$update->bindValue(':path', $path, $path === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
		return $update->execute();
	}
}