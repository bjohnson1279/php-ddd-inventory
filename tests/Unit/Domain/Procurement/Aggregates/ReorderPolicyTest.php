<?php

namespace Tests\Unit\Domain\Procurement\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InvalidArgumentException;

class ReorderPolicyTest extends TestCase
{
    private function createValidPolicy(): ReorderPolicy
    {
        return new ReorderPolicy(
            id: 'rp-1',
            sku: new SKU('TEST-SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5,
            dynamicRopEnabled: false
        );
    }

    public function test_can_instantiate_valid_reorder_policy(): void
    {
        $policy = $this->createValidPolicy();

        $this->assertSame('rp-1', $policy->id);
        $this->assertEquals(new SKU('TEST-SKU-123'), $policy->sku);
        $this->assertSame('loc-1', $policy->locationId);
        $this->assertSame(10, $policy->reorderPoint);
        $this->assertSame(50, $policy->reorderQuantity);
        $this->assertSame(5, $policy->safetyStock);
        $this->assertFalse($policy->dynamicRopEnabled);
    }

    public function test_constructor_throws_exception_on_negative_reorder_point(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder point cannot be negative.");

        new ReorderPolicy(
            id: 'rp-1',
            sku: new SKU('TEST-SKU-123'),
            locationId: 'loc-1',
            reorderPoint: -1,
            reorderQuantity: 50,
            safetyStock: 5
        );
    }

    public function test_constructor_throws_exception_on_zero_reorder_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder quantity must be greater than zero.");

        new ReorderPolicy(
            id: 'rp-1',
            sku: new SKU('TEST-SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 0,
            safetyStock: 5
        );
    }

    public function test_constructor_throws_exception_on_negative_reorder_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder quantity must be greater than zero.");

        new ReorderPolicy(
            id: 'rp-1',
            sku: new SKU('TEST-SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: -10,
            safetyStock: 5
        );
    }

    public function test_constructor_throws_exception_on_negative_safety_stock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Safety stock cannot be negative.");

        new ReorderPolicy(
            id: 'rp-1',
            sku: new SKU('TEST-SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: -5
        );
    }

    public function test_update_reorder_point_updates_value_successfully(): void
    {
        $policy = $this->createValidPolicy();
        $this->assertSame(10, $policy->reorderPoint);

        $policy->updateReorderPoint(20);
        $this->assertSame(20, $policy->reorderPoint);

        $policy->updateReorderPoint(0);
        $this->assertSame(0, $policy->reorderPoint);
    }

    public function test_update_reorder_point_throws_exception_on_negative_value(): void
    {
        $policy = $this->createValidPolicy();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder point cannot be negative.");

        $policy->updateReorderPoint(-1);
    }

    public function test_should_reorder_returns_true_when_current_quantity_is_less_than_or_equal_to_reorder_point(): void
    {
        $policy = $this->createValidPolicy(); // reorder point is 10

        $this->assertTrue($policy->shouldReorder(9));
        $this->assertTrue($policy->shouldReorder(10));
        $this->assertTrue($policy->shouldReorder(0));
        $this->assertTrue($policy->shouldReorder(-5));
    }

    public function test_should_reorder_returns_false_when_current_quantity_is_greater_than_reorder_point(): void
    {
        $policy = $this->createValidPolicy(); // reorder point is 10

        $this->assertFalse($policy->shouldReorder(11));
        $this->assertFalse($policy->shouldReorder(50));
    }
}
