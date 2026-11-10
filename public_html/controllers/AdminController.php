<?php
// /admin/* : store management, is_admin accounts only. the tables load through /api/admin/* (tablesorter.js)
class AdminController extends BaseController {
    private function page($template, $title) {
        $this->requireAdmin();
        $this->setGlobals();
        $this->csrfToken();
        $this->view->set('admin_title', $title);
        $this->view->template = 'admin/' . $template;
    }

    public function dashboard() {
        $this->page('dashboard.html', 'Dashboard');

        $this->view->set('stats', OrdersModel::stats());
        $this->view->set('product_count', ProductsModel::count());
        $this->view->set('account_count', AccountsModel::count());
        $this->view->set('recent', OrdersModel::getPage(8, 0));

        $this->view->render();
    }

    public function products() {
        $this->page('products.html', 'Products');
        $this->view->render();
    }

    // /admin/products/new and /admin/products/@id share the form
    public function product($id = null) {
        $this->page('product.html', $id ? 'Edit Product' : 'New Product');

        $product = $id ? ProductsModel::getProduct($id) : new ProductsModel(array('active' => 1, 'con' => 0, 'badge' => 0, 'stock' => 0, 'price' => '0.00'));
        if (!$product) {
            Flight::redirect('/admin/products');
            return;
        }

        $this->view->set('product', $product);
        $this->view->set('images', $id ? ProductImgModel::getAll($id) : array());
        $this->view->set('keys', $id ? ProductKeysModel::counts($id) : array(0, 0)); // [unused, total]
        $this->view->set('all_categories', CategoriesModel::getAllAdmin());

        $this->view->render();
    }

    public function categories() {
        $this->page('categories.html', 'Categories');
        $this->view->set('all_categories', CategoriesModel::getAllAdmin());
        $this->view->render();
    }

    public function orders() {
        $this->page('orders.html', 'Orders');
        $this->view->set('statuses', OrdersModel::STATUSES);
        $this->view->render();
    }

    public function order($uuid) {
        $this->page('order.html', 'Order');

        $order = OrdersModel::getByUuid($uuid);
        if (!$order) {
            Flight::redirect('/admin/orders');
            return;
        }
        $order->getItems();
        $this->view->set('order', $order);
        $this->view->set('account', AccountsModel::getAccount($order->account_id));
        $this->view->set('statuses', OrdersModel::STATUSES);

        $this->view->render();
    }

    public function accounts() {
        $this->page('accounts.html', 'Accounts');
        $this->view->render();
    }

    public function settings() {
        $this->page('settings.html', 'Store Settings');
        $this->view->set('info', StoreInfoModel::getAll());
        $this->view->set('mail_enabled', MailLibrary::enabled());
        $this->view->set('gateways', PaymentLibrary::enabledList()); // name => label, same registry the payment page uses
        // $this->view->set('btcpay_enabled', BtcpayLibrary::enabled());
        // $this->view->set('stripe_enabled', StripeLibrary::enabled());
        // $this->view->set('paypal_enabled', PaypalLibrary::enabled());
        $this->view->render();
    }
}
