<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\TransferStock;
use InventoryApp\Application\Inventory\UseCases\AllocateStock;
use InventoryApp\Application\Inventory\UseCases\ReleaseAllocation;
use InventoryApp\Application\Inventory\UseCases\FulfillAllocation;
use InventoryApp\Application\Inventory\UseCases\CreateInTransit;
use InventoryApp\Application\Inventory\UseCases\ReceiveInTransit;
use InventoryApp\Application\Inventory\Queries\StockQueryServiceInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Application\Shared\Decorators\AutoRetryUseCaseDecorator;
use Exception;

class InventoryController
{
    public function receive(RequestInterface $request, ReceiveStock $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku' => 'required|string',
                'quantity' => 'required|integer|min:1',
                'location_id' => 'required|string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id']);
            $quantity = new Quantity($validated['quantity']);

            $lotNumber = $validated['lot_number'] ?? $validated['lotNumber'] ?? null;
            $expirationDateStr = $validated['expiration_date'] ?? $validated['expirationDate'] ?? null;
            $expirationDate = $expirationDateStr ? new \DateTimeImmutable($expirationDateStr) : null;
            $unitCostCents = isset($validated['unit_cost_cents']) ? (int)$validated['unit_cost_cents'] : (isset($validated['unitCostCents']) ? (int)$validated['unitCostCents'] : null);
            $tenantId = function_exists('tenantId') ? tenantId() : ($_SERVER['auth.tenant_id'] ?? 'system');
            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            \InventoryApp\Infrastructure\ConformanceHelper::ensureLocationExists($validated['location_id']);

            $rawBody = json_decode(file_get_contents('php://input'), true) ?: [];
            $expectedVersion = null;
            if (array_key_exists('expectedVersion', $rawBody) && $rawBody['expectedVersion'] !== null) {
                $expectedVersion = (int)$rawBody['expectedVersion'];
            } elseif (array_key_exists('expected_version', $rawBody) && $rawBody['expected_version'] !== null) {
                $expectedVersion = (int)$rawBody['expected_version'];
            }
            if (!\InventoryApp\Infrastructure\ConformanceHelper::checkVersion(
                $tenantId,
                $validated['sku'],
                $validated['location_id'],
                $expectedVersion
            )) {
                return new Response(['error' => 'Optimistic concurrency version conflict'], 409);
            }

            $useCase->execute(
                $sku,
                $locationId,
                $quantity,
                $validated['reference'] ?? null,
                $lotNumber,
                $expirationDate,
                $unitCostCents,
                $tenantId
            );

            $newVersion = \InventoryApp\Infrastructure\ConformanceHelper::bumpVersion(
                $tenantId,
                $validated['sku'],
                $validated['location_id']
            );

            \InventoryApp\Infrastructure\ConformanceHelper::onStockMutation(
                $tenantId,
                $actorId,
                'STOCK_RECEIVED',
                [
                    'sku' => $validated['sku'],
                    'locationId' => $validated['location_id'],
                    'quantityDelta' => (int)$validated['quantity'],
                    'lotNumber' => $lotNumber,
                    'unitCostCents' => $unitCostCents,
                ]
            );

            return new Response(['message' => 'Stock received successfully', 'version' => $newVersion], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function dispatch(RequestInterface $request, DispatchStock $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku'         => 'required|string',
                'quantity'    => 'required|integer|min:1',
                'location_id' => 'required|string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id']);
            $quantity = new Quantity($validated['quantity']);

            $lotNumber = $validated['lot_number'] ?? $validated['lotNumber'] ?? null;
            $tenantId = function_exists('tenantId') ? tenantId() : ($_SERVER['auth.tenant_id'] ?? 'system');
            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            if (\InventoryApp\Infrastructure\ConformanceHelper::isLotBlocked($tenantId, $lotNumber)) {
                return new Response(['error' => "Lot {$lotNumber} is quarantined or recalled and cannot be dispatched."], 400);
            }

            $rawBody = json_decode(file_get_contents('php://input'), true) ?: [];
            $expectedVersion = null;
            if (array_key_exists('expectedVersion', $rawBody) && $rawBody['expectedVersion'] !== null) {
                $expectedVersion = (int)$rawBody['expectedVersion'];
            } elseif (array_key_exists('expected_version', $rawBody) && $rawBody['expected_version'] !== null) {
                $expectedVersion = (int)$rawBody['expected_version'];
            }
            if (!\InventoryApp\Infrastructure\ConformanceHelper::checkVersion(
                $tenantId,
                $validated['sku'],
                $validated['location_id'],
                $expectedVersion
            )) {
                return new Response(['error' => 'Optimistic concurrency version conflict'], 409);
            }

            $useCase->execute(
                $sku,
                $locationId,
                $quantity,
                $validated['reference'] ?? null,
                $lotNumber,
                $tenantId
            );

            $newVersion = \InventoryApp\Infrastructure\ConformanceHelper::bumpVersion(
                $tenantId,
                $validated['sku'],
                $validated['location_id']
            );

            \InventoryApp\Infrastructure\ConformanceHelper::onStockMutation(
                $tenantId,
                $actorId,
                'STOCK_DISPATCHED',
                [
                    'sku' => $validated['sku'],
                    'locationId' => $validated['location_id'],
                    'quantityDelta' => -(int)$validated['quantity'],
                    'lotNumber' => $lotNumber,
                ]
            );

            return new Response(['message' => 'Stock dispatched successfully', 'version' => $newVersion], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function transfer(RequestInterface $request, TransferStock $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku'             => 'required|string',
                'from_location'   => 'required|string',
                'to_location'     => 'required|string',
                'quantity'        => 'required|integer|min:1',
            ]);

            $sku = new SKU($validated['sku']);
            $fromLocation = new LocationId($validated['from_location']);
            $toLocation = new LocationId($validated['to_location']);
            $quantity = new Quantity($validated['quantity']);
            $tenantId = function_exists('tenantId') ? tenantId() : ($_SERVER['auth.tenant_id'] ?? 'system');

            \InventoryApp\Infrastructure\ConformanceHelper::ensureLocationExists($validated['from_location']);
            \InventoryApp\Infrastructure\ConformanceHelper::ensureLocationExists($validated['to_location']);

            $useCase->execute(
                $sku,
                $fromLocation,
                $toLocation,
                $quantity
            );

            \InventoryApp\Infrastructure\ConformanceHelper::invalidateCache($tenantId, $validated['sku']);
            \InventoryApp\Infrastructure\ConformanceHelper::bumpVersion($tenantId, $validated['sku'], $validated['from_location']);
            \InventoryApp\Infrastructure\ConformanceHelper::bumpVersion($tenantId, $validated['sku'], $validated['to_location']);

            return new Response(['message' => 'Stock transferred successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function stockLevel(RequestInterface $request, string $sku, StockQueryServiceInterface $queryService)
    {
        try {
            $locationId = $request->query('location_id');
            $stockLevelDto = $queryService->getStockLevel($sku, $locationId);
            $tenantId = function_exists('tenantId') ? tenantId() : ($_SERVER['auth.tenant_id'] ?? 'system');
            $locKey = $locationId ?? $stockLevelDto->locationId ?? 'default';
            \InventoryApp\Infrastructure\ConformanceHelper::recordStockRead($tenantId, $sku, $locKey);
            $version = \InventoryApp\Infrastructure\ConformanceHelper::getVersion($tenantId, $sku, $locKey);

            return new Response([
                'sku' => $stockLevelDto->sku,
                'location_id' => $stockLevelDto->locationId,
                'stock' => $stockLevelDto->stockQuantity,
                'quantity' => $stockLevelDto->stockQuantity,
                'allocated' => $stockLevelDto->allocatedQuantity,
                'inTransit' => $stockLevelDto->inTransitQuantity,
                'available' => $stockLevelDto->availableQuantity,
                'version' => $version,
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 404);
        }
    }

    public function allocate(RequestInterface $request, AllocateStock $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku'         => 'required|string',
                'amount'      => 'required|integer',
                'location_id' => 'string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id'] ?? 'default');
            $quantity = new Quantity($validated['amount']);

            $useCase->execute($sku, $quantity, $locationId);

            return new Response(['message' => 'Stock allocated successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function releaseAllocation(RequestInterface $request, ReleaseAllocation $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku'         => 'required|string',
                'amount'      => 'required|integer',
                'location_id' => 'string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id'] ?? 'default');
            $quantity = new Quantity($validated['amount']);

            $useCase->execute($sku, $quantity, $locationId);

            return new Response(['message' => 'Allocation released successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function fulfillAllocation(RequestInterface $request, FulfillAllocation $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku'         => 'required|string',
                'amount'      => 'required|integer',
                'location_id' => 'string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id'] ?? 'default');
            $quantity = new Quantity($validated['amount']);

            $useCase->execute($sku, $quantity, $locationId);

            $tenantId = function_exists('tenantId') ? tenantId() : ($_SERVER['auth.tenant_id'] ?? 'system');
            \InventoryApp\Infrastructure\ConformanceHelper::invalidateCache($tenantId, $validated['sku']);
            \InventoryApp\Infrastructure\ConformanceHelper::bumpVersion($tenantId, $validated['sku'], $validated['location_id'] ?? 'default');

            return new Response(['message' => 'Allocation fulfilled successfully'], 200);
        } catch (Exception $e) {
            $msg = strtolower($e->getMessage());
            $isClientError = ($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)
                || str_contains($msg, 'insufficient') || str_contains($msg, 'allocated') || str_contains($msg, 'cannot') || str_contains($msg, 'exceed') || str_contains($msg, 'not found');
            if (!$isClientError) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function createInTransit(RequestInterface $request, CreateInTransit $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku'         => 'required|string',
                'amount'      => 'required|integer',
                'location_id' => 'string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id'] ?? 'default');
            $quantity = new Quantity($validated['amount']);

            $useCase->execute($sku, $quantity, $locationId);

            return new Response(['message' => 'In-transit stock created successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function receiveInTransit(RequestInterface $request, ReceiveInTransit $useCase)
    {
        try {
            $useCase = new AutoRetryUseCaseDecorator($useCase);
            $validated = $request->validate([
                'sku'         => 'required|string',
                'amount'      => 'required|integer',
                'location_id' => 'string'
            ]);

            $sku = new SKU($validated['sku']);
            $locationId = new LocationId($validated['location_id'] ?? 'default');
            $quantity = new Quantity($validated['amount']);

            $useCase->execute($sku, $quantity, $locationId);

            return new Response(['message' => 'In-transit stock received successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function suggestFefoPick(RequestInterface $request, \InventoryApp\Domain\Inventory\Services\FEFOPickingSuggester $suggester)
    {
        try {
            $sku = $request->query('sku');
            $quantity = (int)$request->query('quantity', 0);

            if (empty($sku)) {
                throw new Exception("SKU is required.");
            }
            if ($quantity <= 0) {
                throw new Exception("Quantity must be greater than 0.");
            }

            $suggestions = $suggester->suggestFefoPicking($sku, $quantity);

            return new Response($suggestions, 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function traceRecall(RequestInterface $request, string $lotNumber, \InventoryApp\Domain\Inventory\Services\ProductRecallService $recallService)
    {
        try {
            $dispatches = $recallService->traceProductRecall($lotNumber);

            return new Response($dispatches, 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[InventoryController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }
}
