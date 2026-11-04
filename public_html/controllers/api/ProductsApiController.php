<?php 
class ProductsApiController {
    private function respond(callable $callback) {
        header('Content-Type: application/json');
        $result = ['success' => true,'products' => [],'error' => 'Failed to load.'];
        try {
            $products = $callback();
            if ($products) {
                $result['products'] = $products;
                unset($result['error']);
            }
        } catch (Throwable $e) {
            error_log('Products API: ' . $e->getMessage());
            $result['success'] = false;
            http_response_code(500);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function handle() {
        // splat keeps the query string (?q=...), so drop it before splitting
        $route = trim(strtok(Flight::router()->executedRoute->splat, '?'), '/');
        $parts = explode('/', $route);

        switch ($parts[0]) {
            case 'newest':
                $this->respond(fn() => ProductsModel::getNewest());
                break;
            case 'featured':
                $this->respond(fn() => ProductsModel::getFeatured());
                break;
            case 'browse':
                $this->respond(fn() => ProductsModel::getAll());
                break;
            case 'category':
                $name = $parts[1] ?? '';
                if (!preg_match('/^[a-z0-9-]+$/', $name)) {
                    Flight::halt(404);
                }
                $this->respond(fn() => ProductsModel::getCategory($name));
                break;
            case 'search':
                $term = trim($_GET['q'] ?? '');
                if ($term === '' || strlen($term) > 100) {
                    Flight::halt(404);
                }
                $this->respond(fn() => ProductsModel::search($term));
                break;
            default:
                Flight::halt(404);
        }
    }
}