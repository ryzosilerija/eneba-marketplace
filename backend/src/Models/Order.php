<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Order
{
    public static function create(int $userId, int $total): array
    {
        $stmt = Database::connection()->prepare('INSERT INTO orders (user_id, total) VALUES (?, ?) RETURNING *');
        $stmt->execute([$userId, $total]);
        return $stmt->fetch();
    }

    public static function addItem(int $orderId, array $line): void
    {
        Database::connection()->prepare(
            'INSERT INTO order_items (order_id, offer_id, product_id, product_name, seller_name, platform, region, unit_price, quantity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $orderId, $line['offer_id'], $line['product_id'], $line['name'], $line['seller_name'],
            $line['platform'], $line['region'], $line['price'], $line['quantity'],
        ]);
    }

    /**
     * Finds an order only if it belongs to the given user. Returning null for other users' orders
     * (instead of 403) avoids revealing which order IDs exist.
     */
    public static function findForUser(int $orderId, int $userId, bool $lock = false): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM orders WHERE id = ? AND user_id = ?' . ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute([$orderId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function listForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items i WHERE i.order_id = o.id) AS item_count,
                    (SELECT STRING_AGG(product_name, \', \' ORDER BY id) FROM order_items i WHERE i.order_id = o.id) AS summary
             FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function items(int $orderId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.*, p.slug,
                    (SELECT url FROM product_images pi WHERE pi.product_id = i.product_id ORDER BY position LIMIT 1) AS cover
             FROM order_items i LEFT JOIN products p ON p.id = i.product_id
             WHERE i.order_id = ? ORDER BY i.id'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    /** Keys assigned to a given order item. */
    public static function keysForItem(int $orderItemId): array
    {
        $stmt = Database::connection()->prepare('SELECT code FROM digital_keys WHERE order_item_id = ? ORDER BY id');
        $stmt->execute([$orderItemId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function markPaid(int $orderId, string $reference): void
    {
        Database::connection()->prepare(
            "UPDATE orders SET status = 'paid', paid_at = NOW(), payment_reference = ?, failure_reason = NULL WHERE id = ?"
        )->execute([$reference, $orderId]);
    }

    public static function markFailed(int $orderId, string $reason): void
    {
        Database::connection()->prepare(
            "UPDATE orders SET status = 'failed', failure_reason = ? WHERE id = ? AND status <> 'paid'"
        )->execute([$reason, $orderId]);
    }

    public static function toPublic(array $row): array
    {
        return [
            'id'             => (int) $row['id'],
            'status'         => $row['status'],
            'total'          => (int) $row['total'],
            'created_at'     => $row['created_at'],
            'paid_at'        => $row['paid_at'],
            'failure_reason' => $row['failure_reason'],
            'payment_reference' => $row['payment_reference'],
        ];
    }
}
