<?php

namespace Tests\Unit\Domain\Shared\ValueObjects;

use InventoryApp\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testCanCreateMoneyFromCentsAndDollars(): void
    {
        $money1 = Money::fromCents(1050, 'USD');
        $this->assertEquals(1050, $money1->getAmountInCents());
        $this->assertEquals(10.50, $money1->getAmountInDollars());
        $this->assertEquals('USD', $money1->getCurrency());

        $money2 = Money::fromDollars(10.50, 'EUR');
        $this->assertEquals(1050, $money2->getAmountInCents());
        $this->assertEquals('EUR', $money2->getCurrency());

        $zero = Money::zero();
        $this->assertTrue($zero->isZero());
        $this->assertEquals(0, $zero->getAmountInCents());
    }

    public function testUnsupportedCurrencyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(100, 'XYZ');
    }

    public function testAddAndSubtractSameCurrency(): void
    {
        $m1 = Money::fromCents(1000, 'USD');
        $m2 = Money::fromCents(500, 'USD');

        $sum = $m1->add($m2);
        $this->assertEquals(1500, $sum->getAmountInCents());
        $this->assertNotSame($m1, $sum); // Immutability assertion

        $diff = $m1->subtract($m2);
        $this->assertEquals(500, $diff->getAmountInCents());
    }

    public function testCurrencyMismatchThrowsException(): void
    {
        $usd = Money::fromCents(1000, 'USD');
        $eur = Money::fromCents(1000, 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $usd->add($eur);
    }

    public function testMultiplyAndComparisons(): void
    {
        $money = Money::fromCents(1000, 'USD');
        $multiplied = $money->multiply(1.5);
        $this->assertEquals(1500, $multiplied->getAmountInCents());

        $m1 = Money::fromCents(1000, 'USD');
        $m2 = Money::fromCents(2000, 'USD');
        $m3 = Money::fromCents(1000, 'USD');

        $this->assertTrue($m1->equals($m3));
        $this->assertFalse($m1->equals($m2));
        $this->assertTrue($m2->isGreaterThan($m1));
        $this->assertTrue($m1->isLessThan($m2));
    }

    public function testFormattingAndToString(): void
    {
        $positive = Money::fromCents(1234, 'USD');
        $this->assertEquals('USD 12.34', $positive->format());
        $this->assertEquals('USD 12.34', (string) $positive);

        $negative = Money::fromCents(-500, 'GBP');
        $this->assertTrue($negative->isNegative());
        $this->assertEquals('-GBP 5.00', $negative->format());
    }
}
