<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSource;
use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;
use InventoryApp\Domain\Barcode\Services\BarcodeScanDispatcher;
use InventoryApp\Domain\Barcode\Services\ScanContext;
use InventoryApp\Domain\Barcode\Services\POSScanHandler;
use InventoryApp\Domain\Barcode\Services\ReceivingScanHandler;
use InventoryApp\Domain\Barcode\Services\CycleCountScanHandler;
use Exception;

class BarcodeController
{
    public function lookup(RequestInterface $request, BarcodeRepositoryInterface $repo)
    {
        try {
            $value = $request->query('value');
            if (empty($value)) {
                return new Response(['error' => 'Barcode value query parameter is required'], 400);
            }

            $variantId = $repo->findVariantByBarcodeValue($value);

            return new Response(['variant_id' => $variantId], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function assign(RequestInterface $request, BarcodeRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'variant_id' => 'required|string',
                'value'      => 'required|string',
                'symbology'  => 'required|string',
                'source'     => 'required|string',
            ]);

            $isPrimary = (bool)($request->query('is_primary') ?? ($request->validate([])['is_primary'] ?? false));
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            if (isset($body['is_primary'])) {
                $isPrimary = (bool)$body['is_primary'];
            }

            $symbology = BarcodeSymbology::tryFrom($validated['symbology']);
            if ($symbology === null) {
                throw new \InvalidArgumentException('Invalid barcode symbology.');
            }

            $source = BarcodeSource::tryFrom($validated['source']);
            if ($source === null) {
                throw new \InvalidArgumentException('Invalid barcode source.');
            }

            $barcode = new Barcode($symbology, $validated['value']);

            $repo->registerAssignment($validated['variant_id'], $barcode, $source, $isPrimary);

            return new Response(['message' => 'Barcode assigned successfully'], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function getVariantSet(RequestInterface $request, string $variantId, BarcodeRepositoryInterface $repo)
    {
        try {
            $set = $repo->findSetForVariant($variantId);
            $assignments = array_map(function ($a) {
                return [
                    'id'         => $a->id,
                    'variant_id' => $a->variantId,
                    'value'      => $a->barcode->value,
                    'symbology'  => $a->barcode->symbology->value,
                    'source'     => $a->source->value,
                    'is_primary' => $a->isPrimary,
                    'created_at' => $a->assignedAt->format(\DateTimeInterface::ATOM),
                ];
            }, $set->all());

            return new Response([
                'variant_id'  => $variantId,
                'assignments' => $assignments,
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function scan(RequestInterface $request, BarcodeRepositoryInterface $repo, string $tenantId)
    {
        try {
            $validated = $request->validate([
                'rawScan' => 'required|string',
                'context' => 'required|string',
            ]);

            $payload = json_decode(file_get_contents('php://input'), true) ?: [];
            $scanPayload = $payload['payload'] ?? [];

            $events = \InventoryApp\Infrastructure\ServiceContainer::dispatcher();
            $productRepo = \InventoryApp\Infrastructure\ServiceContainer::productRepo($tenantId);
            $countRepo = \InventoryApp\Infrastructure\ServiceContainer::inventoryCountRepo($tenantId);

            $dispatchStock = new \InventoryApp\Application\Inventory\UseCases\DispatchStock($productRepo, $events);
            $receiveStock = new \InventoryApp\Application\Inventory\UseCases\ReceiveStock($productRepo, $events);
            $recordCountItem = new \InventoryApp\Application\Inventory\UseCases\RecordCountItem($countRepo);

            $dispatcher = new BarcodeScanDispatcher($repo);
            $dispatcher->register(ScanContext::PointOfSale, new POSScanHandler($dispatchStock));
            $dispatcher->register(ScanContext::Receiving, new ReceivingScanHandler($receiveStock));
            $dispatcher->register(ScanContext::CycleCount, new CycleCountScanHandler($recordCountItem));

            $sku = $dispatcher->dispatch($validated['rawScan'], $validated['context'], $scanPayload);

            $notificationService = new \InventoryApp\Application\Notification\Services\NotificationService();
            $notificationService->createNotification(
                $tenantId,
                'Barcode Scanned',
                json_encode([
                    'scanValue' => $validated['rawScan'],
                    'symbology' => 'unknown',
                    'context' => $validated['context'],
                    'status' => 'success',
                    'time' => date('Y-m-d H:i:s'),
                    'payload' => json_encode($scanPayload),
                    'sku' => $sku
                ]),
                'barcode_scanned'
            );

            return new Response([
                'message' => 'Scan processed.',
                'sku' => $sku,
                'context' => $validated['context'],
                'dispatched' => true
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
