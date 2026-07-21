<?php

namespace Tests\Unit\Infrastructure\Integration\Shopify;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyOrderMapper;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Application\Inventory\UseCases\ProcessSaleBatch;
use InventoryApp\Application\Inventory\UseCases\ProcessReturnBatch;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;

class ShopifyOrderMapperTest extends TestCase
{
    public function testHandleOrderPaidCallsProcessSalePerLineItem(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        $processSaleBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 2],
                ['sku' => 'PANTS-M-BLK', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
            ], '1001');

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings);
        $mapper->handleOrderPaid([
            'id'         => 1001,
            'line_items' => [
                ['sku' => 'TEE-L-RED',    'quantity' => 2],
                ['sku' => 'PANTS-M-BLK',  'quantity' => 1],
            ],
        ]);
    }

    public function testHandleOrderPaidSkipsLineItemsWithNoSku(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        $processSaleBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'VALID-SKU', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
            ], '2002');

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings);
        $mapper->handleOrderPaid([
            'id'         => 2002,
            'line_items' => [
                ['sku' => 'VALID-SKU', 'quantity' => 1],
                ['sku' => '',          'quantity' => 1], // no SKU — should be skipped
                ['quantity' => 3],                        // missing SKU key — skipped
            ],
        ]);
    }

    public function testHandleRefundCreatedWithReturnRestockMapsToNewCondition(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        $processReturnBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1, 'condition' => Condition::NEW]
            ], 'SHOPIFY-REFUND-5001');

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings);
        $mapper->handleRefundCreated([
            'id'                => 5001,
            'refund_line_items' => [
                [
                    'quantity'     => 1,
                    'restock_type' => 'return',
                    'line_item'    => ['sku' => 'TEE-L-RED'],
                ],
            ],
        ]);
    }

    public function testHandleRefundCreatedWithNoRestockMapsTosDamagedCondition(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        $processReturnBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1, 'condition' => Condition::DAMAGED]
            ], 'SHOPIFY-REFUND-5002');

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings);
        $mapper->handleRefundCreated([
            'id'                => 5002,
            'refund_line_items' => [
                [
                    'quantity'     => 1,
                    'restock_type' => 'no_restock',
                    'line_item'    => ['sku' => 'TEE-L-RED'],
                ],
            ],
        ]);
    }

    public function testHandleRefundCreatedWithUnknownRestockMapsToOpenBox(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        $processReturnBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1, 'condition' => Condition::OPEN_BOX]
            ], 'SHOPIFY-REFUND-5003');

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings);
        $mapper->handleRefundCreated([
            'id'                => 5003,
            'refund_line_items' => [
                [
                    'quantity'     => 1,
                    'restock_type' => 'legacy_restock',
                    'line_item'    => ['sku' => 'TEE-L-RED'],
                ],
            ],
        ]);
    }
}
