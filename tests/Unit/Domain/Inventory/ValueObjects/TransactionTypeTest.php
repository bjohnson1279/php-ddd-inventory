<?php

namespace Tests\Unit\Domain\Inventory\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\ValueObjects\TransactionType;
use InvalidArgumentException;

class TransactionTypeTest extends TestCase
{
    public function testAllValidTypesCanBeCreated(): void
    {
        $this->assertEquals(TransactionType::RECEIPT, (new TransactionType(TransactionType::RECEIPT))->getValue());
        $this->assertEquals(TransactionType::SALE, (new TransactionType(TransactionType::SALE))->getValue());
        $this->assertEquals(TransactionType::DISPATCH, (new TransactionType(TransactionType::DISPATCH))->getValue());
        $this->assertEquals(TransactionType::RETURN, (new TransactionType(TransactionType::RETURN))->getValue());
        $this->assertEquals(TransactionType::ADJUSTMENT, (new TransactionType(TransactionType::ADJUSTMENT))->getValue());
    }

    public function testInvalidTypeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TransactionType('write_off');
    }
}
