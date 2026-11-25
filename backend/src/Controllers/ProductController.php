<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Product;
use App\Services\CatalogService;
use App\Services\SearchService;

final class ProductController
{
    private CatalogService $catalog;

    public function __construct()
    {
        $this->catalog = new CatalogService();
    }

    /**
     * GET /api/products?q=&category=rpg,action&platform=Steam&region=GLOBAL&price_min=1000&price_max=5000
     *                  &in_stock=1&sort=price_asc&page=1&per_page=24
     * Prices are in cents.
     */
    public function index(Request $request): Response
    {
        $data = Validator::validate($request->query, [
            'q'         => 'string|max:100',
            'sort'      => 'in:' . implode(',', CatalogService::SORTS),
            'page'      => 'integer|min:1|max:1000',
            'per_page'  => 'integer|min:1|max:48',
            'price_min' => 'integer|min:0',
            'price_max' => 'integer|min:0',
            'in_stock'  => 'in:0,1,true,false',
        ]);

        foreach (['category', 'platform', 'region'] as $key) {
            $data[$key] = self::listParam($request->query[$key] ?? null);
        }
        $data['in_stock'] = in_array($data['in_stock'] ?? '', ['1', 'true'], true);

        return Response::json($this->catalog->browse($data));
    }

    public function show(Request $request): Response
    {
        return Response::json(['data' => $this->catalog->product($request->param('slug'))]);
    }

    /** GET /api/filters — values for the store sidebar (platforms, regions, categories, price range). */
    public function filters(Request $request): Response
    {
        return Response::json(['data' => Product::facets()]);
    }

    public function categories(Request $request): Response
    {
        return Response::json(['data' => $this->catalog->categories()]);
    }

    /** GET /api/home/{section} — popular | deals | new | recommended | featured */
    public function homeSection(Request $request): Response
    {
        return Response::json(['data' => $this->catalog->section($request->param('section'), $request->user)]);
    }

    /** GET /api/search/suggest?q=cyberp */
    public function suggest(Request $request): Response
    {
        $q = Validator::validate($request->query, ['q' => 'required|string|max:100'])['q'];
        return Response::json(['data' => (new SearchService())->suggest($q)]);
    }

    /** Accepts "a,b" or ["a","b"] (from ?x[]=a&x[]=b). @return string[] */
    private static function listParam(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $values = is_array($value) ? $value : explode(',', (string) $value);
        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values), 'strlen'));
    }
}
