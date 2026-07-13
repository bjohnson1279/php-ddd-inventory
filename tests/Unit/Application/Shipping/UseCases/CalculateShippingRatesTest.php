<?php

namespace Tests\Unit\Application\Shipping\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
use InventoryApp\Application\Ports\CarrierServiceInterface;
use InventoryApp\Application\Ports\CarrierRate;
use InvalidArgumentException;

class CalculateShippingRatesTest extends TestCase
{
    public function testExecuteReturnsRatesSuccessfully()
    {
        $carrierServiceMock = $this->createMock(CarrierServiceInterface::class);

        $sku = 'SKU-123';
        $quantity = 5;
        $destinationAddress = '123 Test St, City, ST 12345';

        $expectedRates = [
            new CarrierRate('FedEx', 1500, 3),
            new CarrierRate('UPS', 1200, 5),
        ];

        $carrierServiceMock->expects($this->once())
            ->method('fetchRates')
            ->with($sku, $quantity, $destinationAddress)
            ->willReturn($expectedRates);

        $useCase = new CalculateShippingRates($carrierServiceMock);
        $rates = $useCase->execute($sku, $quantity, $destinationAddress);

        $this->assertEquals($expectedRates, $rates);
    }

    public function testExecuteThrowsExceptionWhenSkuIsEmpty()
    {
        $carrierServiceMock = $this->createMock(CarrierServiceInterface::class);
        $carrierServiceMock->expects($this->never())
            ->method('fetchRates');

        $useCase = new CalculateShippingRates($carrierServiceMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required rate fields: sku and destinationAddress.');

        $useCase->execute('', 1, '123 Test St');
    }

    public function testExecuteThrowsExceptionWhenDestinationAddressIsEmpty()
    {
        $carrierServiceMock = $this->createMock(CarrierServiceInterface::class);
        $carrierServiceMock->expects($this->never())
            ->method('fetchRates');

        $useCase = new CalculateShippingRates($carrierServiceMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required rate fields: sku and destinationAddress.');

        $useCase->execute('SKU-123', 1, '');
    }
}
