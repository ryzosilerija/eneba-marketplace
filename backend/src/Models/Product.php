<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Product queries. A product's displayed price is the cheapest in-stock offer ("from €X"),
 * computed with a LATERAL subquery so it is always in sync with offers and keys.
 */
final class Product
{
    /** SELECT + FROM shared by every listing query. */
    public const LIST_SQL = <<<SQL
        SELECT p.id, p.slug, p.name, p.platform, p.region, p.original_price, p.rating,
               p.release_date, p.sales_count, p.is_featured,
               c.slug AS category_slug, c.name AS category_name,
               (SELECT url FROM product_images i WHERE i.product_id = p.id ORDER BY position LIMIT 1) AS cover,
               os.min_price, COALESCE(os.offer_count, 0) AS offer_count
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN LATERAL (
            SELECT MIN(o.price) AS min_price, COUNT(*) AS offer_count
            FROM offers o
            WHERE o.product_id = p.id AND o.is_active
              AND EXISTS (SELECT 1 FROM digital_keys k WHERE k.offer_id = o.id AND k.status = 'available')
        ) os ON TRUE
        SQL;

    /** Shapes a listing row into the JSON the frontend expects. */
    public static function toCard(array $row): array
    {
        $min = $row['min_price'] !== null ? (int) $row['min_price'] : null;
        $orig = (int) $row['original_price'];
        return [
            'id'             => (int) $row['id'],
            'slug'           => $row['slug'],
            'name'           => $row['name'],
            'platform'       => $row['platform'],
            'region'         => $row['region'],
            'category'       => ['slug' => $row['category_slug'], 'name' => $row['category_name']],
            'cover'          => $row['cover'],
            'rating'         => (float) $row['rating'],
            'release_date'   => $row['release_date'],
            'original_price' => $orig,
            'min_price'      => $min,                 // null = no seller has stock
            'discount'       => $min !== null && $orig > 0 ? max(0, (int) round(100 - $min * 100 / $orig)) : 0,
            'offer_count'    => (int) $row['offer_count'],
            'in_stock'       => $min !== null,
        ];
    }

    /**
     * Runs a listing query with a WHERE clause / ORDER BY built by the caller.
     * @param array<string, mixed> $params named parameters
     * @return array{items: array, total: int}
     */
    public static function list(string $where, array $params, string $orderBy, int $limit, int $offset): array
    {
        $pdo = Database::connection();

        $sql = 'SELECT * FROM (' . self::LIST_SQL . ") AS t WHERE $where ORDER BY $orderBy LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = array_map([self::class, 'toCard'], $stmt->fetchAll());

        // Remove params only used in ORDER BY (e.g. relevance) when counting
        $countParams = array_filter($params, fn ($k) => str_contains($where, ":$k"), ARRAY_FILTER_USE_KEY);
        $count = $pdo->prepare('SELECT COUNT(*) FROM (' . self::LIST_SQL . ") AS t WHERE $where");
        $count->execute($countParams);

        return ['items' => $items, 'total' => (int) $count->fetchColumn()];
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, c.slug AS category_slug, c.name AS category_name
             FROM products p JOIN categories c ON c.id = p.category_id WHERE p.slug = ?'
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /** @return string[] */
    public static function images(int $productId): array
    {
        $stmt = Database::connection()->prepare('SELECT url FROM product_images WHERE product_id = ? ORDER BY position');
        $stmt->execute([$productId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function incrementSales(int $productId, int $by): void
    {
        Database::connection()
            ->prepare('UPDATE products SET sales_count = sales_count + ? WHERE id = ?')
            ->execute([$by, $productId]);
    }

    /** Distinct values + counts for the store's filter sidebar. */
    public static function facets(): array
    {
        $pdo = Database::connection();
        $col = fn (string $c) => $pdo->query("SELECT $c AS value, COUNT(*) AS count FROM products GROUP BY $c ORDER BY $c")->fetchAll();
        $categories = $pdo->query(
            'SELECT c.slug AS value, c.name AS label, COUNT(p.id) AS count
             FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name'
        )->fetchAll();
        $price = $pdo->query('SELECT MIN(price) AS min, MAX(price) AS max FROM offers WHERE is_active')->fetch();

        $cast = fn (array $rows) => array_map(fn ($r) => ['count' => (int) $r['count']] + $r, $rows);
        return [
            'platforms'  => $cast($col('platform')),
            'regions'    => $cast($col('region')),
            'categories' => $cast($categories),
            'price'      => ['min' => (int) $price['min'], 'max' => (int) $price['max']],
        ];
    }
}
