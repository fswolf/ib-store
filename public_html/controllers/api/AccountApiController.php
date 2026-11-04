<?php
// /api/account/* : the signed in user's own settings
class AccountApiController extends ApiController {
    public function handle() {
        $parts = $this->parts();
        $this->checkOrigin();
        $user = $this->requireLogin();

        switch ($parts[0]) {
            // POST {username, email, csrf_token}
            case 'profile':
                $this->respond(function() use ($user) {
                    $data = $this->input();
                    $this->checkCsrf($data);

                    $user->username = AccountsModel::checkUsername($data->username ?? '', $user);
                    $user->email = AccountsModel::checkEmail($data->email ?? '', $user);
                    $user->save();
                    return ['message' => 'Saved.'];
                });
                break;

            // POST {current, password, confirm, csrf_token}
            case 'password':
                $this->respond(function() use ($user) {
                    $data = $this->input();
                    $this->checkCsrf($data);

                    if (!$user->checkPassword((string) ($data->current ?? ''))) throw new Exception('Current password is wrong');
                    $password = AccountsModel::checkNewPassword($data->password ?? '', $data->confirm ?? '');

                    $user->password = AccountsModel::hashPassword($password);
                    $user->save();
                    return ['message' => 'Password changed.'];
                });
                break;

            // GET, both default addresses
            case 'addresses':
                $this->respond(function() use ($user) {
                    $billing = AddressesModel::getDefault($user->id, 'billing');
                    $shipping = AddressesModel::getDefault($user->id, 'shipping');
                    return [
                        'billing' => $billing ? $billing->toArray() : null,
                        'shipping' => $shipping ? $shipping->toArray() : null,
                    ];
                });
                break;

            // POST {type: billing|shipping, first_name, ..., csrf_token}
            case 'address':
                $this->respond(function() use ($user) {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $type = ($data->type ?? '') === 'shipping' ? 'shipping' : 'billing';

                    $address = (new AddressesModel([], $type))->fill($data);
                    $address->account_id = $user->id;
                    $address->email = $address->email ?: $user->email; // account_billing.email is NOT NULL
                    $address->save();
                    return ['message' => ucfirst($type) . ' address saved.', 'address' => $address->toArray()];
                });
                break;

            default:
                Flight::halt(404);
        }
    }
}
