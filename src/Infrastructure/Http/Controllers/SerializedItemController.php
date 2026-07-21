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
            $validated = $request->validate([
                'return_id' => 'required|string',
            ]);

            $item = $repo->findById($id);
            if (!$item) {
                return new Response(['error' => 'Serial item not found'], 404);
            }

            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $service->acceptReturn($item->serialNumber, $item->tenantId, $validated['return_id'], $actorId);

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
            $validated = $request->validate([
                'return_id'                 => 'required|string',
                'restocked_unit_cost_cents' => 'required|integer',
            ]);

            $item = $repo->findById($id);
            if (!$item) {
                return new Response(['error' => 'Serial item not found'], 404);
            }

            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            $service->restock($item->serialNumber, $item->tenantId, $validated['return_id'], $validated['restocked_unit_cost_cents'], $actorId);

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
            'serial_number' => $item->serialNumber->value,
            'tenant_id'     => $item->tenantId,
            'location_id'   => $item->locationId(),
            'status'        => $item->status()->value,
            'history'       => $history,
        ];
    }
}
