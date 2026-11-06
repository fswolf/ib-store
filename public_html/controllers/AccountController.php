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

    // /user/orders/@uuid/download/@item : a digital line's file, for the order's owner (or an admin) once it is paid.
    // files sit in DOWNLOAD_DIR outside public_html so this is the only way to them. a full url (a file hosted
    // somewhere else) is a redirect, that host decides who gets in
    public function download($uuid, $item) {
        $this->requireLogin();

        $order = OrdersModel::getByUuid($uuid);
        if (!$order || ($order->account_id != $this->user->id && !$this->user->is_admin) || !$order->downloadsReady()) {
            Flight::redirect('/404');
            return;
        }

        $line = null;
        foreach ($order->getItems() as $row) {
            if ((int) $row['id'] === (int) $item && $row['digital'] && $row['download_url']) $line = $row;
        }
        if (!$line) {
            Flight::redirect('/404');
            return;
        }

        if (preg_match('#^https?://#i', $line['download_url'])) {
            Flight::redirect($line['download_url']);
            return;
        }

        $file = download_path($line['download_url']);
        if (!$file) {
            error_log('Download missing for order ' . $order->uuid . ': ' . $line['download_url']);
            Flight::redirect('/404');
            return;
        }

        // flight buffers output, a big file goes straight out instead
        while (ob_get_level()) ob_end_clean();
        set_time_limit(0);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($file)) . '"');
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }
}
