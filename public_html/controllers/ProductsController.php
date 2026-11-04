<?php
class ProductsController extends BaseController {
    public function index() {
    	$this->setGlobals();

        $this->view->template = 'products/index.html';
        $this->view->render();
    }

    // /item/@uuid, the route param is really the slug (or an id for old links)
    public function product($uuid) {
    	$this->setGlobals();

        $product = ProductsModel::getBySlug($uuid);
        if (!$product || (!$product->active && !($this->user && $this->user->is_admin))) {
            Flight::redirect('/404');
            return;
        }

        $this->view->set('product', $product);
        $this->view->set('images', ProductImgModel::getAll($product->id));
        $this->view->set('related', ProductsModel::getRelated($product));

        $this->view->template = 'products/product.html';
        $this->view->render();
    }

    public function category() {
        $this->setGlobals();

        $route = Flight::router()->executedRoute;
        $cat = $route->splat;

        $list = array_column($this->categories, 'route');
        if (!in_array($cat, $list)) {
            Flight::redirect('/404');
            return; // redirect sends the headers but does not stop the script
        }

        foreach ($this->categories as $item) {
            if ($item->route === $cat) {
                $this->view->set('category', $item->name);
                break;
            }
        }

        $this->view->template = 'products/index.html';
        $this->view->render();
    }

    public function search($item) {
        $this->setGlobals();
        $this->view->set('category', 'Search: ' . $item);

        $this->view->template = 'products/index.html';
        $this->view->render();
    }
}
