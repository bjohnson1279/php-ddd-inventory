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

            $actorId = $_SERVER['auth.user_id'] ?? 'system';
            $kitQty = (int)$validated['quantity'];
            $saleId = $validated['sale_id'];

            Capsule::transaction(function () use ($id, $kitQty, $saleId, $actorId, $repo, $inventoryService) {
                $kit = $repo->findOrFail($id);
                $inventoryService->decrementForKitSale($kit, $kitQty, $saleId, $actorId);
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
            $validated = $request->validate([
                'kitSku'      => 'required|string',
                'quantity'    => 'required|integer',
                'locationId'  => 'required|string',
                'referenceId' => 'required|string',
            ]);

            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $actorId  = $_SERVER['auth.user_id'] ?? 'system';

            Capsule::transaction(function () use ($useCase, $validated, $tenantId, $actorId) {
                $useCase->execute([
                    'tenantId'    => $tenantId,
                    'locationId'  => $validated['locationId'],
                    'kitSku'      => $validated['kitSku'],
                    'quantity'    => (int)$validated['quantity'],
                    'actorId'     => $actorId,
                    'referenceId' => $validated['referenceId'],
                ]);
            });

            return new Response([
                'message' => "Successfully assembled {$validated['quantity']} units of Kit {$validated['kitSku']}."
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
            $validated = $request->validate([
                'kitSku'      => 'required|string',
                'quantity'    => 'required|integer',
                'locationId'  => 'required|string',
                'referenceId' => 'required|string',
            ]);

            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $actorId  = $_SERVER['auth.user_id'] ?? 'system';

            Capsule::transaction(function () use ($useCase, $validated, $tenantId, $actorId) {
                $useCase->execute([
                    'tenantId'    => $tenantId,
                    'locationId'  => $validated['locationId'],
                    'kitSku'      => $validated['kitSku'],
                    'quantity'    => (int)$validated['quantity'],
                    'actorId'     => $actorId,
                    'referenceId' => $validated['referenceId'],
                ]);
            });

            return new Response([
                'message' => "Successfully disassembled {$validated['quantity']} units of Kit {$validated['kitSku']}."
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
