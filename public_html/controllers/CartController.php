<?php
class CartController extends BaseController {
    public function cart() {
        $this->setGlobals();

        // cart.js pulls the items from /api/cart/items, the page just needs the shell
        $this->view->template = 'cart/cart.html';
        $this->view->render();
    }

    // /cart/checkout needs an account, an empty cart goes back to the cart page
    public function check_out() {
        $this->requireLogin();
        $this->setGlobals();

        $cart = new CartLibrary();
        if (!$cart->count()) {
            Flight::redirect('/cart');
            return;
        }

        $this->csrfToken();
        $this->view->set('cart', $cart->toArray());
        $this->view->set('billing', AddressesModel::getDefault($this->user->id, 'billing'));
        $this->view->set('shipping', AddressesModel::getDefault($this->user->id, 'shipping'));

        $this->view->template = 'cart/shipping.html';
        $this->view->render();
    }

    // /cart/payment/@order shows the pending order. the actual payment step is still to come
    public function payment($order_id) {
    	$this->requireLogin();
    	$this->setGlobals();

        $order = OrdersModel::getByUuid($order_id);
        if (!$order || ($order->account_id != $this->user->id && !$this->user->is_admin)) {
            Flight::redirect('/404');
            return;
        }
        $order->getItems();
        $this->csrfToken();
        $this->view->set('order', $order);
        $this->view->set('gateways', PaymentLibrary::enabledList()); // name => button label, the ones switched on

        $this->view->template = 'cart/payment.html';
        $this->view->render();
    }
}
