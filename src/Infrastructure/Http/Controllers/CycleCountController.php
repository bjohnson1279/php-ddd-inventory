<?php

namespace App\Infrastructure\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CycleCountController
{
    public function start(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode(['id' => 'cc-123', 'status' => 'PENDING']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function submit(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function list(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
