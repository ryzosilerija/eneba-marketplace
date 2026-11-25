<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * An offer is a seller's listing of a product at their own price.
 * "stock" = number of available (unsold) digital keys for the offer.
 */
final class Offer
{
    private const SELECT = <<<SQL
        SELECT o.id, o.product_id, o.price, o.is_active,
               s.id AS seller_id, s.name AS seller_name, s.slug AS seller_slug, s.rating AS seller_rating,
               s.sales_count AS seller_sales, s.is_verified AS seller_verified, s.country AS seller_country,
               (SELECT COUNT(*) FROM digital_keys k WHERE k.offer_id = o.id AND k.status = 'available') AS stock
        FROM offers o JOIN sellers s ON s.id = o.seller_id
        SQL;

    public static function toPublic(array $row): array
    {
        return [
            'id'       => (int) $row['id'],
            'price'    => (int) $row['price'],
            'stock'    => (int) $row['stock'],
            'in_stock' => (int) $row['stock'] > 0 && $row['is_active'],
            'seller'   => [
                'id'          => (int) $row['seller_id'],
                'name'        => $row['seller_name'],
                'slug'        => $row['seller_slug'],
                'rating'      => (float) $row['seller_rating'],
                'sales_count' => (int) $row['seller_sales'],
                'is_verified' => (bool) $row['seller_verified'],
                'country'     => $row['seller_country'],
            ],
        ];
    }

    /** All active offers for a product, cheapest in-stock first. */
    public static function forProduct(int $productId): array
    {
        $stmt = Database::connection()->prepare(
            self::SELECT . ' WHERE o.product_id = ? AND o.is_active ORDER BY (SELECT COUNT(*) = 0 FROM digital_keys k WHERE k.offer_id = o.id AND k.status = \'available\'), o.price'
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public static function find(int $offerId): ?array
    {
        $stmt = Database::connection()->prepare(self::SELECT . ' WHERE o.id = ?');
        $stmt->execute([$offerId]);
        return $stmt->fetch() ?: null;
    }
}
