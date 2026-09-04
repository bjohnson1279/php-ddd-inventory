<?php

namespace Tests\Unit\Domain\Procurement\Aggregates;

use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReorderPolicyTest extends TestCase
{
    private SKU $sku;

    protected function setUp(): void
    {
        $this->sku = new SKU('TEST-SKU-123');
    }

    public function test_can_be_instantiated_with_valid_parameters(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5,
            dynamicRopEnabled: true
        );

        $this->assertEquals('policy-1', $policy->id);
        $this->assertSame($this->sku, $policy->sku);
        $this->assertEquals('loc-1', $policy->locationId);
        $this->assertEquals(10, $policy->reorderPoint);
        $this->assertEquals(50, $policy->reorderQuantity);
        $this->assertEquals(5, $policy->safetyStock);
        $this->assertTrue($policy->dynamicRopEnabled);
    }

    public function test_can_be_instantiated_with_edge_case_parameters(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-2',
            sku: $this->sku,
            locationId: 'loc-2',
            reorderPoint: 0,
            reorderQuantity: 1,
            safetyStock: 0
        );

        $this->assertEquals(0, $policy->reorderPoint);
        $this->assertEquals(1, $policy->reorderQuantity);
        $this->assertEquals(0, $policy->safetyStock);
        $this->assertFalse($policy->dynamicRopEnabled);
    }

    public function test_throws_exception_for_negative_reorder_point(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reorder point cannot be negative.');

        new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: -1,
            reorderQuantity: 50,
            safetyStock: 5
        );
    }

    public function test_throws_exception_for_zero_reorder_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reorder quantity must be greater than zero.');

        new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 0,
            safetyStock: 5
        );
    }

    public function test_throws_exception_for_negative_reorder_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reorder quantity must be greater than zero.');

        new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: -10,
            safetyStock: 5
        );
    }

    public function test_throws_exception_for_negative_safety_stock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Safety stock cannot be negative.');

        new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: -5
        );
    }

    public function test_update_reorder_point_successfully(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $policy->updateReorderPoint(20);
        $this->assertEquals(20, $policy->reorderPoint);

        $policy->updateReorderPoint(0);
        $this->assertEquals(0, $policy->reorderPoint);
    }

    public function test_update_reorder_point_throws_exception_for_negative_value(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reorder point cannot be negative.');

        $policy->updateReorderPoint(-1);
    }

    public function test_should_reorder_returns_true_when_quantity_less_than_reorder_point(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $this->assertTrue($policy->shouldReorder(9));
    }

    public function test_should_reorder_returns_true_when_quantity_equals_reorder_point(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $this->assertTrue($policy->shouldReorder(10));
    }

    public function test_should_reorder_returns_false_when_quantity_greater_than_reorder_point(): void
    {
        $policy = new ReorderPolicy(
            id: 'policy-1',
            sku: $this->sku,
            locationId: 'loc-1',
            reorderPoint: 10,
            reorderQuantity: 50,
            safetyStock: 5
        );

        $this->assertFalse($policy->shouldReorder(11));
    }
}
