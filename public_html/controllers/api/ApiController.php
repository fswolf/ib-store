<?php
// shared bits for the json api controllers: read the posted json, answer in json, check who is asking.
// ProductsApi / StoreApi predate this and keep their own respond(), nothing wrong with them
class ApiController {
    protected $session;
    protected $user;

    public function __construct() {
        $this->session = new SessionLibrary();
    }

    // posted json as an object, empty object when there is none
    protected function input() {
        $data = json_decode(file_get_contents('php://input'));
        return is_object($data) ? $data : new stdClass();
    }

    // the splat after the route prefix, split on /, query string dropped.
    // a route with no wildcard (GET /api/cart/items) has no splat, so take what follows /api/<name>/ in the url
    protected function parts() {
        $route = (string) Flight::router()->executedRoute->splat;
        if ($route === '') $route = preg_replace('#^.*?/api/[^/]+/#', '', Flight::request()->url);
        $route = trim(strtok($route, '?'), '/');
        return explode('/', $route);
    }

    protected function json($result, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        Flight::response()->status($status); // flight sends its own status when the script runs to the end
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // runs the callback and turns anything thrown into {success: false, error: ...}
    // the callback returns an array that gets merged over {success: true}
    protected function respond(callable $callback) {
        try {
            $this->json(array_merge(['success' => true], $callback() ?: []));
        } catch (PDOException $e) {
            // db trouble is a bug, the sql stays in the log not on the screen
            error_log(static::class . ': ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Something went wrong saving that.'], 500);
        } catch (Exception $e) {
            // a thrown Exception is a message meant for the user
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            // anything else is a bug, keep it in the log
            error_log(static::class . ': ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Something went wrong.'], 500);
        }
    }

    protected function user() {
        if ($this->user === null) $this->user = AccountsModel::resume();
        return $this->user;
    }

    // {signin: true} is what tablesorter.js and the pages look for to bounce to /signin
    protected function requireLogin() {
        if ($this->user()) return $this->user;
        $this->json(['success' => false, 'signin' => true, 'error' => 'Please sign in.'], 401);
        exit;
    }

    protected function requireAdmin() {
        $this->requireLogin();
        if (!$this->user->is_admin) {
            $this->json(['success' => false, 'error' => 'Not allowed.'], 403);
            exit;
        }
        return $this->user;
    }

    // forms send the token the page put in data-csrf, see form_handler.js
    protected function checkCsrf($data) {
        $token = $this->session->get('csrf_token');
        if (empty($data->csrf_token) || !$token || !hash_equals($token, (string) $data->csrf_token)) {
            throw new Exception('Form expired, reload the page and try again.');
        }
    }

    // posts that change things must come from our own pages
    protected function checkOrigin() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if ($origin === '') return; // same origin fetch sends neither on some setups, the session cookie is samesite anyway
        $host = parse_url($origin, PHP_URL_HOST);
        if ($host !== ($_SERVER['HTTP_HOST'] ?? '') && $host !== strtok($_SERVER['HTTP_HOST'] ?? '', ':')) {
            $this->json(['success' => false, 'error' => 'Cross site request blocked.'], 403);
            exit;
        }
    }
}
