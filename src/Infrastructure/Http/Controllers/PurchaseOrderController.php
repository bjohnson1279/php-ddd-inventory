<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Application\Procurement\UseCases\CreatePurchaseOrder;
use InventoryApp\Application\Procurement\UseCases\ReceivePurchaseOrder;
use InventoryApp\Application\Shared\Decorators\AutoRetryUseCaseDecorator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Exception;

class PurchaseOrderController
{
    public function create(RequestInterface $request, PurchaseOrderRepositoryInterface $poRepo)
    {
        try {
            $body = $request->validate([
                'purchaseOrderNumber' => 'required|string',
                'vendorId'            => 'required|string',
                'tenantId'            => 'required|string',
                'locationId'          => 'required|string',
                'items'               => 'required|array'
            ]);

            $useCase = new AutoRetryUseCaseDecorator(new CreatePurchaseOrder($poRepo));
            $po = $useCase->execute($body);

            return new Response([
                'id'                  => $po->id,
                'purchaseOrderNumber' => $po->purchaseOrderNumber,
                'status'              => $po->getStatus()->value,
                'vendorId'            => $po->vendorId,
                'tenantId'            => $po->tenantId,
                'locationId'          => $po->locationId,
                'items'               => array_map(fn($i) => [
                    'id'               => $i->id,
                    'variantId'        => $i->variantId,
                    'quantity'         => $i->quantity,
                    'receivedQuantity' => $i->getReceivedQuantity(),
                    'unitCostCents'    => $i->unitCostCents
                ], $po->getItems())
            ], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[PurchaseOrderController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function get(RequestInterface $request, string $id, PurchaseOrderRepositoryInterface $poRepo)
    {
        try {
            $po = $poRepo->findById($id);
            if (!$po) {
                return new Response(['error' => 'Purchase order not found'], 404);
            }

            return new Response([
                'id'                  => $po->id,
                'purchaseOrderNumber' => $po->purchaseOrderNumber,
                'status'              => $po->getStatus()->value,
                'vendorId'            => $po->vendorId,
                'tenantId'            => $po->tenantId,
                'locationId'          => $po->locationId,
                'items'               => array_map(fn($i) => [
                    'id'               => $i->id,
                    'variantId'        => $i->variantId,
                    'quantity'         => $i->quantity,
                    'receivedQuantity' => $i->getReceivedQuantity(),
                    'unitCostCents'    => $i->unitCostCents
                ], $po->getItems())
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[PurchaseOrderController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function approve(RequestInterface $request, string $id, PurchaseOrderRepositoryInterface $poRepo)
    {
        try {
            $po = $poRepo->findById($id);
            if (!$po) {
                return new Response(['error' => 'Purchase order not found'], 404);
            }

            $po->approve();
            $poRepo->save($po);

            return new Response(['message' => 'Purchase order approved successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[PurchaseOrderController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function send(RequestInterface $request, string $id, PurchaseOrderRepositoryInterface $poRepo)
    {
        try {
            $po = $poRepo->findById($id);
            if (!$po) {
                return new Response(['error' => 'Purchase order not found'], 404);
            }

            $po->send();
            $poRepo->save($po);

            return new Response(['message' => 'Purchase order sent to vendor successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[PurchaseOrderController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function receive(
        RequestInterface $request,
        string $id,
        PurchaseOrderRepositoryInterface $poRepo,
        ProductRepositoryInterface $productRepo,
        CostLayerRepositoryInterface $costLayerRepo,
        EventDispatcherInterface $events
    ) {
        try {
            $body = $request->validate([
                'items' => 'required|array'
            ]);

            $baseUseCase = new ReceivePurchaseOrder($poRepo, $productRepo, $costLayerRepo, $events);
            $receiveStockFactory = new ReceiveStockFactory($productRepo, $events, null, $costLayerRepo);
            $baseUseCase = new ReceivePurchaseOrder($poRepo, $costLayerRepo, $receiveStockFactory);
            $useCase = new AutoRetryUseCaseDecorator($baseUseCase);
            $useCase->execute([
                'purchaseOrderId' => $id,
                'items'           => $body['items']
            ]);

            return new Response(['message' => 'Items received successfully'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[PurchaseOrderController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
