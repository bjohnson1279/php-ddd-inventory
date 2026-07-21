<?php

namespace InventoryApp\Domain\Barcode\Services;

use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Inventory\ValueObjects\Quantity;
use InventoryApp\Application\Inventory\UseCases\DispatchStock;
use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Application\Inventory\UseCases\RecordCountItem;
use Illuminate\Database\Capsule\Manager as Capsule;
use DomainException;
use InvalidArgumentException;

enum ScanContext: string
{
    case PointOfSale = 'pos';
    case Receiving = 'receiving';
    case CycleCount = 'cycle_count';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
}

interface IScanHandler
{
    public function handle(string $sku, string $rawScan, array $payload): void;
}

class POSScanHandler implements IScanHandler
{
    public function __construct(private readonly DispatchStock $dispatchStockUseCase) {}

    public function handle(string $sku, string $rawScan, array $payload): void
    {
        if (empty($payload['location_id'])) {
            throw new InvalidArgumentException('location_id is required for POS scan dispatch.');
        }
        $amount = (int)($payload['amount'] ?? 1);
        $this->dispatchStockUseCase->execute(
            new SKU($sku),
            new LocationId($payload['location_id']),
            new Quantity($amount)
        );
    }
}

class ReceivingScanHandler implements IScanHandler
{
    public function __construct(private readonly ReceiveStock $receiveStockUseCase) {}

    public function handle(string $sku, string $rawScan, array $payload): void
    {
        if (empty($payload['location_id'])) {
            throw new InvalidArgumentException('location_id is required for Receiving scan dispatch.');
        }
        $amount = (int)($payload['amount'] ?? 1);
        $this->receiveStockUseCase->execute(
            new SKU($sku),
            new LocationId($payload['location_id']),
            new Quantity($amount)
        );
    }
}

class CycleCountScanHandler implements IScanHandler
{
    public function __construct(private readonly RecordCountItem $recordCountItemUseCase) {}

    public function handle(string $sku, string $rawScan, array $payload): void
    {
        if (empty($payload['count_id'])) {
            throw new InvalidArgumentException('count_id is required for CycleCount scan dispatch.');
        }
        if (empty($payload['location_id'])) {
            throw new InvalidArgumentException('location_id is required for CycleCount scan dispatch.');
        }
        if (!isset($payload['actual_quantity'])) {
            throw new InvalidArgumentException('actual_quantity is required for CycleCount scan dispatch.');
        }
        $this->recordCountItemUseCase->execute(
            $payload['count_id'],
            $sku,
            $payload['location_id'],
            (int)$payload['actual_quantity']
        );
    }
}

class BarcodeScanDispatcher
{
    private array $handlers = [];

    public function __construct(private readonly BarcodeRepositoryInterface $repo) {}

    public function register(ScanContext $context, IScanHandler $handler): void
    {
        $this->handlers[$context->value] = $handler;
    }

    public function dispatch(string $rawScan, string $contextStr, array $payload = []): string
    {
        $context = ScanContext::tryFrom($contextStr);
        if ($context === null) {
            throw new InvalidArgumentException("Invalid scan context: {$contextStr}");
        }

        $rawScanClean = strtoupper(trim($rawScan));
        $variantId = $this->repo->findVariantByBarcodeValue($rawScanClean);

        if ($variantId === null) {
            throw new DomainException("No variant found for barcode: {$rawScan}");
        }

        // Resolve variant_id UUID to SKU string
        $variantRow = Capsule::connection()->table('catalog_variants')->where('id', $variantId)->first();
        if ($variantRow === null) {
            // Fallback if running in-memory or setup without catalog_variants table populated
            $sku = $variantId;
        } else {
            $sku = $variantRow->sku;
        }

        $handler = $this->handlers[$context->value] ?? null;
        if ($handler !== null) {
            $handler->handle($sku, $rawScan, $payload);
        }

        return $sku;
    }
}
