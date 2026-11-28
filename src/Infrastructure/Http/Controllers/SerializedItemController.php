<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Serial\Services\SerializedInventoryService;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;
use InventoryApp\Domain\Serial\Enums\SerializedItemStatus;
use InventoryApp\Domain\Serial\Aggregates\SerializedItem;
use Exception;

class SerializedItemController
{
    public function register(RequestInterface $request, SerializedInventoryService $service)
    {
        try {
            $validated = $request->validate([
                'variant_id'    => 'required|string',
                'serial_number' => 'required|string',
                'location_id'   => 'required|string',
            ]);

            $serial = new SerialNumber($validated['serial_number']);
            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $item = $service->register($serial, $validated['variant_id'], $tenantId, $validated['location_id'], $actorId);

            return new Response([
                'message' => 'Serial number registered successfully',
                'id'      => $item->id,
            ], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function receive(RequestInterface $request, string $id, SerializedInventoryService $service, SerializedItemRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'location_id'       => 'required|string',
                'purchase_order_id' => 'required|string',
            ]);

            // Body parameters could have unit_cost_cents
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $unitCostCents = (int)($body['unit_cost_cents'] ?? 0);

            $item = $repo->findById($id);
            if (!$item) {
                return new Response(['error' => 'Serial item not found'], 404);
            }

            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $service->receive($item->serialNumber, $item->tenantId, $validated['location_id'], $validated['purchase_order_id'], $unitCostCents, $actorId);
            $this->adjustProductStock($item->tenantId, $item->variantId, $validated['location_id'], 1, $validated['purchase_order_id']);

            return new Response(['message' => 'Serial item received'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function sell(RequestInterface $request, string $id, SerializedInventoryService $service, SerializedItemRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'sale_id' => 'required|string',
            ]);

            $item = $repo->findById($id);
            if (!$item) {
                return new Response(['error' => 'Serial item not found'], 404);
            }

            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $service->sell($item->serialNumber, $item->tenantId, $validated['sale_id'], $actorId);
            $this->adjustProductStock($item->tenantId, $item->variantId, $item->locationId() ?? 'default', -1, $validated['sale_id']);

            return new Response(['message' => 'Serial item sold'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function acceptReturn(RequestInterface $request, string $id, SerializedInventoryService $service, SerializedItemRepositoryInterface $repo)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $returnId = $body['return_id'] ?? $body['returnId'] ?? $body['reference_id'] ?? $body['referenceId'] ?? '';
            if ($returnId === '') {
                throw new \InvalidArgumentException('return_id is required.');
            }

            $item = $repo->findById($id);
            if (!$item) {
                return new Response(['error' => 'Serial item not found'], 404);
            }

            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $service->acceptReturn($item->serialNumber, $item->tenantId, (string)$returnId, $actorId);

            return new Response(['message' => 'Serial item return accepted'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function restock(RequestInterface $request, string $id, SerializedInventoryService $service, SerializedItemRepositoryInterface $repo)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $returnId = $body['return_id'] ?? $body['returnId'] ?? $body['reference_id'] ?? $body['referenceId'] ?? '';
            if ($returnId === '') {
                throw new \InvalidArgumentException('return_id is required.');
            }
            $restockedCostCents = (int)($body['restocked_unit_cost_cents'] ?? $body['unit_cost_cents'] ?? $body['unitCostCents'] ?? 0);

            $item = $repo->findById($id);
            if (!$item) {
                return new Response(['error' => 'Serial item not found'], 404);
            }

            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $service->restock($item->serialNumber, $item->tenantId, (string)$returnId, $restockedCostCents, $actorId);
            $this->adjustProductStock($item->tenantId, $item->variantId, $item->locationId() ?? 'default', 1, (string)$returnId);

            return new Response(['message' => 'Serial item restocked'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function writeOff(RequestInterface $request, string $id, SerializedInventoryService $service, SerializedItemRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string',
            ]);

            // Body parameters could have reference_id
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $referenceId = $body['reference_id'] ?? null;

            $item = $repo->findById($id);
            if (!$item) {
                return new Response(['error' => 'Serial item not found'], 404);
            }

            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $service->writeOff($item->serialNumber, $item->tenantId, $validated['reason'], $actorId, $referenceId);

            return new Response(['message' => 'Serial item written off'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function lookup(RequestInterface $request, SerializedItemRepositoryInterface $repo)
    {
        try {
            $serialNumber = $request->query('serial_number');
            if (empty($serialNumber)) {
                return new Response(['error' => 'serial_number query parameter is required'], 400);
            }

            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $item = $repo->findBySerial(new SerialNumber($serialNumber), $tenantId);

            if (!$item) {
                return new Response(['error' => 'Serial number not found'], 404);
            }

            return new Response($this->serializeItem($item), 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function listByVariant(RequestInterface $request, string $variantId, SerializedItemRepositoryInterface $repo)
    {
        try {
            $statusStr = $request->query('status');
            $status = null;
            if ($statusStr) {
                $status = SerializedItemStatus::tryFrom($statusStr);
                if ($status === null) {
                    return new Response(['error' => 'Invalid status parameter'], 400);
                }
            }

            $items = $repo->findByVariant($variantId, $status);
            $serialized = array_map(fn($item) => $this->serializeItem($item), $items);

            return new Response(['items' => $serialized], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function countByStatus(RequestInterface $request, string $variantId, SerializedItemRepositoryInterface $repo)
    {
        try {
            $statusStr = $request->query('status');
            if (empty($statusStr)) {
                return new Response(['error' => 'status query parameter is required'], 400);
            }
            $status = SerializedItemStatus::tryFrom($statusStr);
            if ($status === null) {
                return new Response(['error' => 'Invalid status parameter'], 400);
            }

            $count = $repo->countByStatus($variantId, $status);

            return new Response(['count' => $count], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[SerializedItemController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function serializeItem(SerializedItem $item): array
    {
        $history = array_map(function ($t) {
            return [
                'from'        => $t->from->value,
                'to'          => $t->to->value,
                'reason'      => $t->reason,
                'actorId'     => $t->actorId,
                'referenceId' => $t->referenceId,
                'occurredAt'  => $t->occurredAt->format(\DateTimeInterface::ATOM),
            ];
        }, $item->history());

        return [
            'id'            => $item->id,
            'variant_id'    => $item->variantId,
            'variantId'     => $item->variantId,
            'serial_number' => $item->serialNumber->value,
            'serialNumber'  => $item->serialNumber->value,
            'tenant_id'     => $item->tenantId,
            'tenantId'      => $item->tenantId,
            'location_id'   => $item->locationId(),
            'locationId'    => $item->locationId(),
            'status'        => $item->status()->value,
            'history'       => $history,
        ];
    }

    private function adjustProductStock(string $tenantId, string $variantId, string $locationId, int $delta, string $reference): void
    {
        try {
            $skuStr = \InventoryApp\Infrastructure\ConformanceHelper::resolveSkuFromVariantOrSku($variantId);
            \InventoryApp\Infrastructure\ConformanceHelper::ensureLocationExists($locationId);
            $productRepo = \InventoryApp\Infrastructure\ServiceContainer::productRepo($tenantId);
            $product = $productRepo->findBySku(new \InventoryApp\Domain\Inventory\ValueObjects\SKU($skuStr));
            if (!$product) {
                return;
            }
            $locVo = new \InventoryApp\Domain\Inventory\ValueObjects\LocationId($locationId);
            if ($delta > 0) {
                $product->receiveStockAt($locVo, new \InventoryApp\Domain\Inventory\ValueObjects\Quantity($delta), $reference);
            } elseif ($delta < 0) {
                $product->dispatchStockAt($locVo, new \InventoryApp\Domain\Inventory\ValueObjects\Quantity(abs($delta)), $reference);
            }
            $productRepo->save($product);
            \InventoryApp\Infrastructure\ConformanceHelper::invalidateCache($tenantId, $skuStr);
            \InventoryApp\Infrastructure\ConformanceHelper::bumpVersion($tenantId, $skuStr, $locationId);
        } catch (\Throwable $e) {
            error_log('[SerializedItemController] adjustProductStock failed: ' . $e->getMessage());
        }
    }
}
