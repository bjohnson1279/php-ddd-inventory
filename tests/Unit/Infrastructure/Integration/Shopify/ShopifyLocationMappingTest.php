<?php

namespace Tests\Unit\Infrastructure\Integration\Shopify;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyOrderMapper;
use InventoryApp\Infrastructure\Integration\Shopify\ShopifyMappingRepository;
use InventoryApp\Application\Inventory\UseCases\ProcessSale;
use InventoryApp\Application\Inventory\UseCases\ProcessReturn;

class ShopifyLocationMappingTest extends TestCase
{
    public function testKnownShopifyLocationIdIsMappedToOurLocation(): void
    {
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        // Simulate: Shopify location 9876 maps to our LOC-BACKROOM
        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')
            ->with('9876')
            ->willReturn('LOC-BACKROOM');

        $processSale->expects($this->once())
            ->method('executeBulk')
            ->with(
                [['sku' => 'TEE-L-RED', 'location' => 'LOC-BACKROOM', 'quantity' => 1]],
                $this->anything()
            );

        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings);
        $mapper->handleOrderPaid([
            'id'         => 777,
            'line_items' => [
                ['sku' => 'TEE-L-RED', 'quantity' => 1, 'location_id' => '9876'],
            ],
        ]);
    }

    public function testUnknownShopifyLocationIdFallsBackToDefault(): void
    {
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        // Returns null = no mapping found
        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->method('findLocationId')->willReturn(null);

        $processSale->expects($this->once())
            ->method('executeBulk')
            ->with(
                [['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]],
                $this->anything()
            ); // default

        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings, 'LOC-STOREFRONT');
        $mapper->handleOrderPaid([
            'id'         => 778,
            'line_items' => [
                ['sku' => 'TEE-L-RED', 'quantity' => 1, 'location_id' => 'UNMAPPED-99'],
            ],
        ]);
    }

    public function testMissingLocationIdInPayloadFallsBackToDefault(): void
    {
        $processSale   = $this->createMock(ProcessSale::class);
        $processReturn = $this->createMock(ProcessReturn::class);

        $mappings = $this->createMock(ShopifyMappingRepository::class);
        $mappings->expects($this->never())->method('findLocationId');

        $processSale->expects($this->once())
            ->method('executeBulk')
            ->with(
                [['sku' => 'TEE-L-RED', 'location' => 'LOC-STOREFRONT', 'quantity' => 1]],
                $this->anything()
            );

        $mapper = new ShopifyOrderMapper($processSale, $processReturn, $mappings);
        $mapper->handleOrderPaid([
            'id'         => 779,
            'line_items' => [
                ['sku' => 'TEE-L-RED', 'quantity' => 1], // no location_id key
            ],
        ]);
    }
}
