<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuthService;

final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function register(Request $request): Response
    {
        $data = Validator::validate($request->body, [
            'name'     => 'required|string|min:2|max:100',
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:8|max:72', // bcrypt only uses the first 72 bytes
        ]);
        $result = $this->auth->register($data['name'], $data['email'], $data['password'], $request->header('x-cart-token'));
        return Response::json($result, 201);
    }

    public function login(Request $request): Response
    {
        $data = Validator::validate($request->body, [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        return Response::json($this->auth->login($data['email'], $data['password'], $request->header('x-cart-token')));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout((string) $request->bearerToken());
        return Response::noContent();
    }

    public function me(Request $request): Response
    {
        return Response::json(['user' => User::toPublic($request->user)]);
    }
}
