<?php

namespace Tests\Unit\Domain\Catalog\Entities;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Catalog\Entities\Product;
use InventoryApp\Domain\Catalog\Entities\Variant;
use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;
use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;

class CatalogProductTest extends TestCase
{
    public function testCanCreateCatalogProduct(): void
    {
        $product = new Product('p1', 'Graphic Tee', 'A cool shirt', new Department('APPAREL'));

        $this->assertEquals('p1', $product->getId());
        $this->assertEquals('Graphic Tee', $product->getName());
        $this->assertEquals('A cool shirt', $product->getDescription());
        $this->assertEquals('APPAREL', $product->getDepartment()->getValue());
        $this->assertEmpty($product->getVariants());
    }

    public function testAddingVariantStoresIt(): void
    {
        $product = new Product('p1', 'Graphic Tee', 'A cool shirt', new Department('APPAREL'));
        $variant = new Variant('v1', 'p1', new SKU('TEE-L-RED'), ['size' => 'L', 'color' => 'Red'], 29.99);

        $product->addVariant($variant);

        $this->assertCount(1, $product->getVariants());
        $this->assertSame($variant, $product->getVariants()[0]);
    }

    public function testAddingVariantRecordsDomainEvent(): void
    {
        $product = new Product('p1', 'Graphic Tee', 'A cool shirt', new Department('APPAREL'));
        $variant = new Variant('v1', 'p1', new SKU('TEE-L-RED'), ['size' => 'L', 'color' => 'Red'], 29.99);

        $product->addVariant($variant);

        $events = $product->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(VariantAddedToCatalog::class, $events[0]);
    }

    public function testReleasedEventContainsCorrectData(): void
    {
        $product = new Product('p1', 'Graphic Tee', 'A cool shirt', new Department('APPAREL'));
        $variant = new Variant('v1', 'p1', new SKU('TEE-L-RED'), ['size' => 'L', 'color' => 'Red'], 29.99);

        $product->addVariant($variant);

        /** @var VariantAddedToCatalog $event */
        $event = $product->releaseEvents()[0];

        $this->assertEquals('p1', $event->getProductId());
        $this->assertEquals('Graphic Tee', $event->getProductName());
        $this->assertEquals('TEE-L-RED', $event->getSku()->getValue());
        $this->assertEquals('APPAREL', $event->getDepartment()->getValue());
    }

    public function testReleaseEventsEmptiesQueue(): void
    {
        $product = new Product('p1', 'Graphic Tee', 'A cool shirt', new Department('APPAREL'));
        $product->addVariant(new Variant('v1', 'p1', new SKU('TEE-L-RED'), [], 29.99));

        $product->releaseEvents(); // first release
        $secondRelease = $product->releaseEvents(); // should be empty

        $this->assertEmpty($secondRelease);
    }

    public function testMultipleVariantsCanBeAdded(): void
    {
        $product = new Product('p1', 'Graphic Tee', 'A cool shirt', new Department('APPAREL'));
        $product->addVariant(new Variant('v1', 'p1', new SKU('TEE-S-RED'), ['size' => 'S'], 29.99));
        $product->addVariant(new Variant('v2', 'p1', new SKU('TEE-M-RED'), ['size' => 'M'], 29.99));
        $product->addVariant(new Variant('v3', 'p1', new SKU('TEE-L-RED'), ['size' => 'L'], 29.99));

        $this->assertCount(3, $product->getVariants());

        $events = $product->releaseEvents();
        $this->assertCount(3, $events);
    }
}
