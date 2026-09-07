<?php

namespace Tests\Unit\Domain\Procurement\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InvalidArgumentException;

class ReorderPolicyTest extends TestCase
{
    public function test_can_create_valid_reorder_policy(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5,
            dynamicRopEnabled: true
        );

        $this->assertEquals('policy-1', $policy->id);
        $this->assertEquals('SKU-123', $policy->sku->getValue());
        $this->assertEquals('loc-1', $policy->locationId);
        $this->assertEquals(10, $policy->reorderPoint);
        $this->assertEquals(50, $policy->reorderQuantity);
        $this->assertEquals(5, $policy->safetyStock);
        $this->assertTrue($policy->dynamicRopEnabled);
    }

    public function test_cannot_create_with_negative_reorder_point(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder point cannot be negative.");

        new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: -1,
            reorderQuantity: 50,
            safetyStock: 5
        );
    }

    public function test_cannot_create_with_zero_reorder_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder quantity must be greater than zero.");

        new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 0,
            safetyStock: 5
        );
    }

    public function test_cannot_create_with_negative_reorder_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder quantity must be greater than zero.");

        new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: -5,
            safetyStock: 5
        );
    }

    public function test_cannot_create_with_negative_safety_stock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Safety stock cannot be negative.");

        new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: -1
        );
    }

    public function test_can_update_reorder_point(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $policy->updateReorderPoint(20);

        $this->assertEquals(20, $policy->reorderPoint);
    }

    public function test_cannot_update_reorder_point_to_negative_value(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Reorder point cannot be negative.");

        $policy->updateReorderPoint(-5);
    }

    public function test_should_reorder_returns_true_when_quantity_is_less_than_or_equal_to_reorder_point(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $this->assertTrue($policy->shouldReorder(10));
        $this->assertTrue($policy->shouldReorder(5));
        $this->assertTrue($policy->shouldReorder(0));
    }

    public function test_should_reorder_returns_false_when_quantity_is_greater_than_reorder_point(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: new SKU('SKU-123'),
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $this->assertFalse($policy->shouldReorder(11));
        $this->assertFalse($policy->shouldReorder(20));
    }
}
