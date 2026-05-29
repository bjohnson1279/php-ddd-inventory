<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Identity\UseCases\RegisterUser;
use InventoryApp\Application\Identity\UseCases\AuthenticateUser;
use Exception;

class AuthController
{
    /**
     * POST /auth/register
     *
     * Body: { "id": "...", "tenant_id": "...", "email": "...", "password": "...", "name": "..." }
     */
    public function register(RequestInterface $request, RegisterUser $useCase): Response
    {
        try {
            $validated = $request->validate([
                'id'        => 'required|string',
                'tenant_id' => 'required|string',
                'email'     => 'required|string',
                'password'  => 'required|string',
                'name'      => 'required|string',
            ]);

            $useCase->execute(
                $validated['id'],
                $validated['tenant_id'],
                $validated['email'],
                $validated['password'],
                $validated['name']
            );

            return new Response(['message' => 'User registered successfully.'], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /auth/login
     *
     * Body: { "email": "...", "password": "...", "tenant_id": "..." }
     * Returns: { "token": "<bearer-token>" }
     */
    public function login(RequestInterface $request, AuthenticateUser $useCase): Response
    {
        try {
            $validated = $request->validate([
                'email'     => 'required|string',
                'password'  => 'required|string',
                'tenant_id' => 'required|string',
            ]);

            $token = $useCase->execute(
                $validated['email'],
                $validated['password'],
                $validated['tenant_id']
            );

            return new Response(['token' => $token], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 401);
        }
    }
}
