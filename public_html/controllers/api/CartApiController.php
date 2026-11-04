<?php
// /api/cart/* : the session cart and checkout
class CartApiController extends ApiController {
    public function handle() {
        $parts = $this->parts();
        $cart = new CartLibrary();

        // everything but the read changes the cart, so it has to come from our own pages
        if ($parts[0] !== 'items') $this->checkOrigin();

        switch ($parts[0]) {
            // GET, what is in the cart right now
            case 'items':
                $this->respond(fn() => ['cart' => $cart->toArray()]);
                break;

            // POST {id, qty}
            case 'add':
                $this->respond(function() use ($cart) {
                    $data = $this->input();
                    $qty = $cart->add((int) ($data->id ?? 0), (int) ($data->qty ?? 1));
                    return ['qty' => $qty, 'count' => $cart->count(), 'cart' => $cart->toArray()];
                });
                break;

            // POST {id, qty}, qty 0 removes
            case 'update':
                $this->respond(function() use ($cart) {
                    $data = $this->input();
                    $cart->update((int) ($data->id ?? 0), (int) ($data->qty ?? 1));
                    return ['count' => $cart->count(), 'cart' => $cart->toArray()];
                });
                break;

            // POST {id}
            case 'remove':
                $this->respond(function() use ($cart) {
                    $data = $this->input();
                    $cart->remove((int) ($data->id ?? 0));
                    return ['count' => $cart->count(), 'cart' => $cart->toArray()];
                });
                break;

            case 'clear':
                $this->respond(function() use ($cart) {
                    $cart->clear();
                    return ['count' => 0, 'cart' => $cart->toArray()];
                });
                break;

            // POST {billing: {...}, shipping: {...}, same_address, notes, csrf_token}
            // makes a pending order and clears the cart. payment comes after, on /cart/payment/<uuid>
            case 'checkout':
                $user = $this->requireLogin();
                $this->respond(function() use ($cart, $user) {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    if (!$cart->count()) throw new Exception('Your cart is empty');

                    $billing = (new AddressesModel([], 'billing'))->fill($data->billing ?? new stdClass());
                    $billing->account_id = $user->id;
                    $billing->email = $billing->email ?: $user->email; // account_billing.email is NOT NULL

                    $shipping = null;
                    if ($cart->hasPhysical()) {
                        $shipping = (new AddressesModel([], 'shipping'));
                        $shipping->fill(!empty($data->same_address) ? $data->billing : ($data->shipping ?? new stdClass()));
                        $shipping->account_id = $user->id;
                        $shipping->email = $shipping->email ?: $user->email;
                    }

                    // keep the addresses as the account defaults for next time. saved before the order so a
                    // bad address never leaves a half done order behind
                    $billing->save();
                    if ($shipping) $shipping->save();

                    $notes = mb_substr(trim((string) ($data->notes ?? '')), 0, 2000);
                    $order = OrdersModel::create($user, $cart, $billing, $shipping, $notes);

                    $cart->clear();
                    $this->session->set('csrf_token', null);
                    OrderMailLibrary::placed($order);
                    return ['order' => $order->uuid, 'redirect' => '/cart/payment/' . $order->uuid];
                });
                break;

            default:
                Flight::halt(404);
        }
    }

}
