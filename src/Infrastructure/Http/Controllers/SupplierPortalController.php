<?php

namespace App\Infrastructure\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SupplierPortalController
{
    public function submitASN(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode(['id' => 'asn-123', 'status' => 'SUBMITTED']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function listASNs(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
