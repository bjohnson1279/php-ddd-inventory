<?php

namespace InventoryApp\Infrastructure;

use InventoryApp\Infrastructure\Persistence\Repositories\EloquentLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentUserRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentSerializedItemRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentBarcodeRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentJournalRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentStockOnboardingRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentCostLayerRepository;
use InventoryApp\Domain\Shared\Events\EventDispatcher;

use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\StockOnboardingRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentInventoryCountRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentCatalogProductRepository;
use InventoryApp\Domain\Uom\Repositories\ProductUomConfigurationRepositoryInterface;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductUomConfigurationRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentKitRepository;

class ServiceContainer
{
    // Tenant-scoped repos are NOT singletons — they carry a tenantId per request
    private static ?LedgerRepositoryInterface $ledger = null;
    private static ?UserRepositoryInterface $users = null;
    private static ?SerializedItemRepositoryInterface $serials = null;
    private static ?BarcodeRepositoryInterface $barcodes = null;
    private static ?JournalRepositoryInterface $journal = null;
    private static ?StockOnboardingRepositoryInterface $onboards = null;
    private static ?InventoryCountRepositoryInterface $inventoryCounts = null;
    private static ?ProductUomConfigurationRepositoryInterface $uomConfig = null;
    private static ?KitRepositoryInterface $kits = null;
    private static ?EventDispatcher $dispatcher = null;

    /**
     * Shared event dispatcher singleton.
     * Wire listeners here (or in a bootstrap file) before the first request.
     */
    public static function dispatcher(): EventDispatcher
    {
        return self::$dispatcher ??= new EventDispatcher();
    }

    public static function ledgerRepo(string $tenantId): LedgerRepositoryInterface
    {
        return new EloquentLedgerRepository($tenantId);
    }

    public static function userRepo(): UserRepositoryInterface
    {
        return self::$users ??= new EloquentUserRepository();
    }

    public static function serializedRepo(): SerializedItemRepositoryInterface
    {
        return self::$serials ??= new EloquentSerializedItemRepository();
    }

    public static function barcodeRepo(): BarcodeRepositoryInterface
    {
        return self::$barcodes ??= new EloquentBarcodeRepository();
    }

    public static function journalRepo(): JournalRepositoryInterface
    {
        return self::$journal ??= new EloquentJournalRepository();
    }

    public static function stockOnboardingRepo(): StockOnboardingRepositoryInterface
    {
        return self::$onboards ??= new EloquentStockOnboardingRepository();
    }

    /**
     * Returns a tenant-scoped product repository (fresh instance per call).
     */
    public static function productRepo(string $tenantId): ProductRepositoryInterface
    {
        return new EloquentProductRepository($tenantId);
    }

    /**
     * Returns a tenant-scoped cost layer repository (fresh instance per call).
     */
    public static function costLayerRepo(string $tenantId): CostLayerRepositoryInterface
    {
        return new EloquentCostLayerRepository($tenantId);
    }

    /**
     * Returns a tenant-scoped inventory count repository (fresh instance per call).
     */
    public static function inventoryCountRepo(string $tenantId): InventoryCountRepositoryInterface
    {
        return new EloquentInventoryCountRepository($tenantId);
    }

    public static function catalogProductRepo(): \InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface
    {
        return new EloquentCatalogProductRepository();
    }

    public static function uomConfigRepo(): ProductUomConfigurationRepositoryInterface
    {
        return self::$uomConfig ??= new EloquentProductUomConfigurationRepository();
    }

    public static function kitRepo(): KitRepositoryInterface
    {
        return self::$kits ??= new EloquentKitRepository();
    }
}

