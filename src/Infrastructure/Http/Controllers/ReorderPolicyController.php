<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Ramsey\Uuid\Uuid;
use Exception;

class ReorderPolicyController
{
    public function createOrUpdate(RequestInterface $request, ReorderPolicyRepositoryInterface $repo)
    {
        try {
            $body = $request->validate([
                'sku'             => 'required|string',
                'locationId'      => 'required|string',
                'reorderPoint'    => 'required|integer|min:0',
                'reorderQuantity' => 'required|integer|min:1',
                'safetyStock'     => 'required|integer|min:0'
            ]);

            $id = Uuid::uuid4()->toString();
            $policy = new ReorderPolicy(
                $id,
                new SKU($body['sku']),
                $body['locationId'],
                (int) $body['reorderPoint'],
                (int) $body['reorderQuantity'],
                (int) $body['safetyStock']
            );

            $repo->save($policy);

            return new Response([
                'id'              => $policy->id,
                'sku'             => $policy->sku->getValue(),
                'locationId'      => $policy->locationId,
                'reorderPoint'    => $policy->reorderPoint,
                'reorderQuantity' => $policy->reorderQuantity,
                'safetyStock'     => $policy->safetyStock
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function get(RequestInterface $request, string $sku, string $locationId, ReorderPolicyRepositoryInterface $repo)
    {
        try {
            $policy = $repo->findBySkuAndLocation(new SKU($sku), $locationId);
            if (!$policy) {
                return new Response(['error' => 'Reorder policy not found'], 404);
            }

            return new Response([
                'id'              => $policy->id,
                'sku'             => $policy->sku->getValue(),
                'locationId'      => $policy->locationId,
                'reorderPoint'    => $policy->reorderPoint,
                'reorderQuantity' => $policy->reorderQuantity,
                'safetyStock'     => $policy->safetyStock
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
