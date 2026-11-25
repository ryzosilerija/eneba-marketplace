<?php
declare(strict_types=1);

namespace Tests;

final class CartTest extends TestCase
{
    public function testGuestCanAddOfferAndCartIsRemembered(): void
    {
        $offer = $this->inStockOffer(2);
        [$status, $cart] = $this->call('POST', '/api/cart/items', ['offer_id' => (int) $offer['id'], 'quantity' => 2]);
        $this->assertSame(201, $status);
        $this->assertTrue(is_string($cart['cart_token']), 'guest gets a cart token');
        $this->assertSame((int) $offer['id'], $cart['items'][0]['offer_id'], 'the selected seller offer is stored');

        // "Refresh": a new request with only the token sees the same cart
        [, $again] = $this->call('GET', '/api/cart', [], ['X-Cart-Token' => $cart['cart_token']]);
        $this->assertSame(2, $again['item_count']);
        $this->assertSame($cart['total'], $again['total']);
    }

    public function testAddingSameOfferTwiceIncreasesQuantity(): void
    {
        $auth = $this->registerUser();
        $offer = $this->inStockOffer(3);
        $this->call('POST', '/api/cart/items', ['offer_id' => (int) $offer['id']], $auth);
        [, $cart] = $this->call('POST', '/api/cart/items', ['offer_id' => (int) $offer['id'], 'quantity' => 2], $auth);
        $this->assertSame(1, count($cart['items']));
        $this->assertSame(3, $cart['items'][0]['quantity']);
        $this->assertSame($cart['items'][0]['unit_price'] * 3, $cart['total']);
    }

    public function testUpdateAndRemoveItems(): void
    {
        $auth = $this->registerUser();
        $offer = $this->inStockOffer(3);
        [, $cart] = $this->call('POST', '/api/cart/items', ['offer_id' => (int) $offer['id']], $auth);
        $itemId = $cart['items'][0]['id'];

        [$status, $cart] = $this->call('PATCH', "/api/cart/items/$itemId", ['quantity' => 3], $auth);
        $this->assertSame(200, $status);
        $this->assertSame(3, $cart['item_count']);

        [$status, $cart] = $this->call('DELETE', "/api/cart/items/$itemId", [], $auth);
        $this->assertSame(200, $status);
        $this->assertSame(0, $cart['item_count']);
        $this->assertSame(0, $cart['total']);
    }

    public function testCannotAddMoreThanStock(): void
    {
        $auth = $this->registerUser();
        $offer = $this->inStockOffer();
        $this->db()->exec("DELETE FROM digital_keys WHERE offer_id = {$offer['id']} AND id NOT IN (SELECT MIN(id) FROM digital_keys WHERE offer_id = {$offer['id']})");

        [$status, $body] = $this->call('POST', '/api/cart/items', ['offer_id' => (int) $offer['id'], 'quantity' => 2], $auth);
        $this->assertSame(409, $status);
        $this->assertSame('Only 1 key(s) left for this offer.', $body['message']);
    }

    public function testValidationAndUnknownOffer(): void
    {
        $this->assertSame(422, $this->call('POST', '/api/cart/items', ['offer_id' => 'abc'])[0]);
        $this->assertSame(422, $this->call('POST', '/api/cart/items', ['offer_id' => 1, 'quantity' => 50])[0]);
        $this->assertSame(404, $this->call('POST', '/api/cart/items', ['offer_id' => 999999])[0]);
    }

    public function testGuestCartIsMergedOnLogin(): void
    {
        $offer = $this->inStockOffer();
        [, $guest] = $this->call('POST', '/api/cart/items', ['offer_id' => (int) $offer['id']]);

        [, $login] = $this->call('POST', '/api/auth/login',
            ['email' => 'demo@keyvault.test', 'password' => 'password123'],
            ['X-Cart-Token' => $guest['cart_token']]);
        [, $cart] = $this->call('GET', '/api/cart', [], ['Authorization' => 'Bearer ' . $login['token']]);
        $this->assertSame((int) $offer['id'], $cart['items'][0]['offer_id']);
    }

    public function testUsersCannotTouchOtherUsersCartItems(): void
    {
        $alice = $this->registerUser('a@example.com');
        $bob = $this->registerUser('b@example.com');
        [, $cart] = $this->call('POST', '/api/cart/items', ['offer_id' => (int) $this->inStockOffer()['id']], $alice);
        $this->call('POST', '/api/cart/items', ['offer_id' => (int) $this->inStockOffer()['id']], $bob);

        $this->assertSame(404, $this->call('DELETE', '/api/cart/items/' . $cart['items'][0]['id'], [], $bob)[0]);
        $this->assertSame(404, $this->call('PATCH', '/api/cart/items/' . $cart['items'][0]['id'], ['quantity' => 2], $bob)[0]);
    }
}
