<?php

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;

class AccountCodeTest extends TestCase
{
    public function testStaticFactoryMethods(): void
    {
        $cash = AccountCode::cash();
        $this->assertEquals('1000', $cash->code);
        $this->assertEquals('Cash', $cash->name);
        $this->assertEquals('asset', $cash->category);

        $cogs = AccountCode::costOfGoodsSold();
        $this->assertEquals('5000', $cogs->code);
        $this->assertEquals('expense', $cogs->category);
    }

    public function testCustomAccountCode(): void
    {
        $code = new AccountCode('1234', 'Custom Account', 'equity');
        $this->assertEquals('1234', $code->code);
        $this->assertEquals('equity', $code->category);
    }
}
