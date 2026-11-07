<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Procurement\ValueObjects;

use InvalidArgumentException;
use InventoryApp\Domain\Procurement\ValueObjects\PurchaseOrderNumber;
use PHPUnit\Framework\TestCase;

class PurchaseOrderNumberTest extends TestCase
{
    public function testCanInstantiateValidPurchaseOrderNumber(): void
    {
        $poNumber = new PurchaseOrderNumber('PO-2026-0001');

        $this->assertEquals('PO-2026-0001', $poNumber->getValue());
        $this->assertEquals('PO-2026-0001', (string) $poNumber);
    }

    public function testTrimsWhitespace(): void
    {
        $poNumber = new PurchaseOrderNumber("  \t PO-999 \n ");

        $this->assertEquals('PO-999', $poNumber->getValue());
    }

    public function testThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase order number cannot be empty.');

        new PurchaseOrderNumber('');
    }

    public function testThrowsOnWhitespaceOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase order number cannot be empty.');

        new PurchaseOrderNumber('     ');
    }

    public function testThrowsWhenExceedingMaxLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Purchase order number cannot exceed 64 characters.');

        new PurchaseOrderNumber(str_repeat('A', 65));
    }

    public function testEqualsComparison(): void
    {
        $po1 = new PurchaseOrderNumber('PO-100');
        $po2 = new PurchaseOrderNumber('PO-100');
        $po3 = new PurchaseOrderNumber('PO-200');

        $this->assertTrue($po1->equals($po2));
        $this->assertFalse($po1->equals($po3));
    }
}
