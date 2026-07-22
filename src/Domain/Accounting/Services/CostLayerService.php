<?php

namespace InventoryApp\Domain\Accounting\Services;

use InventoryApp\Domain\Accounting\Repositories\CostLayerRepositoryInterface;
use InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown;
use InventoryApp\Domain\Accounting\Enums\CostingMethod;
use InventoryApp\Domain\Accounting\Strategies\CostingStrategyRegistry;
use DomainException;

class CostLayerService
{
    public function __construct(
        private readonly CostLayerRepositoryInterface $layers,
    ) {}

    public function calculateCost(string $variantId, int $quantity, CostingMethod $method = CostingMethod::FIFO): CostBreakdown
    {
        if ($method === CostingMethod::SpecificIdentification) {
            throw new DomainException("SpecificIdentification requires serial numbers. Use a dedicated path.");
        }
        $activeLayers = $this->layers->getActiveLayers($variantId);
        $strategy = CostingStrategyRegistry::get($method);
        return $strategy->calculateCost($activeLayers, $quantity, $variantId);
    }

    public function consumeLayers(string $variantId, int $quantity, CostingMethod $method = CostingMethod::FIFO): CostBreakdown
    {
        if ($method === CostingMethod::SpecificIdentification) {
            throw new DomainException("SpecificIdentification requires serial numbers. Use a dedicated path.");
        }
        $activeLayers = $this->layers->getActiveLayers($variantId);
        $strategy = CostingStrategyRegistry::get($method);
        [$breakdown, $affectedLayers] = $strategy->consumeLayers($activeLayers, $quantity, $variantId);

        if (!empty($affectedLayers)) {
            $this->layers->saveBatch($affectedLayers);
        }

        return $breakdown;
    }

    // Backwards compatibility helpers
    public function consumeFifoLayers(string $variantId, int $quantity): CostBreakdown
    {
        return $this->consumeLayers($variantId, $quantity, CostingMethod::FIFO);
    }

    public function consumeLifoLayers(string $variantId, int $quantity): CostBreakdown
    {
        return $this->consumeLayers($variantId, $quantity, CostingMethod::LIFO);
    }

    public function consumeSpecificLayers(string $variantId, array $serialNumbers): CostBreakdown
    {
        $totalCost = 0;
        $affectedLayers = [];

        $layers = $this->layers->findBySerials($variantId, $serialNumbers);
        $layersBySerial = [];
        foreach ($layers as $layer) {
            $layersBySerial[$layer->serialNumber] = $layer;
        }

        foreach ($serialNumbers as $sn) {
            $layer = $layersBySerial[$sn] ?? null;
            if (!$layer) {
                throw new DomainException("No cost layer found for serial number {$sn}");
            }
            if ($layer->remainingQuantity() < 1) {
                throw new DomainException("Cost layer for serial number {$sn} has already been consumed");
            }

            $layer->consume(1);
            $totalCost += $layer->unitCostCents;
            $affectedLayers[$layer->id] = $layer;
        }

        $this->layers->saveBatch(array_values($affectedLayers));

        return new CostBreakdown(count($serialNumbers), $totalCost);
    }

    public function calculateWeightedAverageCost(string $variantId, int $quantity): CostBreakdown
    {
        return $this->calculateCost($variantId, $quantity, CostingMethod::WeightedAverageCost);
    }
}
