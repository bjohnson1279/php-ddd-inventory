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
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentInventoryCountRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentCatalogProductRepository;
use InventoryApp\Domain\Uom\Repositories\ProductUomConfigurationRepositoryInterface;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentProductUomConfigurationRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentKitRepository;
use InventoryApp\Domain\Inventory\Repositories\WarehouseLocationRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentWarehouseLocationRepository;
use InventoryApp\Domain\Inventory\Repositories\DemandForecastRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentDemandForecastRepository;
use InventoryApp\Domain\Compliance\Repositories\ComplianceLedgerRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentComplianceLedgerRepository;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentOutboxRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentShipmentRepository;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Infrastructure\Shipping\MockCarrierService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Infrastructure\Persistence\TenantRegistry;
use InventoryApp\Infrastructure\Persistence\TenantConnectionPool;
use InventoryApp\Infrastructure\Persistence\TenantProvisioner;

class ServiceContainer
{
    private static ?Container $container = null;
    private static ?EventDispatcher $dispatcher = null;

    public static function getInstance(): Container
    {
        if (self::$container === null) {
            self::$container = new Container();
            self::bindInterfaces(self::$container);
        }

        return self::$container;
    }

    private static function bindInterfaces(Container $container): void
    {
        // Tenant database routing singletons (Roadmap 6.1)
        $container->singleton(TenantRegistry::class, function ($c) {
            return new TenantRegistry($c->make(Capsule::class));
        });

        $container->singleton(TenantConnectionPool::class, function ($c) {
            return new TenantConnectionPool($c->make(Capsule::class), $c->make(TenantRegistry::class));
        });

        $container->singleton(TenantProvisioner::class, function ($c) {
            return new TenantProvisioner($c->make(Capsule::class), $c->make(TenantRegistry::class));
        });

        // Singleton mappings
        $container->singleton(UserRepositoryInterface::class, EloquentUserRepository::class);
        $container->singleton(SerializedItemRepositoryInterface::class, EloquentSerializedItemRepository::class);
        $container->singleton(BarcodeRepositoryInterface::class, EloquentBarcodeRepository::class);
        $container->singleton(JournalRepositoryInterface::class, EloquentJournalRepository::class);
        $container->singleton(StockOnboardingRepositoryInterface::class, EloquentStockOnboardingRepository::class);
        $container->singleton(\InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface::class, EloquentCatalogProductRepository::class);
        $container->singleton(ProductUomConfigurationRepositoryInterface::class, EloquentProductUomConfigurationRepository::class);
        $container->singleton(KitRepositoryInterface::class, EloquentKitRepository::class);
        $container->singleton(WarehouseLocationRepositoryInterface::class, EloquentWarehouseLocationRepository::class);
        $container->singleton(DemandForecastRepositoryInterface::class, EloquentDemandForecastRepository::class);
        $container->singleton(ComplianceLedgerRepositoryInterface::class, EloquentComplianceLedgerRepository::class);
        $container->singleton(ShipmentRepositoryInterface::class, EloquentShipmentRepository::class);
        $container->singleton(OutboxRepositoryInterface::class, EloquentOutboxRepository::class);
        $container->singleton(CarrierServiceInterface::class, MockCarrierService::class);
        $container->singleton(\InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface::class, \InventoryApp\Infrastructure\Persistence\Repositories\EloquentRMARepository::class);
        $container->singleton(\InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface::class, \InventoryApp\Infrastructure\Persistence\Repositories\EloquentQuarantineRepository::class);
        $container->singleton(\InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface::class, \InventoryApp\Infrastructure\Persistence\Repositories\EloquentPurchaseOrderRepository::class);
        $container->singleton(\InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface::class, \InventoryApp\Infrastructure\Persistence\Repositories\EloquentReorderPolicyRepository::class);
        $container->singleton(\InventoryApp\Domain\Procurement\Services\ReorderPolicyService::class, function ($c) {
            return new \InventoryApp\Domain\Procurement\Services\ReorderPolicyService(
                $c->make(\InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface::class),
                $c->make(\InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface::class),
                $c->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
            );
        });
        $container->singleton(EventDispatcher::class, function () {
            return self::dispatcher();
        });

        $container->singleton(\Psr\EventDispatcher\EventDispatcherInterface::class, function ($c) {
            return $c->make(EventDispatcher::class);
        });

        // Tenant-scoped factory mappings (requires tenantId)
        $container->bind(LedgerRepositoryInterface::class, function ($app, $parameters) {
            $tenantId = $parameters['tenantId'] ?? (function_exists('tenantId') ? tenantId() : 'system');
            self::switchTenantConnection($app, $tenantId);
            return new EloquentLedgerRepository($tenantId);
        });

        $container->bind(ProductRepositoryInterface::class, function ($app, $parameters) {
            $tenantId = $parameters['tenantId'] ?? (function_exists('tenantId') ? tenantId() : 'system');
            self::switchTenantConnection($app, $tenantId);
            return new EloquentProductRepository($tenantId);
        });

        $container->bind(CostLayerRepositoryInterface::class, function ($app, $parameters) {
            $tenantId = $parameters['tenantId'] ?? (function_exists('tenantId') ? tenantId() : 'system');
            self::switchTenantConnection($app, $tenantId);
            return new EloquentCostLayerRepository($tenantId);
        });

        $container->bind(InventoryCountRepositoryInterface::class, function ($app, $parameters) {
            $tenantId = $parameters['tenantId'] ?? (function_exists('tenantId') ? tenantId() : 'system');
            self::switchTenantConnection($app, $tenantId);
            return new EloquentInventoryCountRepository($tenantId);
        });

        $container->bind(\InventoryApp\Application\Inventory\Queries\StockQueryServiceInterface::class, function ($app, $parameters) {
            $tenantId = $parameters['tenantId'] ?? (function_exists('tenantId') ? tenantId() : 'system');
            self::switchTenantConnection($app, $tenantId);
            return new \InventoryApp\Infrastructure\Persistence\Queries\EloquentStockQueryService($tenantId);
        });
    }

