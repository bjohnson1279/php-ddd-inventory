<?php

namespace Tests\Unit\Infrastructure\Integration\Shopify;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyOrderMapper;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Application\Inventory\UseCases\ProcessSale;
use InventoryApp\Application\Inventory\UseCases\ProcessReturn;
use InventoryApp\Domain\Inventory\ValueObjects\Condition;

class ShopifyOrderMapperTest extends TestCase
{
    public function testHandleOrderPaidCallsProcessSalePerLineItem(): void
    {
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        $processSale->expects($this->once())
            ->method('executeBulk')
            ->with(
                [
                    ['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 2],
                    ['sku' => 'PANTS-M-BLK', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
                ],
                '1001'
            );

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings);
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
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        $processSale->expects($this->once())
            ->method('executeBulk')
            ->with(
                [['sku' => 'VALID-SKU', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]],
                '2002'
            );

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings);
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
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        $processReturn->expects($this->once())
            ->method('executeBulk')
            ->with(
                [['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1, 'condition' => Condition::NEW]],
                $this->anything()
            );

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings);
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
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        $processReturn->expects($this->once())
            ->method('executeBulk')
            ->with(
                [['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1, 'condition' => Condition::DAMAGED]],
                $this->anything()
            );

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings);
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
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        $processReturn->expects($this->once())
            ->method('executeBulk')
            ->with(
                [['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1, 'condition' => Condition::OPEN_BOX]],
                $this->anything()
            );

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);
        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings);
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
