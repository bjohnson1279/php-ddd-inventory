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
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[BarcodeController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function assign(RequestInterface $request, BarcodeRepositoryInterface $repo)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $variantId = $body['variant_id'] ?? $body['variantId'] ?? $body['sku'] ?? '';
            $value = $body['value'] ?? $body['barcodeValue'] ?? $body['barcode'] ?? '';
            $rawSymbology = $body['symbology'] ?? 'code_128';
            $rawSource = $body['source'] ?? 'internal';

            if ($variantId === '' || $value === '') {
                throw new \InvalidArgumentException('variant_id and value are required.');
            }

            $isPrimary = (bool)($body['is_primary'] ?? $body['isPrimary'] ?? $body['makePrimary'] ?? $request->query('is_primary') ?? false);

            $normSym = strtolower(str_replace('-', '_', trim((string)$rawSymbology)));
            if ($normSym === 'code128') {
                $normSym = 'code_128';
            } elseif ($normSym === 'ean13') {
                $normSym = 'ean_13';
            } elseif ($normSym === 'upca') {
                $normSym = 'upc_a';
            } elseif ($normSym === 'upce') {
                $normSym = 'upc_e';
            } elseif ($normSym === 'ean8') {
                $normSym = 'ean_8';
            } elseif ($normSym === 'itf14') {
                $normSym = 'itf_14';
            } elseif ($normSym === 'gs1128') {
                $normSym = 'gs1_128';
            }
            $symbology = BarcodeSymbology::tryFrom($normSym) ?? BarcodeSymbology::CODE_128;

            $normSource = strtolower(trim((string)$rawSource));
            if (in_array($normSource, ['manual', 'system', 'warehouse', 'admin'], true)) {
                $normSource = 'internal';
            }
            $source = BarcodeSource::tryFrom($normSource) ?? BarcodeSource::Internal;

            $barcode = new Barcode($symbology, (string)$value);

            $repo->registerAssignment((string)$variantId, $barcode, $source, $isPrimary);

            return new Response(['message' => 'Barcode assigned successfully'], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[BarcodeController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
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
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[BarcodeController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
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

            $dispatcher = $this->buildDispatcher($repo, $tenantId);
            $sku = $dispatcher->dispatch($validated['rawScan'], $validated['context'], $scanPayload);
            \InventoryApp\Infrastructure\ConformanceHelper::invalidateCache($tenantId, $sku);

            $this->logScanNotification($tenantId, $validated['rawScan'], $validated['context'], $scanPayload, $sku);

            return new Response([
                'message' => 'Scan processed.',
                'sku' => $sku,
                'context' => $validated['context'],
                'dispatched' => true
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[BarcodeController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function buildDispatcher(BarcodeRepositoryInterface $repo, string $tenantId): BarcodeScanDispatcher
    {
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

        return $dispatcher;
    }

    private function logScanNotification(string $tenantId, string $rawScan, string $context, array $scanPayload, string $sku): void
    {
        $notificationService = new \InventoryApp\Application\Notification\Services\NotificationService();
        $notificationService->createNotification(
            $tenantId,
            'Barcode Scanned',
            json_encode([
                'scanValue' => $rawScan,
                'symbology' => 'unknown',
                'context' => $context,
                'status' => 'success',
                'time' => date('Y-m-d H:i:s'),
                'payload' => json_encode($scanPayload),
                'sku' => $sku
            ]),
            'barcode_scanned'
        );
    }
}
