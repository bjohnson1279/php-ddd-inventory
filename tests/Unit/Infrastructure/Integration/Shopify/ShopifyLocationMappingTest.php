<?php

namespace Tests\Unit\Infrastructure\Integration\Shopify;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyOrderMapper;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Application\Inventory\UseCases\ProcessSaleBatch;
use InventoryApp\Application\Inventory\UseCases\ProcessReturnBatch;

class ShopifyLocationMappingTest extends TestCase
{
    public function testKnownShopifyLocationIdIsMappedToOurLocation(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        // Simulate: Shopify location 9876 maps to our LOC-BACKROOM
        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')
            ->with('9876')
            ->willReturn('LOC-BACKROOM');

        $processSaleBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'TEE-L-RED', 'location' => 'LOC-BACKROOM', 'quantity' => 1]
            ], '777');

        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings);
        $mapper->handleOrderPaid([
            'id'         => 777,
            'line_items' => [
                ['sku' => 'TEE-L-RED', 'quantity' => 1, 'location_id' => '9876'],
            ],
        ]);
    }

    public function testUnknownShopifyLocationIdFallsBackToDefault(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        // Returns null = no mapping found
        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);

        $processSaleBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
            ], '778');

        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings, 'LOC-STOREFRONT');
        $mapper->handleOrderPaid([
            'id'         => 778,
            'line_items' => [
                ['sku' => 'TEE-L-RED', 'quantity' => 1, 'location_id' => 'UNMAPPED-99'],
            ],
        ]);
    }

    public function testMissingLocationIdInPayloadFallsBackToDefault(): void
    {
        $processSaleBatch   = $this->createMock(ProcessSaleBatch::class);
        $processReturnBatch = $this->createMock(ProcessReturnBatch::class);

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->expects($this->never())->method('findLocationId');

        $processSaleBatch->expects($this->once())
            ->method('execute')
            ->with([
                ['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]
            ], '779');

        $mapper = new ShopifyOrderMapper($processSaleBatch, $processReturnBatch, $mappings);
        $mapper->handleOrderPaid([
            'id'         => 779,
            'line_items' => [
                ['sku' => 'TEE-L-RED', 'quantity' => 1], // no location_id key
            ],
        ]);
    }
}
