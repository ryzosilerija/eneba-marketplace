<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Offer;
use App\Models\Product;

/**
 * Browsing the catalog: store listing with filters/sorting/pagination,
 * homepage sections and the product detail page.
 * All filtering happens in SQL, so the frontend only ever receives the matching page of results.
 */
final class CatalogService
{
    public const SORTS = ['relevance', 'popular', 'price_asc', 'price_desc', 'newest', 'discount', 'rating', 'name'];

    private const ORDER_BY = [
        'popular'    => 't.sales_count DESC',
        'price_asc'  => 't.min_price ASC',
        'price_desc' => 't.min_price DESC',
        'newest'     => 't.release_date DESC NULLS LAST',
        'discount'   => '(t.original_price - t.min_price)::float / NULLIF(t.original_price, 0) DESC',
        'rating'     => 't.rating DESC, t.sales_count DESC',
        'name'       => 't.name ASC',
    ];

    public function __construct(private SearchService $search = new SearchService())
    {
    }

    /**
     * @param array{q?:string, category?:string[], platform?:string[], region?:string[],
     *              price_min?:int, price_max?:int, in_stock?:bool, sort?:string, page?:int, per_page?:int} $f
     */
    public function browse(array $f): array
    {
        $where = ['TRUE'];
        $params = [];

        if (!empty($f['q'])) {
            [$cond, $p] = $this->search->condition($f['q']);
            $where[] = $cond;
            $params += $p;
        }
        // Multi-value filters: ?platform=Steam,Xbox  →  t.platform IN (:platform0, :platform1)
        foreach (['category' => 't.category_slug', 'platform' => 't.platform', 'region' => 't.region'] as $key => $column) {
            if (!empty($f[$key])) {
                $placeholders = [];
                foreach (array_values($f[$key]) as $i => $value) {
                    $placeholders[] = ":{$key}{$i}";
                    $params["{$key}{$i}"] = $value;
                }
                $where[] = "$column IN (" . implode(', ', $placeholders) . ')';
            }
        }
        if (isset($f['price_min'])) {
            $where[] = 't.min_price >= :price_min';
            $params['price_min'] = $f['price_min'];
        }
        if (isset($f['price_max'])) {
            $where[] = 't.min_price <= :price_max';
            $params['price_max'] = $f['price_max'];
        }
        if (!empty($f['in_stock'])) {
            $where[] = 't.min_price IS NOT NULL';
        }

        $sort = $f['sort'] ?? (empty($f['q']) ? 'popular' : 'relevance');
        if ($sort === 'relevance' && !empty($f['q'])) {
            [$order, $orderParams] = $this->search->relevanceOrder($f['q']);
            $params += $orderParams;
        } else {
            $order = self::ORDER_BY[$sort] ?? self::ORDER_BY['popular'];
        }
        // Out-of-stock products always go last; id makes pagination order stable.
        $order = "(t.min_price IS NULL), $order, t.id";

        $perPage = $f['per_page'] ?? 24;
        $page = max(1, $f['page'] ?? 1);
        $result = Product::list(implode(' AND ', $where), $params, $order, $perPage, ($page - 1) * $perPage);

        return [
            'data' => $result['items'],
            'meta' => [
                'total'     => $result['total'],
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
                'sort'      => $sort,
            ],
        ];
    }

    /** Homepage sections. Each one is a separate endpoint so one failing doesn't break the others. */
    public function section(string $name, ?array $user): array
    {
        $inStock = 't.min_price IS NOT NULL';
        return match ($name) {
            'popular'     => Product::list($inStock, [], 't.sales_count DESC', 10, 0)['items'],
            'deals'       => Product::list($inStock, [], self::ORDER_BY['discount'], 10, 0)['items'],
            'new'         => Product::list("$inStock AND t.release_date <= CURRENT_DATE", [], 't.release_date DESC', 10, 0)['items'],
            'featured'    => Product::list("$inStock AND t.is_featured", [], 't.sales_count DESC', 5, 0)['items'],
            'recommended' => $this->recommended($user),
            default       => throw HttpException::notFound('Unknown homepage section.'),
        };
    }

    /**
     * Logged-in users with orders: top-rated products from the categories they bought from,
     * excluding what they already own. Everyone else: top-rated featured products.
     */
    private function recommended(?array $user): array
    {
        if ($user) {
            $stmt = Database::connection()->prepare(
                "SELECT DISTINCT p.category_id, oi.product_id FROM orders o
                 JOIN order_items oi ON oi.order_id = o.id JOIN products p ON p.id = oi.product_id
                 WHERE o.user_id = ? AND o.status = 'paid'"
            );
            $stmt->execute([(int) $user['id']]);
            $rows = $stmt->fetchAll();
            if ($rows) {
                $cats = implode(',', array_unique(array_map(fn ($r) => (int) $r['category_id'], $rows)));
                $owned = implode(',', array_map(fn ($r) => (int) $r['product_id'], $rows));
                $items = Product::list(
                    "t.min_price IS NOT NULL AND t.id NOT IN ($owned) AND t.id IN (SELECT id FROM products WHERE category_id IN ($cats))",
                    [], 't.rating DESC, t.sales_count DESC', 10, 0
                )['items'];
                if (count($items) >= 4) {
                    return $items;
                }
            }
        }
        return Product::list('t.min_price IS NOT NULL', [], 't.is_featured DESC, t.rating DESC, t.sales_count DESC', 10, 0)['items'];
    }

    public function product(string $slug): array
    {
        $p = Product::findBySlug($slug);
        if (!$p) {
            throw HttpException::notFound('Product not found.');
        }
        $offers = array_map([Offer::class, 'toPublic'], Offer::forProduct((int) $p['id']));
        $inStock = array_filter($offers, fn ($o) => $o['in_stock']);
        $min = $inStock ? min(array_column($inStock, 'price')) : null;

        $related = Product::list(
            't.category_slug = :cat AND t.id <> :id AND t.min_price IS NOT NULL',
            ['cat' => $p['category_slug'], 'id' => (int) $p['id']],
            't.sales_count DESC', 6, 0
        )['items'];

        return [
            'id'             => (int) $p['id'],
            'slug'           => $p['slug'],
            'name'           => $p['name'],
            'description'    => $p['description'],
            'platform'       => $p['platform'],
            'region'         => $p['region'],
            'developer'      => $p['developer'],
            'publisher'      => $p['publisher'],
            'release_date'   => $p['release_date'],
            'rating'         => (float) $p['rating'],
            'sales_count'    => (int) $p['sales_count'],
            'original_price' => (int) $p['original_price'],
            'min_price'      => $min,
            'discount'       => $min ? max(0, (int) round(100 - $min * 100 / (int) $p['original_price'])) : 0,
            'category'       => ['slug' => $p['category_slug'], 'name' => $p['category_name']],
            'images'         => Product::images((int) $p['id']),
            'offers'         => $offers,
            'related'        => $related,
        ];
    }

    public function categories(): array
    {
        return Database::connection()->query(
            'SELECT c.id, c.name, c.slug, COUNT(p.id)::int AS product_count
             FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name'
        )->fetchAll();
    }
}
