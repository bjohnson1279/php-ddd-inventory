<?php

namespace InventoryApp\Infrastructure\Messaging;

class AllowedClasses
{
    public static function get(): array
    {
        return [
            \DateTimeImmutable::class,
            \DateTime::class,
            \stdClass::class,
            \InventoryApp\Domain\Procurement\Events\ReorderPointReachedEvent::class,
            \InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog::class,
            \InventoryApp\Domain\Shared\Entities\OutboxEvent::class,
            \InventoryApp\Domain\Serial\Events\SerialStatusChanged::class,
            \InventoryApp\Domain\Serial\ValueObjects\SerialNumber::class,
            \InventoryApp\Domain\Accounting\Events\JournalEntryRecorded::class,
            \InventoryApp\Domain\Accounting\ValueObjects\AccountCode::class,
            \InventoryApp\Domain\Accounting\ValueObjects\CostBreakdown::class,
            \InventoryApp\Domain\Identity\Events\UserDeactivated::class,
            \InventoryApp\Domain\Identity\Events\UserRegistered::class,
            \InventoryApp\Domain\Identity\ValueObjects\TenantId::class,
            \InventoryApp\Domain\Identity\ValueObjects\Permission::class,
            \InventoryApp\Domain\Barcode\Events\BarcodeRevoked::class,
            \InventoryApp\Domain\Barcode\Events\BarcodeAssigned::class,
            \InventoryApp\Domain\Barcode\ValueObjects\Barcode::class,
            \InventoryApp\Domain\Inventory\Events\StockReceived::class,
            \InventoryApp\Domain\Inventory\Events\StockDispatched::class,
            \InventoryApp\Domain\Inventory\Events\StockTransferred::class,
            \InventoryApp\Domain\Inventory\Events\OpeningBalancePosted::class,
            \InventoryApp\Domain\Inventory\Events\StockReconciled::class,
            \InventoryApp\Domain\Inventory\Events\SaleProcessed::class,
            \InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted::class,
            \InventoryApp\Domain\Inventory\Events\ReturnProcessed::class,
            \InventoryApp\Domain\Inventory\Events\LowStockDetected::class,
            \InventoryApp\Domain\Inventory\Events\StockDecremented::class,
            \InventoryApp\Domain\Inventory\ValueObjects\CountStatus::class,
            \InventoryApp\Domain\Inventory\ValueObjects\Condition::class,
            \InventoryApp\Domain\Inventory\ValueObjects\SKU::class,
            \InventoryApp\Domain\Inventory\ValueObjects\Quantity::class,
            \InventoryApp\Domain\Inventory\ValueObjects\TransactionType::class,
            \InventoryApp\Domain\Inventory\ValueObjects\LocationId::class,
            \InventoryApp\Domain\Inventory\ValueObjects\DemandForecastId::class,
            \InventoryApp\Domain\Inventory\ValueObjects\Department::class,
            \InventoryApp\Domain\Kit\ValueObjects\KitComponent::class,
            \InventoryApp\Domain\Rfid\RfidScanProcessedEvent::class,
            \InventoryApp\Domain\Uom\ValueObjects\Quantity::class,
            \InventoryApp\Domain\Uom\ValueObjects\UnitOfMeasure::class,
            \InventoryApp\Domain\Shipping\Events\ShipmentCreatedEvent::class,
            \InventoryApp\Domain\Shipping\Events\ShipmentStatusUpdatedEvent::class,
            \InventoryApp\Domain\Shipping\ValueObjects\GeoLocation::class,
        ];
    }
}
