<?php

namespace Tests\Unit\Domain\Supplier;

use App\Domain\Supplier\ASN;
use PHPUnit\Framework\TestCase;

class ASNTest extends TestCase
{
    public function testASNEntity(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $asn = new ASN(
            'asn-123',
            'tenant-1',
            'po-1',
            'sup-1',
            $date,
            'PENDING',
            ['line1', 'line2']
        );

        $this->assertEquals('asn-123', $asn->getId());

        // Use reflection to check properties since there are no getters for other fields yet
        $reflection = new \ReflectionClass(ASN::class);

        $tenantIdProperty = $reflection->getProperty('tenantId');
        $tenantIdProperty->setAccessible(true);
        $this->assertEquals('tenant-1', $tenantIdProperty->getValue($asn));

        $poIdProperty = $reflection->getProperty('poId');
        $poIdProperty->setAccessible(true);
        $this->assertEquals('po-1', $poIdProperty->getValue($asn));

        $supplierIdProperty = $reflection->getProperty('supplierId');
        $supplierIdProperty->setAccessible(true);
        $this->assertEquals('sup-1', $supplierIdProperty->getValue($asn));

        $dateProperty = $reflection->getProperty('expectedArrivalDate');
        $dateProperty->setAccessible(true);
        $this->assertEquals($date, $dateProperty->getValue($asn));

        $statusProperty = $reflection->getProperty('status');
        $statusProperty->setAccessible(true);
        $this->assertEquals('PENDING', $statusProperty->getValue($asn));

        $linesProperty = $reflection->getProperty('lines');
        $linesProperty->setAccessible(true);
        $this->assertEquals(['line1', 'line2'], $linesProperty->getValue($asn));
    }
}
