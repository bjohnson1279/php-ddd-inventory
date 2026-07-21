<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Procurement\Services\DemandVelocityCalculator;
use InventoryApp\Domain\Procurement\Services\ReorderPointForecaster;
use InventoryApp\Domain\Procurement\Services\ReorderPolicyService;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Infrastructure\ServiceContainer;
use Ramsey\Uuid\Uuid;
use Exception;

class ReorderPolicyController
{
    public function createOrUpdate(RequestInterface $request, ReorderPolicyRepositoryInterface $repo)
    {
        try {
            $body = $request->validate([
                'sku'                 => 'required|string',
                'locationId'          => 'required|string',
                'reorderPoint'        => 'required|integer|min:0',
                'reorderQuantity'     => 'required|integer|min:1',
                'safetyStock'         => 'required|integer|min:0',
                'dynamicRopEnabled'   => 'nullable|boolean'
            ]);

            $id = Uuid::uuid4()->toString();
            $policy = new ReorderPolicy(
                $id,
                new SKU($body['sku']),
                $body['locationId'],
                (int) $body['reorderPoint'],
                (int) $body['reorderQuantity'],
                (int) $body['safetyStock'],
                (bool) ($body['dynamicRopEnabled'] ?? false)
            );

            $repo->save($policy);

            return new Response([
                'id'                 => $policy->id,
                'sku'                => $policy->sku->getValue(),
                'locationId'         => $policy->locationId,
                'reorderPoint'       => $policy->reorderPoint,
                'reorderQuantity'    => $policy->reorderQuantity,
                'safetyStock'        => $policy->safetyStock,
                'dynamicRopEnabled'  => $policy->dynamicRopEnabled
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ReorderPolicyController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
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
                'id'                 => $policy->id,
                'sku'                => $policy->sku->getValue(),
                'locationId'         => $policy->locationId,
                'reorderPoint'       => $policy->reorderPoint,
                'reorderQuantity'    => $policy->reorderQuantity,
                'safetyStock'        => $policy->safetyStock,
                'dynamicRopEnabled'  => $policy->dynamicRopEnabled
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ReorderPolicyController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function evaluate(RequestInterface $request, ReorderPolicyRepositoryInterface $repo)
    {
        try {
            $container = ServiceContainer::getInstance();
            $tenantId = function_exists('tenantId') ? tenantId() : 'system';

            $productRepo = $container->make(ProductRepositoryInterface::class, ['tenantId' => $tenantId]);
            $ledgerRepo = $container->make(LedgerRepositoryInterface::class, ['tenantId' => $tenantId]);
            $poRepo = $container->make(PurchaseOrderRepositoryInterface::class);
            $service = $container->make(ReorderPolicyService::class);

            $velocityCalculator = new DemandVelocityCalculator($ledgerRepo, $productRepo);
            $forecaster = new ReorderPointForecaster($velocityCalculator, $productRepo, $poRepo);

            $results = $service->evaluatePolicies($tenantId, $forecaster, $productRepo, $ledgerRepo);

            return new Response(['results' => $results], 200);
        } catch (Exception $e) {
            error_log('[ReorderPolicyController.php evaluate] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }
}
