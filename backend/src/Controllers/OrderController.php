<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\CheckoutService;
use App\Services\FakePaymentGateway;
use App\Services\OrderService;

/** All routes here require authentication (see routes.php). */
final class OrderController
{
    public function index(Request $request): Response
    {
        return Response::json(['data' => (new OrderService())->list((int) $request->user['id'])]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        return Response::json(['data' => (new OrderService())->detail($id, (int) $request->user['id'])]);
    }

    /** POST /api/checkout — creates a pending order from the cart. */
    public function checkout(Request $request): Response
    {
        return Response::json(['data' => (new CheckoutService())->placeOrder($request->user)], 201);
    }

    /** POST /api/orders/{id}/pay — fake card payment. 200 on success, 402 Payment Required on decline. */
    public function pay(Request $request): Response
    {
        $data = Validator::validate($request->body, [
            'card_number' => 'required|string|max:23',
            'card_name'   => 'required|string|min:2|max:100',
            'expiry'      => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'], // array form: the regex contains "|"
            'cvc'         => 'required|string|regex:/^\d{3,4}$/',
        ]);
        if (!FakePaymentGateway::luhnValid($data['card_number'])) {
            throw HttpException::validation(['card_number' => ['The card number is invalid.']]);
        }
        [$month, $year] = array_map('intval', explode('/', $data['expiry']));
        if (mktime(0, 0, 0, $month + 1, 1, 2000 + $year) <= time()) {
            throw HttpException::validation(['expiry' => ['The card has expired.']]);
        }

        $result = (new CheckoutService())->pay($request->user, (int) $request->param('id'), $data['card_number']);
        return Response::json(
            ['message' => $result['message'], 'data' => $result['order']],
            $result['success'] ? 200 : 402
        );
    }
}
