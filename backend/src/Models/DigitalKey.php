<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class DigitalKey
{
    /**
     * Locks up to $quantity available keys of an offer for the current transaction.
     * FOR UPDATE SKIP LOCKED: if two checkouts run at the same moment, each one skips the rows
     * the other has locked, so they can never pick the same key. Must be called inside a transaction.
     * @return int[] key IDs
     */
    public static function lockAvailable(int $offerId, int $quantity): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM digital_keys WHERE offer_id = ? AND status = 'available'
             ORDER BY id LIMIT ? FOR UPDATE SKIP LOCKED"
        );
        $stmt->execute([$offerId, $quantity]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Marks keys as sold to an order item. The "AND status = 'available'" guard is a second safety net:
     * a key that's already sold is never overwritten. Returns how many rows changed.
     * @param int[] $keyIds
     */
    public static function assign(array $keyIds, int $orderItemId): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE digital_keys SET status = 'sold', order_item_id = ?, sold_at = NOW()
             WHERE id = ANY(?::int[]) AND status = 'available'"
        );
        $stmt->execute([$orderItemId, '{' . implode(',', $keyIds) . '}']);
        return $stmt->rowCount();
    }
}
