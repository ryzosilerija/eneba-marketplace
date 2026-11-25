<?php
declare(strict_types=1);

namespace Tests;

final class CheckoutTest extends TestCase
{
    /** Puts an offer in the user's cart and places an order. Returns the order. */
    private function placeOrder(array $auth, int $offerId, int $qty = 1): array
    {
        $this->call('POST', '/api/cart/items', ['offer_id' => $offerId, 'quantity' => $qty], $auth);
        [$status, $body] = $this->call('POST', '/api/checkout', [], $auth);
        $this->assertSame(201, $status, 'checkout');
        return $body['data'];
    }

    public function testSuccessfulCheckoutAssignsKeys(): void
    {
        $auth = $this->registerUser();
        $offer = $this->inStockOffer(2);
        $order = $this->placeOrder($auth, (int) $offer['id'], 2);
        $this->assertSame('pending', $order['status']);
        $this->assertSame([], $order['items'][0]['keys'], 'no keys before payment');

        [$status, $body] = $this->call('POST', "/api/orders/{$order['id']}/pay", self::VALID_CARD, $auth);
        $this->assertSame(200, $status);
        $this->assertSame('paid', $body['data']['status']);
        $keys = $body['data']['items'][0]['keys'];
        $this->assertSame(2, count($keys));
        $this->assertTrue(str_starts_with($keys[0], 'KVTEST-'), 'fictional test key');

        // Keys are now sold, and the cart is emptied
        $sold = $this->db()->query("SELECT COUNT(*) FROM digital_keys WHERE status = 'sold'")->fetchColumn();
        $this->assertSame(2, (int) $sold);
        [, $cart] = $this->call('GET', '/api/cart', [], $auth);
        $this->assertSame(0, $cart['item_count']);

        // Order shows up in the user's history
        [, $orders] = $this->call('GET', '/api/orders', [], $auth);
        $this->assertSame($order['id'], $orders['data'][0]['id']);
    }

    public function testDeclinedPaymentMarksOrderFailedAndAssignsNoKeys(): void
    {
        $auth = $this->registerUser();
        $order = $this->placeOrder($auth, (int) $this->inStockOffer()['id']);

        [$status, $body] = $this->call('POST', "/api/orders/{$order['id']}/pay", ['card_number' => '4000 0000 0000 0002'] + self::VALID_CARD, $auth);
        $this->assertSame(402, $status);
        $this->assertSame('failed', $body['data']['status']);
        $this->assertSame('Your card was declined.', $body['message']);
        $this->assertSame(0, (int) $this->db()->query("SELECT COUNT(*) FROM digital_keys WHERE status = 'sold'")->fetchColumn());

        // The user can retry with another card
        [$status] = $this->call('POST', "/api/orders/{$order['id']}/pay", self::VALID_CARD, $auth);
        $this->assertSame(200, $status);
    }

    public function testCannotPayTwice(): void
    {
        $auth = $this->registerUser();
        $order = $this->placeOrder($auth, (int) $this->inStockOffer()['id']);
        $this->call('POST', "/api/orders/{$order['id']}/pay", self::VALID_CARD, $auth);
        [$status] = $this->call('POST', "/api/orders/{$order['id']}/pay", self::VALID_CARD, $auth);
        $this->assertSame(409, $status);
        $this->assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM digital_keys WHERE status = 'sold'")->fetchColumn());
    }

    public function testEmptyCartCannotCheckout(): void
    {
        $auth = $this->registerUser();
        [$status, $body] = $this->call('POST', '/api/checkout', [], $auth);
        $this->assertSame(422, $status);
        $this->assertSame('Your cart is empty.', $body['message']);
    }

    public function testPaymentValidation(): void
    {
        $auth = $this->registerUser();
        $order = $this->placeOrder($auth, (int) $this->inStockOffer()['id']);
        $pay = fn (array $card) => $this->call('POST', "/api/orders/{$order['id']}/pay", $card + self::VALID_CARD, $auth);

        [$s, $b] = $pay(['card_number' => '1234 5678 9012 3456']);
        $this->assertSame(422, $s);
        $this->assertTrue(isset($b['errors']['card_number']));
        [$s, $b] = $pay(['expiry' => '01/20']);
        $this->assertSame(422, $s);
        $this->assertTrue(isset($b['errors']['expiry']));
        [$s] = $pay(['cvc' => '12']);
        $this->assertSame(422, $s);
    }

    /**
     * The same key must never go to two users. An offer has ONE key left; two users both
     * get it into their orders (stock was 1 for each at checkout time). Only the first payment
     * may succeed; the second gets 409 and no key.
     */
    public function testSameKeyIsNeverSoldToTwoUsers(): void
    {
        $offer = $this->inStockOffer();
        $this->db()->exec("DELETE FROM digital_keys WHERE offer_id = {$offer['id']} AND id NOT IN (SELECT MIN(id) FROM digital_keys WHERE offer_id = {$offer['id']})");

        $alice = $this->registerUser('alice2@example.com');
        $bob = $this->registerUser('bob2@example.com');
        $aliceOrder = $this->placeOrder($alice, (int) $offer['id']);
        $bobOrder = $this->placeOrder($bob, (int) $offer['id']);

        [$s1] = $this->call('POST', "/api/orders/{$aliceOrder['id']}/pay", self::VALID_CARD, $alice);
        [$s2, $b2] = $this->call('POST', "/api/orders/{$bobOrder['id']}/pay", self::VALID_CARD, $bob);
        $this->assertSame(200, $s1);
        $this->assertSame(409, $s2);
        $this->assertTrue(str_contains($b2['message'], 'sold out'));

        [, $bobView] = $this->call('GET', "/api/orders/{$bobOrder['id']}", [], $bob);
        $this->assertSame('failed', $bobView['data']['status']);
        $this->assertSame([], $bobView['data']['items'][0]['keys']);

        // Database-level guarantee: every sold key belongs to exactly one order item
        $dupes = $this->db()->query('SELECT code FROM digital_keys GROUP BY code HAVING COUNT(*) > 1')->fetchAll();
        $this->assertSame([], $dupes);
    }

    public function testAssigningAnAlreadySoldKeyDoesNothing(): void
    {
        $auth = $this->registerUser();
        $order = $this->placeOrder($auth, (int) $this->inStockOffer()['id']);
        $this->call('POST', "/api/orders/{$order['id']}/pay", self::VALID_CARD, $auth);
        $keyId = (int) $this->db()->query("SELECT id FROM digital_keys WHERE status = 'sold' LIMIT 1")->fetchColumn();

        // Try to re-assign it to a different order item directly at model level
        $changed = \App\Models\DigitalKey::assign([$keyId], $order['items'][0]['id'] + 1000);
        $this->assertSame(0, $changed);
    }
}
