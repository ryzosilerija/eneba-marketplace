<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\HealthController;
use App\Controllers\OrderController;
use App\Controllers\ProductController;
use App\Core\Router;

/** All API routes in one place. Third argument `true` = requires a logged-in user. */
return function (Router $r): void {
    $r->get('/api/health', [HealthController::class, 'show']);

    // Auth
    $r->post('/api/auth/register', [AuthController::class, 'register']);
    $r->post('/api/auth/login', [AuthController::class, 'login']);
    $r->post('/api/auth/logout', [AuthController::class, 'logout'], true);
    $r->get('/api/auth/me', [AuthController::class, 'me'], true);

    // Catalog
    $r->get('/api/products', [ProductController::class, 'index']);
    $r->get('/api/products/{slug}', [ProductController::class, 'show']);
    $r->get('/api/categories', [ProductController::class, 'categories']);
    $r->get('/api/filters', [ProductController::class, 'filters']);
    $r->get('/api/home/{section}', [ProductController::class, 'homeSection']);
    $r->get('/api/search/suggest', [ProductController::class, 'suggest']);

    // Cart (guest or user)
    $r->get('/api/cart', [CartController::class, 'show']);
    $r->post('/api/cart/items', [CartController::class, 'add']);
    $r->patch('/api/cart/items/{id}', [CartController::class, 'update']);
    $r->delete('/api/cart/items/{id}', [CartController::class, 'remove']);

    // Checkout & orders
    $r->post('/api/checkout', [OrderController::class, 'checkout'], true);
    $r->get('/api/orders', [OrderController::class, 'index'], true);
    $r->get('/api/orders/{id}', [OrderController::class, 'show'], true);
    $r->post('/api/orders/{id}/pay', [OrderController::class, 'pay'], true);
};
