<?php
class BaseController {
	protected $session;
    protected $view;
    protected $user;

    protected $store_name;
    protected $company_name;
    protected $currency;
    protected $categories;

	public function __construct() {
		$this->session = new SessionLibrary();
		$this->view = new ViewLibrary();
	}

	protected function resumeLogin() {
		if ($this->user) return $this->user;
		if ($this->user = AccountsModel::resume()) return $this->user;
		return false;
	}

	protected function setGlobals($auth = true) {
	    $info = StoreInfoModel::getBase();
	    $this->categories = CategoriesModel::getAll();
	    $this->store_name = $info->store_name;
	    $this->company_name = $info->company_name;
	    $this->currency = $info->currency;

	    $this->view->set('store_name', $this->store_name);
	    $this->view->set('company_name', $this->company_name);
	    $this->view->set('currency', $this->currency);
	    $this->view->set('favicon', upload_url($info->favicon ?: 'assets/images/bored.png')); // the tab icon, uploaded in store settings
	    $this->view->set('categories', $this->categories);
	    $this->view->set('cart_count', (new CartLibrary())->count()); // the menu badge
	    if($auth) $this->view->set('logged', $this->resumeLogin());
	}

	// form token for the page, see form_handler.js (data-csrf) and ApiController::checkCsrf().
	// one per session, made when missing, so a page with several forms keeps working after the first save
	protected function csrfToken() {
		$token = $this->session->get('csrf_token');
		if (!$token) {
			$token = bin2hex(random_bytes(32));
			$this->session->set('csrf_token', $token);
		}
		$this->view->set('csrf_token', $token);
		return $token;
	}

	// pages that need an account. sends to signin and remembers where they were going
	protected function requireLogin() {
		if ($this->resumeLogin()) return $this->user;
		$this->session->set('after_signin', Flight::request()->url);
		Flight::redirect('/signin');
		exit;
	}

	// admin pages. not signed in goes to signin, signed in but not admin gets the 404 page
	protected function requireAdmin() {
		$this->requireLogin();
		if (!$this->user->is_admin) {
			Flight::redirect('/404');
			exit;
		}
		return $this->user;
	}

}
