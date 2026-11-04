<?php
class StoreApiController {
    private function respond(callable $callback) {
        header('Content-Type: application/json');
        $result = ['success' => true, 'info' => null, 'error' => 'Failed to load.'];
        try {
            $info = $callback();
            if ($info) {
                $result['info'] = $info;
                unset($result['error']);
            }
        } catch (Throwable $e) {
            error_log('Store API: ' . $e->getMessage());
            $result['success'] = false;
            http_response_code(500);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function handle() {
        $route = trim(strtok(Flight::router()->executedRoute->splat, '?'), '/');
        $parts = explode('/', $route);

        switch ($parts[0]) {
            case 'info':
                $this->respond(fn() => StoreInfoModel::getContact());
                break;
            default:
                Flight::halt(404);
        }
    }
}
