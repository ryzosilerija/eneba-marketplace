<?php
declare(strict_types=1);

namespace Tests;

final class CatalogTest extends TestCase
{
    private function names(array $body): array
    {
        return array_column($body['data'], 'name');
    }

    public function testSearchFindsPartialName(): void
    {
        [$status, $body] = $this->call('GET', '/api/products?q=witch');
        $this->assertSame(200, $status);
        $this->assertSame('The Witcher 3: Wild Hunt GOTY', $body['data'][0]['name'], 'best match first');
    }

    public function testSearchToleratesTypos(): void
    {
        foreach (['cyberpnk' => 'Cyberpunk 2077', 'eldn ring' => 'Elden Ring', 'hollow nite' => 'Hollow Knight', 'baldurs gate' => "Baldur's Gate 3"] as $q => $expected) {
            [, $body] = $this->call('GET', '/api/products?q=' . urlencode($q));
            $this->assertSame($expected, $body['data'][0]['name'] ?? null, "query '$q'");
        }
    }

    public function testSearchWithNoMatchReturnsEmpty(): void
    {
        [$status, $body] = $this->call('GET', '/api/products?q=zzzzqqqq');
        $this->assertSame(200, $status);
        $this->assertSame(0, $body['meta']['total']);
    }

    public function testSuggestReturnsQuickResults(): void
    {
        [$status, $body] = $this->call('GET', '/api/search/suggest?q=star');
        $this->assertSame(200, $status);
        $this->assertTrue(count($body['data']) >= 2 && count($body['data']) <= 6);
        $this->assertContains('Stardew Valley', array_column($body['data'], 'name'));
    }

    public function testFilterByPlatformAndRegion(): void
    {
        [, $body] = $this->call('GET', '/api/products?platform=Nintendo&region=EUROPE&per_page=48');
        $this->assertTrue($body['meta']['total'] > 0);
        foreach ($body['data'] as $p) {
            $this->assertSame('Nintendo', $p['platform']);
            $this->assertSame('EUROPE', $p['region']);
        }
    }

    public function testFilterByMultipleCategories(): void
    {
        [, $body] = $this->call('GET', '/api/products?category=rpg,racing&per_page=48');
        $this->assertTrue($body['meta']['total'] > 0);
        foreach ($body['data'] as $p) {
            $this->assertContains($p['category']['slug'], ['rpg', 'racing']);
        }
    }

    public function testFilterByPriceRangeAndSortByPrice(): void
    {
        [, $body] = $this->call('GET', '/api/products?price_min=2000&price_max=4000&sort=price_asc&per_page=48');
        $prices = array_column($body['data'], 'min_price');
        $this->assertTrue(count($prices) > 0);
        foreach ($prices as $price) {
            $this->assertTrue($price >= 2000 && $price <= 4000, "price $price within range");
        }
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices, 'sorted ascending');
    }

    public function testPaginationIsServerSide(): void
    {
        [, $page1] = $this->call('GET', '/api/products?per_page=10&page=1');
        [, $page2] = $this->call('GET', '/api/products?per_page=10&page=2');
        $this->assertSame(10, count($page1['data']));
        $this->assertSame(38, $page1['meta']['total']);
        $this->assertSame(4, $page1['meta']['last_page']);
        $this->assertSame([], array_intersect(array_column($page1['data'], 'id'), array_column($page2['data'], 'id')));
    }

    public function testInvalidFilterValuesReturn422(): void
    {
        $this->assertSame(422, $this->call('GET', '/api/products?sort=cheapest')[0]);
        $this->assertSame(422, $this->call('GET', '/api/products?price_min=abc')[0]);
    }

    public function testProductDetailHasMultipleSellerOffers(): void
    {
        $slug = $this->db()->query(
            'SELECT p.slug FROM products p JOIN offers o ON o.product_id = p.id GROUP BY p.id HAVING COUNT(*) >= 2 LIMIT 1'
        )->fetchColumn();
        [$status, $body] = $this->call('GET', "/api/products/$slug");
        $this->assertSame(200, $status);
        $p = $body['data'];
        $this->assertTrue(count($p['offers']) >= 2, 'several offers');
        $this->assertTrue(count(array_unique(array_column(array_column($p['offers'], 'seller'), 'id'))) === count($p['offers']), 'each offer from a different seller');
        $this->assertTrue(count($p['images']) === 3);
        $this->assertTrue(isset($p['platform'], $p['region'], $p['description']));
    }

    public function testUnknownProductReturns404(): void
    {
        [$status, $body] = $this->call('GET', '/api/products/does-not-exist');
        $this->assertSame(404, $status);
        $this->assertSame('Product not found.', $body['message']);
    }

    public function testHomepageSectionsAreIndependentEndpoints(): void
    {
        foreach (['popular', 'deals', 'new', 'recommended', 'featured'] as $section) {
            [$status, $body] = $this->call('GET', "/api/home/$section");
            $this->assertSame(200, $status, $section);
            $this->assertTrue(count($body['data']) > 0, "$section has products");
        }
        $this->assertSame(404, $this->call('GET', '/api/home/unknown')[0]);
    }
}
