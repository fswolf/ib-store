<?php
// POST /api/payment/<gateway>/<action> : the payment step, the same three actions for every gateway.
// the gateway specifics live in libraries/*Library.php behind PaymentLibrary; this only knows the shape.
// webhook is called by the gateway, start and status by payment.js on /cart/payment/<uuid>
class PaymentApiController extends ApiController {
    public function handle() {
        $parts = $this->parts();
        $gateway = PaymentLibrary::get($parts[0] ?? '');
        if (!$gateway) Flight::halt(404); // unknown name, or switched off in config

        switch ($parts[1] ?? '') {
            // POST {order, csrf_token}: begin (or pick up) the payment, answer with where to send the customer
            case 'start':
                $this->checkOrigin();
                $this->respond(function() use ($gateway) {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $order = $this->order($data->order ?? '');
                    if ($order->status !== 'pending') throw new Exception('This order is not waiting on payment');

                    $redirect = base_url() . '/cart/payment/' . $order->uuid;
                    return ['redirect' => $this->ask($gateway, fn() => $gateway->start($order, $redirect))];
                });
                break;

            // POST {order}: where the payment is at. marks the order paid when the gateway says so and the
            // webhook never made it (or, for paypal, captures on the way)
            case 'status':
                $this->checkOrigin();
                $this->respond(function() use ($gateway) {
                    $data = $this->input();
                    $order = $this->order($data->order ?? '');
                    if ($order->status !== 'pending' || $order->payment_method !== $gateway::METHOD || !$order->payment_ref) return ['order' => $order->status, 'state' => null];

                    $current = $this->ask($gateway, fn() => $gateway->check($order));
                    if ($current['paid']) $gateway->settle($order, $order->payment_ref);
                    return ['order' => $order->status, 'state' => $current['state'], 'open' => $current['open']];
                });
                break;

            // the gateway posts here. no session, no csrf, the signature is the auth. a bad one answers 400 so the
            // gateway lists the delivery as failed instead of done
            case 'webhook':
                try {
                    $hook = $gateway->webhook(file_get_contents('php://input'), $_SERVER);
                }
                catch (Exception $e) {
                    error_log($gateway::METHOD . ' webhook: ' . $e->getMessage());
                    $this->json(['success' => false, 'error' => $e->getMessage()], 400);
                    break;
                }
                $this->respond(function() use ($gateway, $hook) {
                    if (!$hook['order']) return ['ignored' => 'not one of our orders'];
                    if ($hook['paid']) $gateway->settle($hook['order'], $hook['order']->payment_ref);
                    return ['order' => $hook['order']->status];
                });
                break;

            default: Flight::halt(404);
        }
    }

    // the signed in customer's own order, or any order for an admin
    private function order($uuid) {
        $user = $this->requireLogin();
        $order = OrdersModel::getByUuid((string) $uuid);
        if (!$order || ($order->account_id != $user->id && !$user->is_admin)) throw new Exception('Order not found');
        return $order;
    }

    // a gateway call: whatever goes wrong over there is logged, the customer gets one plain sentence
    private function ask($gateway, callable $call) {
        try {
            return $call();
        }
        catch (Exception $e) {
            error_log($gateway::METHOD . ': ' . $e->getMessage());
            throw new Exception('Could not reach the payment server, try again in a moment.');
        }
    }
}
