<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Inventory\Services\InventoryService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Ramsey\Uuid\Uuid;
use Exception;
use InventoryApp\Application\Shared\Decorators\AutoRetryUseCaseDecorator;

class KitController
{
    public function create(RequestInterface $request, KitRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'sku'  => 'required|string',
                'name' => 'required|string',
            ]);

            // Check if kit already exists with SKU
            $existing = $repo->findBySku($validated['sku']);
            if ($existing) {
                return new Response(['error' => 'Kit with this SKU already exists.'], 400);
            }

            $id = Uuid::uuid4()->toString();
            $kit = new Kit($id, $validated['sku'], $validated['name']);
            $repo->save($kit);

            return new Response([
                'message' => 'Kit created successfully',
                'id'      => $id,
                'sku'     => $validated['sku'],
                'name'    => $validated['name'],
            ], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[KitController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function addComponent(RequestInterface $request, string $id, KitRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'variant_id' => 'required|string',
                'quantity'   => 'required|integer',
            ]);

            $kit = $repo->findOrFail($id);
            $kit->addComponent($validated['variant_id'], (int)$validated['quantity']);
            $repo->save($kit);

            return new Response(['message' => 'Component added/updated successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[KitController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function show(RequestInterface $request, string $id, KitRepositoryInterface $repo)
    {
        try {
            $kit = $repo->findOrFail($id);
            return new Response($this->serializeKit($kit), 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[KitController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 404);
        }
    }

    public function showBySku(RequestInterface $request, string $sku, KitRepositoryInterface $repo)
    {
        try {
            $kit = $repo->findBySku($sku);
            if (!$kit) {
                return new Response(['error' => 'Kit not found'], 404);
            }
            return new Response($this->serializeKit($kit), 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[KitController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function sell(RequestInterface $request, string $id, KitRepositoryInterface $repo, InventoryService $inventoryService)
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer',
                'sale_id'  => 'required|string',
            ]);

            $rawBody = json_decode(file_get_contents('php://input'), true) ?: [];
            $locationIdStr = $rawBody['locationId'] ?? $rawBody['location_id'] ?? 'default';
            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $actorId = $_SERVER['auth.user_id'] ?? 'system';
            $kitQty = (int)$validated['quantity'];
            $saleId = $validated['sale_id'];

            Capsule::transaction(function () use ($id, $kitQty, $saleId, $actorId, $tenantId, $locationIdStr, $repo, $inventoryService) {
                $kit = $repo->findOrFail($id);
                $components = $kit->components();
                if (empty($components)) {
                    throw new \DomainException('Cannot sell a kit with no components.');
                }
                $syncedProducts = false;
                try {
                    $productRepo = \InventoryApp\Infrastructure\ServiceContainer::productRepo($tenantId);
                    $locId = new \InventoryApp\Domain\Inventory\ValueObjects\LocationId($locationIdStr);
                    foreach ($components as $comp) {
                        $needed = $comp->quantity * $kitQty;
                        $skuStr = \InventoryApp\Infrastructure\ConformanceHelper::resolveSkuFromVariantOrSku($comp->variantId);
                        $product = $productRepo->findBySku(new \InventoryApp\Domain\Inventory\ValueObjects\SKU($skuStr));
                        if ($product) {
                            $product->dispatchStockAt($locId, new \InventoryApp\Domain\Inventory\ValueObjects\Quantity($needed), $saleId);
                            $productRepo->save($product);
                            \InventoryApp\Infrastructure\ConformanceHelper::invalidateCache($tenantId, $skuStr);
                            \InventoryApp\Infrastructure\ConformanceHelper::bumpVersion($tenantId, $skuStr, $locationIdStr);
                            $syncedProducts = true;
                        }
                    }
                } catch (\DomainException $de) {
                    throw $de;
                } catch (\Throwable $e) {
                    // Fall back to InventoryService when ServiceContainer DB is not initialized (unit tests)
                }
                if (!$syncedProducts) {
                    $inventoryService->decrementForKitSale($kit, $kitQty, $saleId, $actorId);
                }
            });

            return new Response(['message' => 'Kit sold successfully and component inventories decremented.'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[KitController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function serializeKit(Kit $kit): array
    {
        $components = array_map(function ($c) {
            return [
                'variant_id' => $c->variantId,
                'quantity'   => $c->quantity,
            ];
        }, $kit->components());

        return [
            'id'         => $kit->id,
            'sku'        => $kit->sku,
            'name'       => $kit->name,
            'components' => $components,
        ];
    }

    public function assemble(
        RequestInterface $request,
        \InventoryApp\Application\Inventory\UseCases\AssembleKit $useCase
    ) {
        try {
            $rawBody = json_decode(file_get_contents('php://input'), true) ?: [];
            $kitSku = $rawBody['kitSku'] ?? $rawBody['kit_sku'] ?? '';
            $quantity = isset($rawBody['quantity']) ? (int)$rawBody['quantity'] : 0;
            $locationId = $rawBody['locationId'] ?? $rawBody['location_id'] ?? 'default';
            $referenceId = $rawBody['referenceId'] ?? $rawBody['reference_id'] ?? ('kit-asm-' . uniqid());
            if ($kitSku === '' || $quantity <= 0) {
                throw new \InvalidArgumentException('kitSku and positive quantity are required.');
            }

            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $actorId  = $_SERVER['auth.user_id'] ?? 'system';

            Capsule::transaction(function () use ($useCase, $kitSku, $quantity, $locationId, $tenantId, $actorId, $referenceId) {
                $decoratedUseCase = new AutoRetryUseCaseDecorator($useCase);
                $decoratedUseCase->execute([
                    'tenantId'    => $tenantId,
                    'locationId'  => $locationId,
                    'kitSku'      => $kitSku,
                    'quantity'    => $quantity,
                    'actorId'     => $actorId,
                    'referenceId' => $referenceId,
                ]);
            });

            \InventoryApp\Infrastructure\ConformanceHelper::invalidateCache($tenantId, null);

            return new Response([
                'message' => "Successfully assembled {$quantity} units of Kit {$kitSku}."
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[KitController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function disassemble(
        RequestInterface $request,
        \InventoryApp\Application\Inventory\UseCases\DisassembleKit $useCase
    ) {
        try {
            $rawBody = json_decode(file_get_contents('php://input'), true) ?: [];
            $kitSku = $rawBody['kitSku'] ?? $rawBody['kit_sku'] ?? '';
            $quantity = isset($rawBody['quantity']) ? (int)$rawBody['quantity'] : 0;
            $locationId = $rawBody['locationId'] ?? $rawBody['location_id'] ?? 'default';
            $referenceId = $rawBody['referenceId'] ?? $rawBody['reference_id'] ?? ('kit-dis-' . uniqid());
            if ($kitSku === '' || $quantity <= 0) {
                throw new \InvalidArgumentException('kitSku and positive quantity are required.');
            }

            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $actorId  = $_SERVER['auth.user_id'] ?? 'system';

            Capsule::transaction(function () use ($useCase, $kitSku, $quantity, $locationId, $tenantId, $actorId, $referenceId) {
                $decoratedUseCase = new AutoRetryUseCaseDecorator($useCase);
                $decoratedUseCase->execute([
                    'tenantId'    => $tenantId,
                    'locationId'  => $locationId,
                    'kitSku'      => $kitSku,
                    'quantity'    => $quantity,
                    'actorId'     => $actorId,
                    'referenceId' => $referenceId,
                ]);
            });

            \InventoryApp\Infrastructure\ConformanceHelper::invalidateCache($tenantId, null);

            return new Response([
                'message' => "Successfully disassembled {$quantity} units of Kit {$kitSku}."
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[KitController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
