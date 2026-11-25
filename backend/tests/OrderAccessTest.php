<?php
declare(strict_types=1);

namespace Tests;

/** Users must never see other people's orders or the keys inside them. */
final class OrderAccessTest extends TestCase
{
    private function paidOrderFor(array $auth): array
    {
        $this->call('POST', '/api/cart/items', ['offer_id' => (int) $this->inStockOffer()['id']], $auth);
        [, $order] = $this->call('POST', '/api/checkout', [], $auth);
        [, $paid] = $this->call('POST', "/api/orders/{$order['data']['id']}/pay", self::VALID_CARD, $auth);
        return $paid['data'];
    }

    public function testUserCannotViewAnotherUsersOrder(): void
    {
        $owner = $this->registerUser('owner@example.com');
        $intruder = $this->registerUser('intruder@example.com');
        $order = $this->paidOrderFor($owner);

        [$status, $body] = $this->call('GET', "/api/orders/{$order['id']}", [], $intruder);
        $this->assertSame(404, $status);
        $this->assertTrue(!isset($body['data']), 'no order data leaked');
        $this->assertTrue(!str_contains(json_encode($body), 'KVTEST-'), 'no keys leaked');
    }

    public function testUserCannotPayForAnotherUsersOrder(): void
    {
        $owner = $this->registerUser('owner@example.com');
        $intruder = $this->registerUser('intruder@example.com');
        $this->call('POST', '/api/cart/items', ['offer_id' => (int) $this->inStockOffer()['id']], $owner);
        [, $order] = $this->call('POST', '/api/checkout', [], $owner);

        [$status] = $this->call('POST', "/api/orders/{$order['data']['id']}/pay", self::VALID_CARD, $intruder);
        $this->assertSame(404, $status);
    }

    public function testOrderListOnlyContainsOwnOrders(): void
    {
        $owner = $this->registerUser('owner@example.com');
        $other = $this->registerUser('other@example.com');
        $this->paidOrderFor($owner);

        [, $list] = $this->call('GET', '/api/orders', [], $other);
        $this->assertSame([], $list['data']);
        [, $list] = $this->call('GET', '/api/orders', [], $owner);
        $this->assertSame(1, count($list['data']));
    }

    public function testOwnerSeesTheirKeys(): void
    {
        $owner = $this->registerUser('owner@example.com');
        $order = $this->paidOrderFor($owner);
        [$status, $body] = $this->call('GET', "/api/orders/{$order['id']}", [], $owner);
        $this->assertSame(200, $status);
        $this->assertSame(1, count($body['data']['items'][0]['keys']));
    }
}
