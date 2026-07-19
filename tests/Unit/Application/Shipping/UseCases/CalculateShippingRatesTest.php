<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculateShippingRatesTest extends TestCase
{
    private CarrierServiceInterface $carrierServiceMock;
    private CalculateShippingRates $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carrierServiceMock = $this->createMock(CarrierServiceInterface::class);
        $this->useCase = new CalculateShippingRates($this->carrierServiceMock);
    }

    public function test_it_calculates_shipping_rates_successfully(): void
    {
        $sku = 'TEST-SKU';
        $quantity = 5;
        $destinationAddress = '123 Test St, Test City, TS 12345';
        $expectedRates = []; // In a real scenario, this would be an array of CarrierRate objects, but [] is fine for testing the return value flow

        $this->carrierServiceMock->expects($this->once())
            ->method('fetchRates')
            ->with($sku, $quantity, $destinationAddress)
            ->willReturn($expectedRates);

        $result = $this->useCase->execute($sku, $quantity, $destinationAddress);

        $this->assertSame($expectedRates, $result);
    }

    public function test_it_throws_exception_if_sku_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required rate fields: sku and destinationAddress.");

        $this->useCase->execute('', 1, '123 Test St');
    }

    public function test_it_throws_exception_if_sku_is_only_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required rate fields: sku and destinationAddress.");

        $this->useCase->execute('   ', 1, '123 Test St');
    }

    public function test_it_throws_exception_if_destination_address_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required rate fields: sku and destinationAddress.");

        $this->useCase->execute('TEST-SKU', 1, '');
    }

    public function test_it_throws_exception_if_destination_address_is_only_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required rate fields: sku and destinationAddress.");

        $this->useCase->execute('TEST-SKU', 1, '   ');
    }

    public function test_it_handles_sku_with_string_zero_correctly(): void
    {
        $sku = '0';
        $quantity = 1;
        $destinationAddress = '123 Test St';
        $expectedRates = [];

        $this->carrierServiceMock->expects($this->once())
            ->method('fetchRates')
            ->with($sku, $quantity, $destinationAddress)
            ->willReturn($expectedRates);

        $result = $this->useCase->execute($sku, $quantity, $destinationAddress);

        $this->assertSame($expectedRates, $result);
    }

    public function test_it_handles_destination_address_with_string_zero_correctly(): void
    {
        $sku = 'TEST-SKU';
        $quantity = 1;
        $destinationAddress = '0';
        $expectedRates = [];

        $this->carrierServiceMock->expects($this->once())
            ->method('fetchRates')
            ->with($sku, $quantity, $destinationAddress)
            ->willReturn($expectedRates);

        $result = $this->useCase->execute($sku, $quantity, $destinationAddress);

        $this->assertSame($expectedRates, $result);
    }
}
