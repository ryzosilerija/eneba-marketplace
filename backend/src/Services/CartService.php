<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Request;
use App\Models\Cart;
use App\Models\Offer;

/**
 * Shopping cart rules.
 *
 * The cart lives in the database, so it survives page refreshes and works across devices:
 *  - logged-in users have one cart linked to their user_id;
 *  - guests get a cart identified by a random UUID "cart token", which the frontend keeps
 *    in localStorage and sends in the X-Cart-Token header. On login the guest cart is merged in.
 *
 * Every line references an OFFER (product + the seller the user picked), not just a product.
 */
final class CartService
{
    public const MAX_QUANTITY = 10;

    /** Finds the cart for this request, optionally creating one. */
    public function resolve(Request $request, bool $create): ?array
    {
        if ($request->user) {
            $userId = (int) $request->user['id'];
            return Cart::findForUser($userId) ?? ($create ? Cart::create($userId, null) : null);
        }
        $token = $request->header('x-cart-token');
        if ($token && preg_match('/^[0-9a-f-]{36}$/', $token)) {
            $cart = Cart::findGuest($token);
            if ($cart) {
                return $cart;
            }
        }
        return $create ? Cart::create(null, self::uuid()) : null;
    }

    public function summary(?array $cart): array
    {
        if (!$cart) {
            return ['cart_token' => null, 'items' => [], 'item_count' => 0, 'total' => 0];
        }
        $items = array_map(function (array $r) {
            $stock = (int) $r['stock'];
            return [
                'id'         => (int) $r['id'],
                'quantity'   => (int) $r['quantity'],
                'unit_price' => (int) $r['price'],
                'line_total' => (int) $r['price'] * (int) $r['quantity'],
                'stock'      => $stock,
                'available'  => $r['is_active'] && $stock >= (int) $r['quantity'],
                'offer_id'   => (int) $r['offer_id'],
                'seller'     => ['name' => $r['seller_name'], 'slug' => $r['seller_slug']],
                'product'    => [
                    'id' => (int) $r['product_id'], 'slug' => $r['slug'], 'name' => $r['name'],
                    'platform' => $r['platform'], 'region' => $r['region'], 'cover' => $r['cover'],
                ],
            ];
        }, Cart::items((int) $cart['id']));

        return [
            'cart_token' => $cart['user_id'] ? null : $cart['token'],
            'items'      => $items,
            'item_count' => array_sum(array_column($items, 'quantity')),
            'total'      => array_sum(array_column($items, 'line_total')),
        ];
    }

    /** Adds an offer to the cart; adding the same offer again increases its quantity. */
    public function add(array $cart, int $offerId, int $quantity): void
    {
        $offer = Offer::find($offerId);
        if (!$offer || !$offer['is_active']) {
            throw HttpException::notFound('Offer not found.');
        }
        $existing = Cart::findItemByOffer((int) $cart['id'], $offerId);
        $newQty = ($existing ? (int) $existing['quantity'] : 0) + $quantity;
        $this->assertQuantity($newQty, (int) $offer['stock']);
        Cart::setQuantity((int) $cart['id'], $offerId, $newQty);
    }

    public function update(array $cart, int $itemId, int $quantity): void
    {
        $item = Cart::findItem((int) $cart['id'], $itemId);
        if (!$item) {
            throw HttpException::notFound('Cart item not found.');
        }
        $offer = Offer::find((int) $item['offer_id']);
        $this->assertQuantity($quantity, (int) ($offer['stock'] ?? 0));
        Cart::setQuantity((int) $cart['id'], (int) $item['offer_id'], $quantity);
    }

    public function remove(array $cart, int $itemId): void
    {
        if (!Cart::findItem((int) $cart['id'], $itemId)) {
            throw HttpException::notFound('Cart item not found.');
        }
        Cart::removeItem((int) $cart['id'], $itemId);
    }

    /** Moves a guest cart's lines into the user's cart, then deletes the guest cart. */
    public function mergeGuestCart(string $token, int $userId): void
    {
        $guest = Cart::findGuest($token);
        if (!$guest) {
            return;
        }
        $userCart = Cart::findForUser($userId) ?? Cart::create($userId, null);
        foreach (Cart::items((int) $guest['id']) as $line) {
            $existing = Cart::findItemByOffer((int) $userCart['id'], (int) $line['offer_id']);
            $qty = min(
                ($existing ? (int) $existing['quantity'] : 0) + (int) $line['quantity'],
                self::MAX_QUANTITY,
                max(1, (int) $line['stock'])
            );
            Cart::setQuantity((int) $userCart['id'], (int) $line['offer_id'], $qty);
        }
        Cart::delete((int) $guest['id']);
    }

    private function assertQuantity(int $quantity, int $stock): void
    {
        if ($quantity > self::MAX_QUANTITY) {
            throw HttpException::validation(['quantity' => ['You can buy at most ' . self::MAX_QUANTITY . ' keys of one offer.']]);
        }
        if ($quantity > $stock) {
            throw HttpException::conflict($stock === 0
                ? 'This offer is out of stock.'
                : "Only $stock key(s) left for this offer.");
        }
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); // version 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
