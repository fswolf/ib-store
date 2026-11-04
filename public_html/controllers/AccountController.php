<?php
// /user/* : the signed in customer's pages
class AccountController extends BaseController {
    // /user/settings : username, email, password, saved addresses
    public function settings() {
        $this->requireLogin();
        $this->setGlobals();
        $this->csrfToken();

        $this->view->set('billing', AddressesModel::getDefault($this->user->id, 'billing'));
        $this->view->set('shipping', AddressesModel::getDefault($this->user->id, 'shipping'));

        $this->view->template = 'profile.html';
        $this->view->render();
    }

    // /user/orders : every order on the account
    public function orders() {
        $this->requireLogin();
        $this->setGlobals();

        $this->view->set('orders', OrdersModel::getByAccount($this->user->id));

        $this->view->template = 'purchases.html';
        $this->view->render();
    }

    // /user/orders/@uuid : one order with its lines and downloads once paid
    public function order($uuid) {
        $this->requireLogin();
        $this->setGlobals();

        $order = OrdersModel::getByUuid($uuid);
        if (!$order || $order->account_id != $this->user->id) {
            Flight::redirect('/404');
            return;
        }
        $order->getItems();
        $this->view->set('order', $order);

        $this->view->template = 'order.html';
        $this->view->render();
    }
}
