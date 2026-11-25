<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\CartService;

/**
 * Works for guests (X-Cart-Token header) and logged-in users (Bearer token).
 * Every mutating endpoint returns the full updated cart so the frontend can just replace its state.
 */
final class CartController
{
    private CartService $carts;

    public function __construct()
    {
        $this->carts = new CartService();
    }

    public function show(Request $request): Response
    {
        return Response::json($this->carts->summary($this->carts->resolve($request, create: false)));
    }

    public function add(Request $request): Response
    {
        $data = Validator::validate($request->body, [
            'offer_id' => 'required|integer|min:1',
            'quantity' => 'integer|min:1|max:' . CartService::MAX_QUANTITY,
        ]);
        $cart = $this->carts->resolve($request, create: true);
        $this->carts->add($cart, $data['offer_id'], $data['quantity'] ?? 1);
        return Response::json($this->carts->summary($cart), 201);
    }

    public function update(Request $request): Response
    {
        $data = Validator::validate($request->body, [
            'quantity' => 'required|integer|min:1|max:' . CartService::MAX_QUANTITY,
        ]);
        $cart = $this->requireCart($request);
        $this->carts->update($cart, (int) $request->param('id'), $data['quantity']);
        return Response::json($this->carts->summary($cart));
    }

    public function remove(Request $request): Response
    {
        $cart = $this->requireCart($request);
        $this->carts->remove($cart, (int) $request->param('id'));
        return Response::json($this->carts->summary($cart));
    }

    private function requireCart(Request $request): array
    {
        return $this->carts->resolve($request, create: false) ?? throw HttpException::notFound('Cart not found.');
    }
}
