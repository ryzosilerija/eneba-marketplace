<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Cart;
use App\Models\DigitalKey;
use App\Models\Order;
use App\Models\Product;

/**
 * Checkout in two steps, like a real shop:
 *   1. placeOrder(): turns the cart into a "pending" order (prices are snapshotted).
 *   2. pay():       runs the fake payment; on success assigns digital keys and marks the order "paid".
 *
 * Key assignment is the critical part. Inside ONE database transaction we:
 *   a) lock the order row (so double-clicking "Pay" can't pay twice),
 *   b) lock enough available keys with FOR UPDATE SKIP LOCKED (concurrent buyers can't grab the same key),
 *   c) charge the card,
 *   d) mark the keys sold to this order's items.
 * If anything fails, the transaction rolls back and no key changes hands.
 */
final class CheckoutService
{
    public function __construct(private FakePaymentGateway $gateway = new FakePaymentGateway())
    {
    }

    public function placeOrder(array $user): array
    {
        $cart = Cart::findForUser((int) $user['id']);
        $lines = $cart ? Cart::items((int) $cart['id']) : [];
        if (!$lines) {
            throw HttpException::validation(['cart' => ['Your cart is empty.']], 'Your cart is empty.');
        }
        foreach ($lines as $line) {
            if (!$line['is_active'] || (int) $line['stock'] < (int) $line['quantity']) {
                throw HttpException::conflict("Not enough stock for \"{$line['name']}\" from {$line['seller_name']}. Please update your cart.");
            }
        }

        $total = array_sum(array_map(fn ($l) => (int) $l['price'] * (int) $l['quantity'], $lines));

        $order = Database::transaction(function () use ($user, $lines, $total) {
            $order = Order::create((int) $user['id'], $total);
            foreach ($lines as $line) {
                Order::addItem((int) $order['id'], $line);
            }
            return $order;
        });

        return (new OrderService())->detail((int) $order['id'], (int) $user['id']);
    }

    /** @return array{success: bool, message: string, order: array} */
    public function pay(array $user, int $orderId, string $cardNumber): array
    {
        $userId = (int) $user['id'];
        $order = Order::findForUser($orderId, $userId);
        if (!$order) {
            throw HttpException::notFound('Order not found.');
        }
        if ($order['status'] === 'paid') {
            throw HttpException::conflict('This order has already been paid.');
        }

        try {
            $payment = Database::transaction(function () use ($orderId, $userId, $cardNumber) {
                $order = Order::findForUser($orderId, $userId, lock: true);
                if ($order['status'] === 'paid') {
                    throw HttpException::conflict('This order has already been paid.');
                }

                // (b) reserve keys for every line before charging
                $reserved = [];
                foreach (Order::items($orderId) as $item) {
                    $keyIds = $item['offer_id'] ? DigitalKey::lockAvailable((int) $item['offer_id'], (int) $item['quantity']) : [];
                    if (count($keyIds) < (int) $item['quantity']) {
                        throw HttpException::conflict("Sorry, \"{$item['product_name']}\" from {$item['seller_name']} just sold out.");
                    }
                    $reserved[] = [$item, $keyIds];
                }

                // (c) charge
                $payment = $this->gateway->charge((int) $order['total'], $cardNumber);
                if (!$payment['success']) {
                    return $payment; // nothing written yet; commit is a no-op and the locks are released
                }

                // (d) hand out keys
                foreach ($reserved as [$item, $keyIds]) {
                    if (DigitalKey::assign($keyIds, (int) $item['id']) !== count($keyIds)) {
                        throw new \RuntimeException('Key assignment race detected.'); // rolls everything back
                    }
                    if ($item['product_id']) {
                        Product::incrementSales((int) $item['product_id'], (int) $item['quantity']);
                    }
                }
                Order::markPaid($orderId, $payment['reference']);
                return $payment;
            });
        } catch (HttpException $e) {
            if ($e->status === 409 && !str_contains($e->getMessage(), 'already been paid')) {
                Order::markFailed($orderId, $e->getMessage());
            }
            throw $e;
        }

        if (!$payment['success']) {
            Order::markFailed($orderId, $payment['error']);
            return ['success' => false, 'message' => $payment['error'], 'order' => (new OrderService())->detail($orderId, $userId)];
        }

        $this->removePurchasedFromCart($userId, $orderId);
        return ['success' => true, 'message' => 'Payment successful.', 'order' => (new OrderService())->detail($orderId, $userId)];
    }

    private function removePurchasedFromCart(int $userId, int $orderId): void
    {
        $cart = Cart::findForUser($userId);
        if (!$cart) {
            return;
        }
        $offerIds = array_column(Order::items($orderId), 'offer_id');
        foreach (Cart::items((int) $cart['id']) as $line) {
            if (in_array($line['offer_id'], $offerIds)) {
                Cart::removeItem((int) $cart['id'], (int) $line['id']);
            }
        }
    }
}
