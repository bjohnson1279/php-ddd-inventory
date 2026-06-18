<?php

namespace InventoryApp\Application\Returns\UseCases;

use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Entities\RMAItem;
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use Exception;
use Ramsey\Uuid\Uuid;

class CreateRMA
{
    public function __construct(private readonly RMARepositoryInterface $rmaRepository) {}

    public function execute(array $dto): RMA
    {
        $existing = $this->rmaRepository->findByNumber($dto['rmaNumber']);
        if ($existing) {
            throw new Exception("RMA with number {$dto['rmaNumber']} already exists.");
        }

        $items = [];
        foreach ($dto['items'] as $item) {
            $items[] = new RMAItem(
                Uuid::uuid4()->toString(),
                $item['variantId'],
                $item['quantity'],
                $item['unitCostCents']
            );
        }

        $rma = new RMA(
            Uuid::uuid4()->toString(),
            $dto['rmaNumber'],
            new TenantId($dto['tenantId']),
            $dto['customerId'],
            new LocationId($dto['locationId']),
            RMAStatus::Requested,
            $items
        );

        $this->rmaRepository->save($rma);
        return $rma;
    }
}
