<?php
class AuthApiController extends ApiController {
		public function signin() {
		$result = array('success'=>true, 'error'=>'Submition error');
		$data = json_decode(file_get_contents("php://input"));

		if(empty($data->email) || empty($data->password) || empty($data->csrf_token)) $result['success'] = false;

		if($result['success']) {
			$session = new SessionLibrary();
			if($data->csrf_token == $session->get('csrf_token')) {
				try {
					$clean_email = filter_var($data->email, FILTER_SANITIZE_EMAIL);
					Flight::authLimit($clean_email);
					if(AccountsModel::login($clean_email, $data->password, isset($data->remember) && $data->remember)) {
						$session->set('csrf_token', NULL);
						unset($result['error']);
						// back to the page that sent them to signin (checkout mostly), home otherwise
						$result['redirect'] = $session->get('after_signin') ?: '/';
						$session->remove('after_signin');
					}
				}
				catch (Exception $e) {
					$result['success'] = false;
					$result['error'] = $e->getMessage();
				}
			}
			else {
				$result['success'] = false;
			}
		}
		echo json_encode($result);
	}

	// POST {username, email, password, confirm, csrf_token}. signs them in when it works
	public function register() {
		$this->checkOrigin();
		$this->respond(function() {
			$data = $this->input();
			$this->checkCsrf($data);
			Flight::authLimit($data->email ?? '');

			// username and email are checked inside register()
			$password = AccountsModel::checkNewPassword($data->password ?? '', $data->confirm ?? '');
			$account = AccountsModel::register($data->username ?? '', $data->email ?? '', $password);
			AccountsModel::login($account->email, $password);
			$this->verifyMail($account);
			$this->session->set('csrf_token', null);
			return ['redirect' => '/'];
		});
	}

	// the confirm your email mail after sign up. best effort: a mail problem goes in the log, the account is made either way
	private function verifyMail($account) {
		if (!MailLibrary::enabled()) return;
		$info = StoreInfoModel::getBase();
		$link = base_url() . '/verify/' . (int) $account->id . '/' . $account->verifyToken();
		$body = MailLibrary::card($info->store_name, 'Confirm Your Email',
			'Thanks for making an account. Click the button below to confirm this is your email address.<br><br><span style="font-size:12px;opacity:0.65;">If you did not sign up, ignore this email.</span>',
			'Confirm Email', $link);

		$mail = new MailLibrary(['from_name' => $info->store_name]);
		try {
			$mail->send($account->email, $info->store_name . ' email confirmation', $body, true);
		}
		catch (Exception $e) {
			error_log('Verify mail failed: ' . $e->getMessage());
		}
	}

	public function signout() {
		AccountsModel::logout();
		$this->json(['success' => true]);
	}

	// POST {email, csrf_token}. always says ok so nobody can fish for registered emails
	public function forgot() {
		$this->checkOrigin();
		$this->respond(function() {
			$data = $this->input();
			$this->checkCsrf($data);
			if (!MailLibrary::enabled()) throw new Exception('Password resets are not available on this server. Contact the store.');

			$email = filter_var(trim((string) ($data->email ?? '')), FILTER_SANITIZE_EMAIL);
			Flight::authLimit($email);
			$account = filter_var($email, FILTER_VALIDATE_EMAIL) ? AccountsModel::getAccountByEmail($email) : false;

			if ($account) {
				$token = PasswordResetsModel::create($account->id);
				$info = StoreInfoModel::getBase();
				$link = base_url() . '/reset/' . $token;
				$body = MailLibrary::card($info->store_name, 'Password Reset Request',
					'We received a request to reset the password on your account.<br>If this was you, click the button below to choose a new one. The link works for ' . round(RESET_LIFE / 60) . ' minutes.<br><br><span style="font-size:12px;opacity:0.65;">If you did not ask for this, ignore this email and nothing changes.</span>',
					'Reset Password', $link);

				$mail = new MailLibrary(['from_name' => $info->store_name]);
				try {
					$mail->send($account->email, $info->store_name . ' password reset', $body, true);
				}
				catch (Exception $e) {
					error_log('Reset mail failed: ' . $e->getMessage());
					throw new Exception('Could not send the email right now, try again later.');
				}
			}

			$this->session->set('csrf_token', null);
			return ['message' => 'If that email has an account, a reset link is on its way.'];
		});
	}

	// POST {token, password, confirm, csrf_token}
	public function reset() {
		$this->checkOrigin();
		$this->respond(function() {
			$data = $this->input();
			$this->checkCsrf($data);
			Flight::authLimit();

			$account = PasswordResetsModel::check((string) ($data->token ?? ''));
			if (!$account) throw new Exception('That reset link is not valid any more, ask for a new one.');

			$password = AccountsModel::checkNewPassword($data->password ?? '', $data->confirm ?? '');

			$account->password = AccountsModel::hashPassword($password);
			$account->save();
			PasswordResetsModel::clear($account->id);

			// any other signed in session of theirs is dropped too
			$db = new DatabaseLibrary();
			$delete = $db->pdo->prepare('DELETE FROM `login_sessions` WHERE user_id = :user_id');
			$delete->bindValue(':user_id', (int) $account->id, PDO::PARAM_INT);
			$delete->execute();

			$this->session->set('csrf_token', null);
			return ['redirect' => '/signin', 'message' => 'Password changed, sign in with the new one.'];
		});
	}
}
