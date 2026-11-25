<?php
declare(strict_types=1);

namespace App;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

/**
 * The application kernel: turns a Request into a Response and converts exceptions
 * into JSON errors. Used by public/index.php and directly by the tests.
 */
final class App
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        (require __DIR__ . '/routes.php')($this->router);
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            $body = ['message' => $e->getMessage()];
            if ($e->errors) {
                $body['errors'] = $e->errors;
            }
            return Response::json($body, $e->status);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $body = ['message' => 'Server error.'];
            if (getenv('APP_DEBUG') === 'true') {
                $body['debug'] = $e->getMessage();
            }
            return Response::json($body, 500);
        }
    }
}
