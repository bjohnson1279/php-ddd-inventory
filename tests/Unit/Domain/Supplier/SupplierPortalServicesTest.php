<?php

namespace Tests\Unit\Domain\Supplier;

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 4) . "/src/Domain/Supplier/ASN.php";
require_once dirname(__DIR__, 4) . "/src/Domain/Supplier/ASNSubmissionService.php";
require_once dirname(__DIR__, 4) . "/src/Domain/Supplier/OTIFCalculationService.php";
use App\Domain\Supplier\ASN;
use App\Domain\Supplier\ASNSubmissionService;
use App\Domain\Supplier\OTIFCalculationService;
use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;

class SupplierPortalServicesTest extends TestCase
{
    public function testASNSubmissionValidation()
    {
        $service = new ASNSubmissionService();
        $po = new PurchaseOrder('po1', 'PO-001', 'sup1', 'ten1', 'loc1', PurchaseOrderStatus::Sent);
        
        $line = new \stdClass();
        $line->sku = 'SKU1';
        $line->shippedQuantity = 10;
        
        $asn = new ASN('asn1', 'ten1', 'po1', 'sup1', new \DateTimeImmutable('+1 day'), 'PENDING', [$line]);

        $result = $service->validateAndSubmitASN($po, $asn);
        $this->assertEquals('SUBMITTED', $result->getStatus());
    }

    public function testOTIFCalculation()
    {
        $service = new OTIFCalculationService();
        
        $line = new \stdClass();
        $line->sku = 'SKU1';
        $line->shippedQuantity = 10;

        $asn = new ASN('asn1', 'ten1', 'po1', 'sup1', new \DateTimeImmutable('+2 days'), 'SUBMITTED', [$line]);

        $actualReceiptDate = new \DateTimeImmutable('+1 day');
        $actualQuantities = ['SKU1' => 10];

        $result = $service->calculateOTIF($asn, $actualReceiptDate, $actualQuantities);

        $this->assertTrue($result['onTime']);
        $this->assertTrue($result['inFull']);
        $this->assertTrue($result['otifSuccess']);
        $this->assertEquals(0, $result['defectRatePercentage']);
    }
}
