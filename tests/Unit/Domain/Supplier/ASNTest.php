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
        $this->assertEquals('tenant-1', $asn->getTenantId());
        $this->assertEquals('po-1', $asn->getPoId());
        $this->assertEquals('sup-1', $asn->getSupplierId());
        $this->assertEquals($date, $asn->getExpectedArrivalDate());
        $this->assertEquals('PENDING', $asn->getStatus());
        $this->assertEquals(['line1', 'line2'], $asn->getLines());
    }
}
