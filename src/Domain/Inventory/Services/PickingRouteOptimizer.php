<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Repositories\WarehouseLocationRepositoryInterface;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;

class PickingRouteOptimizer
{
    private WarehouseLocationRepositoryInterface $locationRepo;

    public function __construct(WarehouseLocationRepositoryInterface $locationRepo)
    {
        $this->locationRepo = $locationRepo;
    }

    public function optimizeRoute(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $routeItems = [];
        foreach ($items as $item) {
            $locId = new LocationId($item['locationId']);
            $loc = $this->locationRepo->findById($locId);
            if (!$loc) {
                throw new Exception("Warehouse location with ID {$item['locationId']} not found.");
            }

            $routeItems[] = [
                'sku'          => $item['sku'],
                'locationId'   => $item['locationId'],
                'quantity'     => $item['quantity'],
                'warehouseId'  => $loc->getWarehouseId(),
                'zone'         => $loc->getZone(),
                'aisle'        => $loc->getAisle(),
                'rack'         => $loc->getRack(),
                'shelf'        => $loc->getShelf(),
                'bin'          => $loc->getBin()
            ];
        }

        // Group by warehouseId
        $groups = [];
        foreach ($routeItems as $item) {
            $whId = $item['warehouseId'];
            if (!isset($groups[$whId])) {
                $groups[$whId] = [];
            }
            $groups[$whId][] = $item;
        }

        $result = [];

        // Sort items within each warehouse group using S-Shape Routing
        foreach ($groups as $warehouseId => $warehouseItems) {
            usort($warehouseItems, function($a, $b) {
                // 1. Sort by aisle index
                $indexA = $this->getAisleIndex($a['aisle']);
                $indexB = $this->getAisleIndex($b['aisle']);
                if ($indexA !== $indexB) {
                    return $indexA - $indexB;
                }

                // 2. Since they are in the same aisle, apply S-Shape serpentine direction
                $isOddAisle = ($indexA % 2 !== 0);

                // Compare rack
                $rackComp = strnatcmp($a['rack'], $b['rack']);
                if ($rackComp !== 0) {
                    return $isOddAisle ? $rackComp : -$rackComp;
                }

                // Compare shelf
                $shelfComp = strnatcmp($a['shelf'], $b['shelf']);
                if ($shelfComp !== 0) {
                    return $isOddAisle ? $shelfComp : -$shelfComp;
                }

                // Compare bin
                $binComp = strnatcmp($a['bin'], $b['bin']);
                return $isOddAisle ? $binComp : -$binComp;
            });

            $result[] = [
                'warehouseId' => $warehouseId,
                'items' => $warehouseItems
            ];
        }

        return $result;
    }

  private function getAisleIndex(string $aisle): int
  {
      $num = (int) preg_replace('/\D/', '', $aisle);
      if ($num > 0) {
          return $num;
      }

      $clean = preg_replace('/[^A-Z]/', '', strtoupper($aisle));
      $code = 0;
      for ($i = 0; $i < strlen($clean); $i++) {
          $code = $code * 26 + (ord($clean[$i]) - 64);
      }
      return $code ?: 1;
  }
}
