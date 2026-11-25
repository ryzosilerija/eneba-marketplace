<?php
declare(strict_types=1);

/**
 * Front controller: every API request goes through here.
 * With `php -S` the built-in server serves real files (images) directly when we return false.
 */
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../src/bootstrap.php';

use App\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

// CORS: the React app runs on a different origin (localhost:5173 in dev, a static host in prod).
$allowed = array_map('trim', explode(',', getenv('CORS_ORIGINS') ?: 'http://localhost:5173'));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && (in_array($origin, $allowed, true) || in_array('*', $allowed, true))) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Cart-Token');
    header('Access-Control-Max-Age: 86400');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $request = Request::fromGlobals();
} catch (HttpException $e) {
    Response::json(['message' => $e->getMessage()], $e->status)->send();
    exit;
}

(new App())->handle($request)->send();
