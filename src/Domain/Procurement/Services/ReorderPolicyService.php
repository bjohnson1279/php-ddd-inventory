<?php

namespace InventoryApp\Domain\Procurement\Services;

use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Events\ReorderPointReachedEvent;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Entities\PurchaseOrderItem;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Psr\EventDispatcher\EventDispatcherInterface;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class ReorderPolicyService
{
    public function __construct(
        private readonly ReorderPolicyRepositoryInterface $reorderPolicyRepository,
        private readonly PurchaseOrderRepositoryInterface $poRepository,
        private readonly EventDispatcherInterface $events
    ) {}

    public function evaluatePolicies(
        string $tenantId,
        ReorderPointForecaster $forecaster,
        \InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface $productRepo,
        \InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface $ledgerRepo,
        int $windowDays = 30
    ): array {
        $policies = $this->reorderPolicyRepository->findAll();
        $results = [];

        // Bolt optimization: Extract database queries out of the loop
        $allPos = $this->poRepository->findAll();

        $skus = array_unique(array_map(fn($p) => $p->sku->getValue(), $policies));
        $skuObjects = array_map(fn($s) => new \InventoryApp\Domain\Inventory\ValueObjects\SKU($s), $skus);
        $products = [];

        // This leverages the existing findBySkus method in ProductRepositoryInterface and EloquentProductRepository
        foreach ($productRepo->findBySkus($skuObjects) as $p) {
            $products[$p->getSku()->getValue()] = $p;
        }

        foreach ($policies as $policy) {
            $skuStr = $policy->sku->getValue();
            $product = $products[$skuStr] ?? null;

            $rop = $policy->reorderPoint;
            if ($policy->dynamicRopEnabled) {
                try {
                    $newRop = $forecaster->forecastReorderPoint(
                        $skuStr,
                        $policy->sku->getValue(),
                        $policy->locationId,
                        5, // default leadTimeDays
                        $policy->safetyStock,
                        $windowDays,
                        $tenantId,
                        $allPos,
                        $product
                        $tenantId
                    );
                    $policy->updateReorderPoint($newRop);
                    $this->reorderPolicyRepository->save($policy);
                    $rop = $newRop;
                } catch (\Exception $e) {
                    error_log("Error forecasting ROP for SKU {$skuStr}: " . $e->getMessage());
                }
            }
            
                    error_log("Error forecasting ROP for SKU {$policy->sku->getValue()}: " . $e->getMessage());
                }
            }

            $skuStr = $policy->sku->getValue();
            $product = $productRepo->findBySku($policy->sku);

            $currentQty = 0;
            if ($product) {
                $locationIdObj = new \InventoryApp\Domain\Inventory\ValueObjects\LocationId($policy->locationId);
                $currentQty = $product->getStockAt($locationIdObj)->getStockQuantity()->getValue();
            }

            $triggered = false;
            $reason = "";

            if ($policy->shouldReorder($currentQty)) {
                $allPos = $this->poRepository->findAll();
                $alreadyOrdered = false;
                foreach ($allPos as $po) {
                    if ($po->tenantId !== $tenantId || $po->locationId !== $policy->locationId) {
                        continue;
                    }
                    if (
                        $po->getStatus() === PurchaseOrderStatus::Draft ||
                        $po->getStatus() === PurchaseOrderStatus::Approved ||
                        $po->getStatus() === PurchaseOrderStatus::Sent
                    ) {
                        foreach ($po->getItems() as $item) {
                            if ($item->variantId === $skuStr && $item->getReceivedQuantity() < $item->quantity) {
                                $alreadyOrdered = true;
                                break 2;
                            }
                        }
                    }
                }

                if (!$alreadyOrdered) {
                    $poNumber = 'AUTO-REORDER-' . $skuStr . '-' . strtoupper(base_convert((string)time(), 10, 36));
                    $poId = Uuid::uuid4()->toString();
                    $itemId = Uuid::uuid4()->toString();
                    
                    $item = new PurchaseOrderItem(
                        $itemId,
                        $policy->reorderQuantity,
                        0,
                        0

                        $skuStr,
                    );

                    $po = new PurchaseOrder(
                        $poId,
                        $poNumber,
                        'AUTO-SYSTEM-VENDOR',
                        PurchaseOrderStatus::Draft,
                        [$item]

                    $this->poRepository->save($po);
                    $allPos[] = $po; // Bolt optimization: Append new PO to avoid duplicate generation in subsequent iterations
                        $tenantId,
                        $policy->locationId,
                    );

                    $triggered = true;
                } else {
                    $reason = "Open purchase order already exists to prevent duplicate ordering";
                }
            }

            $results[] = [
                'sku'          => $skuStr,
                'locationId'   => $policy->locationId,
                'reorderPoint' => $rop,
                'triggered'    => $triggered,
                'reason'       => $reason
            ];
        }

        return $results;
    }

    public function checkPolicy(
        string $skuStr,
        string $locationId,
        int $currentQuantity,
        string $tenantId = 'default-tenant'
    ): void {
        $sku = new SKU($skuStr);
        $policy = $this->reorderPolicyRepository->findBySkuAndLocation($sku, $locationId);
        if (!$policy) {
            return;
        }

        if ($policy->shouldReorder($currentQuantity)) {
            // 1. Dispatch Event
            $event = new ReorderPointReachedEvent(
                $skuStr,
                $locationId,
                $currentQuantity,
                $policy->reorderPoint,
                $policy->reorderQuantity,
                new DateTimeImmutable()
            );
            $this->events->dispatch($event);

            // 2. Check if a draft/approved/sent purchase order already exists for this vendor/location and includes this sku
            $allPos = $this->poRepository->findAll();
            $alreadyOrdered = false;
            foreach ($allPos as $po) {
                if ($po->tenantId !== $tenantId || $po->locationId !== $locationId) {
                    continue;
                }
                if (
                    $po->getStatus() === PurchaseOrderStatus::Draft ||
                    $po->getStatus() === PurchaseOrderStatus::Approved ||
                    $po->getStatus() === PurchaseOrderStatus::Sent
                ) {
                    foreach ($po->getItems() as $item) {
                        if ($item->variantId === $skuStr && $item->getReceivedQuantity() < $item->quantity) {
                            $alreadyOrdered = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$alreadyOrdered) {
                // Automatically create a draft purchase order!
                $poNumber = 'AUTO-REORDER-' . $skuStr . '-' . strtoupper(base_convert((string)time(), 10, 36));
                $poId = Uuid::uuid4()->toString();
                $itemId = Uuid::uuid4()->toString();
                

                $item = new PurchaseOrderItem(
                    $itemId,
                    $skuStr,
                    $policy->reorderQuantity,
                    0, // unitCostCents
                    0  // receivedQuantity
                );

                $po = new PurchaseOrder(
                    $poId,
                    $poNumber,
                    'AUTO-SYSTEM-VENDOR',
                    $tenantId,
                    $locationId,
                    PurchaseOrderStatus::Draft,
                    [$item]
                );

                $this->poRepository->save($po);
            }
        }
    }
}



{


                        $policy->sku->getValue(),
                        $tenantId
                    error_log("Error forecasting ROP for SKU {$policy->sku->getValue()}: " . $e->getMessage());
                }
            }

            $product = $productRepo->findBySku($policy->sku);
            
            }


                $allPos = $this->poRepository->findAll();
                    }
                            }
                        }
                    }
                }

                    


                }
            }

        }

    }

        }


                }
                        }
                    }
                }
            }

                


            }
        }
    }
}
