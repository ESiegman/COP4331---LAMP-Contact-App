<?php

$autoloadCandidates = [
    __DIR__ . '/../../vendor/autoload.php',
    '/var/www/contacts-app/vendor/autoload.php',
];

$autoload = null;
foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Application not properly deployed (autoloader not found)']);
    exit;
}

require $autoload;

use App\Auth\SessionAuthStore;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ContactsController;
use App\Database;
use App\Models\ContactModel;
use App\Models\UserModel;
use App\Support\Request;
use App\Support\Response;
use App\Support\Router;

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (isset($_GET['ping'])) {
    Response::send(Response::success(['service' => 'contacts-app-api']));
    exit;
}

$pdo = Database::get();
$users = new UserModel($pdo);
$contacts = new ContactModel($pdo);
$authStore = new SessionAuthStore();

$authController = new AuthController($users, $authStore);
$contactsController = new ContactsController($contacts);
$adminController = new AdminController($users, $contacts);

$requireAuth = function (callable $handler) use ($authStore): callable {
    return function (Request $request) use ($handler, $authStore): array {
        $auth = $authStore->current();

        if ($auth === null) {
            return Response::error('Authentication required', 401);
        }

        return $handler($auth, $request);
    };
};

$router = new Router();

$router->add('POST', 'auth.register', fn (Request $r) => $authController->register($r));
$router->add('POST', 'auth.login', fn (Request $r) => $authController->login($r));
$router->add('POST', 'auth.logout', fn (Request $r) => $authController->logout());
$router->add('GET', 'auth.me', $requireAuth(fn ($auth, $r) => $authController->me($auth)));
$router->add('PUT', 'auth.password', $requireAuth(fn ($auth, $r) => $authController->changePassword($auth, $r)));

$router->add('GET', 'contacts.search', $requireAuth(fn ($auth, $r) => $contactsController->search($auth, $r)));
$router->add('GET', 'contacts.get', $requireAuth(fn ($auth, $r) => $contactsController->get($auth, $r)));
$router->add('POST', 'contacts.create', $requireAuth(fn ($auth, $r) => $contactsController->create($auth, $r)));
$router->add('PUT', 'contacts.update', $requireAuth(fn ($auth, $r) => $contactsController->update($auth, $r)));
$router->add('DELETE', 'contacts.delete', $requireAuth(fn ($auth, $r) => $contactsController->delete($auth, $r)));

$router->add('GET', 'admin.users.search', $requireAuth(fn ($auth, $r) => $adminController->listUsers($auth, $r)));
$router->add('GET', 'admin.users.contacts', $requireAuth(fn ($auth, $r) => $adminController->userContacts($auth, $r)));
$router->add('PUT', 'admin.users.disable', $requireAuth(fn ($auth, $r) => $adminController->disableUser($auth, $r)));
$router->add('PUT', 'admin.users.password', $requireAuth(fn ($auth, $r) => $adminController->changeUserPassword($auth, $r)));

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $body['action'] ?? '';

Response::send($router->dispatch(new Request($method, $action, $body, $_GET)));
