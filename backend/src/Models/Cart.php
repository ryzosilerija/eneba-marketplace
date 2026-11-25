<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Cart
{
    public static function findForUser(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM carts WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function findGuest(string $token): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM carts WHERE token = ? AND user_id IS NULL');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function create(?int $userId, ?string $token): array
    {
        $stmt = Database::connection()->prepare('INSERT INTO carts (user_id, token) VALUES (?, ?) RETURNING *');
        $stmt->execute([$userId, $token]);
        return $stmt->fetch();
    }

    public static function delete(int $cartId): void
    {
        Database::connection()->prepare('DELETE FROM carts WHERE id = ?')->execute([$cartId]);
    }

    /** Cart lines joined with offer, seller, product and current stock. */
    public static function items(int $cartId): array
    {
        $stmt = Database::connection()->prepare(<<<SQL
            SELECT ci.id, ci.quantity, o.id AS offer_id, o.price, o.is_active,
                   s.name AS seller_name, s.slug AS seller_slug,
                   p.id AS product_id, p.slug, p.name, p.platform, p.region,
                   (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY position LIMIT 1) AS cover,
                   (SELECT COUNT(*) FROM digital_keys k WHERE k.offer_id = o.id AND k.status = 'available') AS stock
            FROM cart_items ci
            JOIN offers o   ON o.id = ci.offer_id
            JOIN sellers s  ON s.id = o.seller_id
            JOIN products p ON p.id = o.product_id
            WHERE ci.cart_id = ?
            ORDER BY ci.id
            SQL);
        $stmt->execute([$cartId]);
        return $stmt->fetchAll();
    }

    public static function findItem(int $cartId, int $itemId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cart_items WHERE id = ? AND cart_id = ?');
        $stmt->execute([$itemId, $cartId]);
        return $stmt->fetch() ?: null;
    }

    public static function findItemByOffer(int $cartId, int $offerId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cart_items WHERE cart_id = ? AND offer_id = ?');
        $stmt->execute([$cartId, $offerId]);
        return $stmt->fetch() ?: null;
    }

    public static function setQuantity(int $cartId, int $offerId, int $quantity): void
    {
        Database::connection()->prepare(
            'INSERT INTO cart_items (cart_id, offer_id, quantity) VALUES (?, ?, ?)
             ON CONFLICT (cart_id, offer_id) DO UPDATE SET quantity = EXCLUDED.quantity'
        )->execute([$cartId, $offerId, $quantity]);
        self::touch($cartId);
    }

    public static function removeItem(int $cartId, int $itemId): void
    {
        Database::connection()->prepare('DELETE FROM cart_items WHERE id = ? AND cart_id = ?')->execute([$itemId, $cartId]);
        self::touch($cartId);
    }

    public static function clear(int $cartId): void
    {
        Database::connection()->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
        self::touch($cartId);
    }

    private static function touch(int $cartId): void
    {
        Database::connection()->prepare('UPDATE carts SET updated_at = NOW() WHERE id = ?')->execute([$cartId]);
    }
}
