<?php

class AuthController extends BaseController {
	public function signin() {
		$this->setGlobals(false);

	    // Clear server session
	    $this->session->set('loginSessionInfo', null);
	    $this->session->set('csrf_token', bin2hex(random_bytes(32)));

	    /* remember me cookie got deprecated, signin.js keeps the email in localStorage instead
	    // delete modern remember-me cookie
	    setcookie('__Host-loginSessionInfo', '', [
	        'expires'  => time() - 3600,
	        'path'     => '/',
	        'secure'   => true,
	        'httponly' => true,
	        'samesite' => 'Lax'
	    ]);
	    */

		//Render the login page
		$this->view->set('logged', false);
		$this->view->set('csrf_token', $this->session->get('csrf_token'));
		$this->view->template = 'signin.html';
		$this->view->render();
	}

	// the menu's sign out link, drops the db row and the session then goes home
	public function signout() {
		AccountsModel::logout();
		Flight::redirect('/');
	}

	public function register() {
		$this->setGlobals();
		if ($this->user) {
			Flight::redirect('/');
			return;
		}

		$this->csrfToken();
        $this->view->template = 'register.html';
        $this->view->render();
	}

	public function forgot() {
		$this->setGlobals();
		$this->csrfToken();
		$this->view->set('mail_enabled', MailLibrary::enabled());
        $this->view->template = 'forgot.html';
        $this->view->render();
	}

	// /reset/@token from the email
	public function reset($token) {
		$this->setGlobals();
		$this->csrfToken();
		$this->view->set('token', $token);
		$this->view->set('token_ok', PasswordResetsModel::check($token) !== false);
        $this->view->template = 'reset.html';
        $this->view->render();
	}
}
