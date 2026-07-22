<?php

namespace InventoryApp\Domain\Procurement\Repositories;

use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;

interface PurchaseOrderRepositoryInterface
{
    public function findById(string $id): ?PurchaseOrder;
    public function findByNumber(string $poNumber): ?PurchaseOrder;
    public function findAll(): array;
    public function save(PurchaseOrder $po): void;
}
