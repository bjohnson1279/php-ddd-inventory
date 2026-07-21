<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Application\Ports\CarrierRate;
use InvalidArgumentException;

class CalculateShippingRatesTest extends TestCase
{
    private CalculateShippingRates $useCase;

    /** @var CarrierServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
    private CarrierServiceInterface $carrierServiceMock;

{

    protected function setUp(): void
    {
        parent::setUp();
        $this->carrierServiceMock = $this->createMock(CarrierServiceInterface::class);
        $this->useCase = new CalculateShippingRates($this->carrierServiceMock);
    }

    public function testExecuteThrowsExceptionWhenSkuIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required rate fields: sku and destinationAddress.");

        $this->useCase->execute('', 1, '123 Main St');
    }

    public function testExecuteThrowsExceptionWhenDestinationAddressIsEmpty(): void
    {

        $this->useCase->execute('SKU-123', 1, '');
    }

    public function testExecuteReturnsRatesSuccessfully(): void
    {
        $rates = [
            new CarrierRate('FedEx', 1500, 3),
            new CarrierRate('UPS', 1200, 5)
        ];

        $this->carrierServiceMock
            ->expects($this->once())
            ->method('fetchRates')
            ->with('SKU-123', 2, '123 Main St')
            ->willReturn($rates);

        $result = $this->useCase->execute('SKU-123', 2, '123 Main St');

        $this->assertSame($rates, $result);
        $this->assertCount(2, $result);
    public function test_it_calculates_shipping_rates_successfully(): void
    {
        $sku = 'TEST-SKU';
        $quantity = 5;
        $destinationAddress = '123 Test St, Test City, TS 12345';
        $expectedRates = [];

        $this->carrierServiceMock->expects($this->once())
            ->with($sku, $quantity, $destinationAddress)
            ->willReturn($expectedRates);

        $result = $this->useCase->execute($sku, $quantity, $destinationAddress);

        $this->assertSame($expectedRates, $result);
    }

    public function test_it_throws_exception_if_sku_is_empty(): void
    {

        $this->useCase->execute('', 1, '123 Test St');
    }

    public function test_it_throws_exception_if_sku_is_only_whitespace(): void
    {

        $this->useCase->execute('   ', 1, '123 Test St');
    }

    public function test_it_throws_exception_if_destination_address_is_empty(): void
    {

        $this->useCase->execute('TEST-SKU', 1, '');
    }

    public function test_it_throws_exception_if_destination_address_is_only_whitespace(): void
    {

        $this->useCase->execute('TEST-SKU', 1, '   ');
    }

    public function test_it_handles_sku_with_string_zero_correctly(): void
    {
        $sku = '0';
        $quantity = 1;
        $destinationAddress = '123 Test St';



    }

    public function test_it_handles_destination_address_with_string_zero_correctly(): void
    {
        $destinationAddress = '0';



    }
}
