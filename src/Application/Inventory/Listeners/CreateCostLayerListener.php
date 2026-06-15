<?php

namespace InventoryApp\Application\Inventory\Listeners;

use InventoryApp\Domain\Inventory\Events\StockReceived;
use InventoryApp\Domain\Inventory\Events\OpeningBalancePosted;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\Entities\InventoryCostLayer;
use Illuminate\Database\Capsule\Manager as DB;
use Ramsey\Uuid\Uuid;

class CreateCostLayerListener
{
    private static array $priceCache = [];

    public function __construct(
        private readonly ?CostLayerRepositoryInterface $costLayerRepo = null,
        private readonly ?string $tenantId = null
    ) {}

    private function resolveTenantId(): string
    {
        return $this->tenantId ?? $_SERVER['auth.tenant_id'] ?? 'system';
    }

    private function getRepo(string $tenantId): CostLayerRepositoryInterface
    {
        return $this->costLayerRepo ?? \InventoryApp\Infrastructure\ServiceContainer::costLayerRepo($tenantId);
    }

    public function preloadPrices(array $skus): void
    {
        $uncached = array_diff($skus, array_keys(self::$priceCache));
        if (empty($uncached)) {
            return;
        }

        foreach (array_chunk($uncached, 500) as $chunk) {
            $variants = DB::table('catalog_variants')->whereIn('sku', $chunk)->get(['sku', 'price']);
            foreach ($variants as $variant) {
                self::$priceCache[$variant->sku] = $variant->price;
            }
        }

        foreach ($uncached as $sku) {
            if (!isset(self::$priceCache[$sku])) {
                self::$priceCache[$sku] = 10.00;
            }
        }
    }

    public function handleStockReceived(StockReceived $event): void
    {
        $tenantId = $this->resolveTenantId();
        $sku = $event->getSku()->getValue();
        
        // Lookup default catalog price to establish unit cost
        if (!isset(self::$priceCache[$sku])) {
            $this->preloadPrices([$sku]);
        }
        $price = self::$priceCache[$sku];
        $unitCostCents = (int)($price * 100);

        $layer = new InventoryCostLayer(
            Uuid::uuid4()->toString(),
            $sku,
            $tenantId,
            $event->quantity,
            $unitCostCents,
            $event->occurredOn(),
            $event->reference
        );

        $this->getRepo($tenantId)->save($layer);
    }

    public function handleOpeningBalancePosted(OpeningBalancePosted $event): void
    {
        $tenantId = $this->resolveTenantId();
        
        $layer = new InventoryCostLayer(
            Uuid::uuid4()->toString(),
            $event->variantId,
            $tenantId,
            $event->quantity,
            $event->unitCostCents,
            $event->asOfDate,
            'ONBOARDING-' . $event->onboardingId
        );

        $this->getRepo($tenantId)->save($layer);
    }
}
