<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\Order;

/**
 * Reading orders. Every query is scoped to the current user, so a user can never
 * load someone else's order — or the digital keys attached to it.
 */
final class OrderService
{
    public function list(int $userId): array
    {
        return array_map(fn ($o) => Order::toPublic($o) + [
            'item_count' => (int) $o['item_count'],
            'summary'    => $o['summary'],
        ], Order::listForUser($userId));
    }

    public function detail(int $orderId, int $userId): array
    {
        $order = Order::findForUser($orderId, $userId);
        if (!$order) {
            throw HttpException::notFound('Order not found.');
        }
        $paid = $order['status'] === 'paid';

        $items = array_map(fn ($i) => [
            'id'           => (int) $i['id'],
            'product_name' => $i['product_name'],
            'product_slug' => $i['slug'],
            'cover'        => $i['cover'],
            'seller_name'  => $i['seller_name'],
            'platform'     => $i['platform'],
            'region'       => $i['region'],
            'unit_price'   => (int) $i['unit_price'],
            'quantity'     => (int) $i['quantity'],
            'line_total'   => (int) $i['unit_price'] * (int) $i['quantity'],
            // Keys are only revealed once the order is paid.
            'keys'         => $paid ? Order::keysForItem((int) $i['id']) : [],
        ], Order::items($orderId));

        return Order::toPublic($order) + ['items' => $items];
    }
}
