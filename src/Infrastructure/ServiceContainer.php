<?php

namespace InventoryApp\Infrastructure;

use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemorySerializedItemRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryBarcodeRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryJournalRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryStockOnboardingRepository;

use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\StockOnboardingRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentInventoryCountRepository;

class ServiceContainer
{
    private static ?LedgerRepositoryInterface $ledger = null;
    private static ?SerializedItemRepositoryInterface $serials = null;
    private static ?BarcodeRepositoryInterface $barcodes = null;
    private static ?JournalRepositoryInterface $journal = null;
    private static ?StockOnboardingRepositoryInterface $onboards = null;
    private static ?ProductRepositoryInterface $products = null;
    private static ?InventoryCountRepositoryInterface $inventoryCounts = null;

    public static function ledgerRepo(): LedgerRepositoryInterface
    {
        return self::$ledger ??= new InMemoryLedgerRepository();
    }

    public static function serializedRepo(): SerializedItemRepositoryInterface
    {
        return self::$serials ??= new InMemorySerializedItemRepository();
    }

    public static function barcodeRepo(): BarcodeRepositoryInterface
    {
        return self::$barcodes ??= new InMemoryBarcodeRepository();
    }

    public static function journalRepo(): JournalRepositoryInterface
    {
        return self::$journal ??= new InMemoryJournalRepository();
    }

    public static function stockOnboardingRepo(): StockOnboardingRepositoryInterface
    {
        return self::$onboards ??= new InMemoryStockOnboardingRepository();
    }

    public static function productRepo(): ProductRepositoryInterface
    {
        return self::$products ??= new EloquentProductRepository();
    }

    public static function inventoryCountRepo(): InventoryCountRepositoryInterface
    {
        return self::$inventoryCounts ??= new EloquentInventoryCountRepository();
    }

    public static function catalogProductRepo(): \InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface
    {
        return new EloquentCatalogProductRepository();
    }
}

