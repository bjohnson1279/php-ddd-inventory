<?php

namespace InventoryApp\Infrastructure;

use InventoryApp\Infrastructure\Persistence\Repositories\EloquentLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentUserRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemorySerializedItemRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryBarcodeRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryJournalRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryStockOnboardingRepository;
use InventoryApp\Domain\Shared\Events\EventDispatcher;

use InventoryApp\Domain\Identity\Repositories\UserRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\StockOnboardingRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentInventoryCountRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentCatalogProductRepository;

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

    /**
     * Returns a tenant-scoped product repository (fresh instance per call).
     */
    public static function productRepo(string $tenantId): ProductRepositoryInterface
    {
        return new EloquentProductRepository($tenantId);
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
}

