<?php

namespace InventoryApp\Application\Inventory\Factories;

use InventoryApp\Application\Inventory\UseCases\ReceiveStock;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Services\WMSCapacityService;
use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ReceiveStockFactory implements ReceiveStockFactoryInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventDispatcherInterface   $events,
        private readonly ?WMSCapacityService        $capacityService = null,
        private readonly ?CostLayerRepositoryInterface $costLayerRepository = null,
        private readonly ?LedgerRepositoryInterface $ledgerRepository = null
    ) {}

    public function create(): ReceiveStock
    {
        return new ReceiveStock(
            $this->productRepository,
            $this->events,
            $this->capacityService,
            $this->costLayerRepository,
            $this->ledgerRepository
        );
    }
}