    /**
     * Shared event dispatcher singleton.
     * Wire listeners here (or in a bootstrap file) before the first request.
     */
    public static function dispatcher(): EventDispatcher
    {
        return self::$dispatcher ??= new EventDispatcher();
    }

    public static function resetDispatcher(): void
    {
        self::$dispatcher = null;
    }

    public static function ledgerRepo(string $tenantId): LedgerRepositoryInterface
    {
        return self::getInstance()->make(LedgerRepositoryInterface::class, ['tenantId' => $tenantId]);
    }

    public static function userRepo(): UserRepositoryInterface
    {
        return self::getInstance()->make(UserRepositoryInterface::class);
    }

    public static function serializedRepo(): SerializedItemRepositoryInterface
    {
        return self::getInstance()->make(SerializedItemRepositoryInterface::class);
    }

    public static function barcodeRepo(): BarcodeRepositoryInterface
    {
        return self::getInstance()->make(BarcodeRepositoryInterface::class);
    }

    public static function journalRepo(): JournalRepositoryInterface
    {
        return self::getInstance()->make(JournalRepositoryInterface::class);
    }

    public static function stockOnboardingRepo(): StockOnboardingRepositoryInterface
    {
        return self::getInstance()->make(StockOnboardingRepositoryInterface::class);
    }

    /**
     * Returns a tenant-scoped product repository (fresh instance per call).
     */
    public static function productRepo(string $tenantId): ProductRepositoryInterface
    {
        return self::getInstance()->make(ProductRepositoryInterface::class, ['tenantId' => $tenantId]);
    }

    /**
     * Returns a tenant-scoped cost layer repository (fresh instance per call).
     */
    public static function costLayerRepo(string $tenantId): CostLayerRepositoryInterface
    {
        return self::getInstance()->make(CostLayerRepositoryInterface::class, ['tenantId' => $tenantId]);
    }

    /**
     * Returns a tenant-scoped inventory count repository (fresh instance per call).
     */
    public static function inventoryCountRepo(string $tenantId): InventoryCountRepositoryInterface
    {
        return self::getInstance()->make(InventoryCountRepositoryInterface::class, ['tenantId' => $tenantId]);
    }

    public static function catalogProductRepo(): \InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface
    {
        return self::getInstance()->make(\InventoryApp\Domain\Catalog\Repositories\CatalogProductRepositoryInterface::class);
    }

    public static function uomConfigRepo(): ProductUomConfigurationRepositoryInterface
    {
        return self::getInstance()->make(ProductUomConfigurationRepositoryInterface::class);
    }

    public static function kitRepo(): KitRepositoryInterface
    {
        return self::getInstance()->make(KitRepositoryInterface::class);
    }

    private static function switchTenantConnection(Container $container, string $tenantId): void
    {
        if (getenv('MULTI_TENANT_MODE') === 'database' && $tenantId !== 'system') {
            $pool = $container->make(TenantConnectionPool::class);
            $pool->getConnection($tenantId);
            Capsule::getDatabaseManager()->setDefaultConnection('tenant_' . $tenantId);
        }
    }

    public static function rmaRepo(): \InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface
    {
        return self::getInstance()->make(\InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface::class);
    }

    public static function quarantineRepo(): \InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface
    {
        return self::getInstance()->make(\InventoryApp\Domain\Returns\Repositories\QuarantineRepositoryInterface::class);
    }

    public static function warehouseLocationRepo(): WarehouseLocationRepositoryInterface
    {
        return self::getInstance()->make(WarehouseLocationRepositoryInterface::class);
    }

    public static function purchaseOrderRepo(): \InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface
    {
        return self::getInstance()->make(\InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface::class);
    }

    public static function reorderPolicyRepo(): \InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface
    {
        return self::getInstance()->make(\InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface::class);
    }

    public static function demandForecastRepo(): DemandForecastRepositoryInterface
    {
        return self::getInstance()->make(DemandForecastRepositoryInterface::class);
    }

    public static function complianceLedgerRepo(): ComplianceLedgerRepositoryInterface
    {
        return self::getInstance()->make(ComplianceLedgerRepositoryInterface::class);
    }

    public static function reorderPolicyService(): \InventoryApp\Domain\Procurement\Services\ReorderPolicyService
    {
        return self::getInstance()->make(\InventoryApp\Domain\Procurement\Services\ReorderPolicyService::class);
    }

    public static function shipmentRepo(): ShipmentRepositoryInterface
    {
        return self::getInstance()->make(ShipmentRepositoryInterface::class);
    }

    public static function outboxRepo(): OutboxRepositoryInterface
    {
        return self::getInstance()->make(OutboxRepositoryInterface::class);
    }

    public static function carrierService(): CarrierServiceInterface
    {
        return self::getInstance()->make(CarrierServiceInterface::class);
    }
}
