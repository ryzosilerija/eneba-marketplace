<?php
declare(strict_types=1);

namespace Tests;

final class AuthTest extends TestCase
{
    public function testUserCanRegister(): void
    {
        [$status, $body] = $this->call('POST', '/api/auth/register', [
            'name' => 'New Player', 'email' => 'new@example.com', 'password' => 'password123',
        ]);
        $this->assertSame(201, $status);
        $this->assertSame('new@example.com', $body['user']['email']);
        $this->assertTrue(strlen($body['token']) === 80, 'token returned');
        $this->assertTrue(!isset($body['user']['password_hash']), 'password hash must never be returned');
    }

    public function testRegisterValidatesInput(): void
    {
        [$status, $body] = $this->call('POST', '/api/auth/register', ['name' => 'X', 'email' => 'not-an-email', 'password' => 'short']);
        $this->assertSame(422, $status);
        $this->assertTrue(isset($body['errors']['name'], $body['errors']['email'], $body['errors']['password']), 'field errors');
    }

    public function testCannotRegisterSameEmailTwice(): void
    {
        $this->registerUser('dup@example.com');
        [$status, $body] = $this->call('POST', '/api/auth/register', ['name' => 'Again', 'email' => 'DUP@example.com', 'password' => 'password123']);
        $this->assertSame(422, $status);
        $this->assertTrue(isset($body['errors']['email']));
    }

    public function testUserCanLoginAndFetchProfile(): void
    {
        [$status, $body] = $this->call('POST', '/api/auth/login', ['email' => 'demo@keyvault.test', 'password' => 'password123']);
        $this->assertSame(200, $status);

        [$status, $me] = $this->call('GET', '/api/auth/me', [], ['Authorization' => 'Bearer ' . $body['token']]);
        $this->assertSame(200, $status);
        $this->assertSame('demo@keyvault.test', $me['user']['email']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        [$status, $body] = $this->call('POST', '/api/auth/login', ['email' => 'demo@keyvault.test', 'password' => 'wrong-password']);
        $this->assertSame(401, $status);
        $this->assertSame('Invalid email or password.', $body['message']);
    }

    public function testLogoutRevokesToken(): void
    {
        $auth = $this->registerUser();
        [$status] = $this->call('POST', '/api/auth/logout', [], $auth);
        $this->assertSame(204, $status);
        [$status] = $this->call('GET', '/api/auth/me', [], $auth);
        $this->assertSame(401, $status);
    }

    public function testProtectedRoutesRequireToken(): void
    {
        $this->assertSame(401, $this->call('GET', '/api/orders')[0]);
        $this->assertSame(401, $this->call('GET', '/api/orders', [], ['Authorization' => 'Bearer invalid'])[0]);
        $this->assertSame(401, $this->call('POST', '/api/checkout')[0]);
    }
}
