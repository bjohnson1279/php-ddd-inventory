<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Compliance\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Compliance\Services\ComplianceLedgerService;
use InventoryApp\Domain\Compliance\Entities\ComplianceLedgerEntry;
use InventoryApp\Domain\Compliance\Repositories\ComplianceLedgerRepositoryInterface;
use InventoryApp\Infrastructure\ServiceContainer;

class ComplianceLedgerServiceTest extends TestCase
{
    private $mockRepo;
    private $savedEntries = [];

    protected function setUp(): void
    {
        parent::setUp();
        putenv('COMPLIANCE_PRIVATE_KEY=test-secret-key');
        $this->savedEntries = [];

        $this->mockRepo = $this->createMock(ComplianceLedgerRepositoryInterface::class);
        
        // Mock save
        $this->mockRepo->method('save')->willReturnCallback(function (ComplianceLedgerEntry $entry) {
            $this->savedEntries[] = $entry;
        });

        // Mock findAll
        $this->mockRepo->method('findAll')->willReturnCallback(function (?string $tenantId = null) {
            return $this->savedEntries;

        // Mock getLastEntry
        $this->mockRepo->method('getLastEntry')->willReturnCallback(function (?string $tenantId = null) {
            if (empty($this->savedEntries)) {
                return null;
            }
            return $this->savedEntries[count($this->savedEntries) - 1];

        // Override binding in ServiceContainer
        $container = ServiceContainer::getInstance();
        $container->instance(ComplianceLedgerRepositoryInterface::class, $this->mockRepo);
    }

    protected function tearDown(): void
    {
        putenv('COMPLIANCE_PRIVATE_KEY');
        parent::tearDown();
    }

    public function testLogEventGeneratesValidBlockChain()
    {
        $payload = ['sku' => 'SKU-TEST-1', 'quantity' => 100];
        
        // Log 1st event
        $entry1 = ComplianceLedgerService::logEvent('tenant-1', 'actor-1', 'STOCK_ADJUSTED', $payload);
        $this->assertEquals(1, $entry1->getSequenceNumber());
        $this->assertEquals(str_repeat('0', 64), $entry1->getPreviousHash());
        $this->assertNotEmpty($entry1->getCurrentHash());
        $this->assertNotEmpty($entry1->getSignature());

        // Log 2nd event
        $entry2 = ComplianceLedgerService::logEvent('tenant-1', 'actor-1', 'STOCK_ADJUSTED', $payload);
        $this->assertEquals(2, $entry2->getSequenceNumber());
        $this->assertEquals($entry1->getCurrentHash(), $entry2->getPreviousHash());

        // Validate the ledger chain
        $validationResult = ComplianceLedgerService::validateLedger('tenant-1');
        $this->assertTrue($validationResult['isValid']);
    }

    public function testValidationFailsIfChainingIsBroken()
    {
        $payload = ['sku' => 'SKU-TEST-1'];
        

        // Tamper with the previous hash of block 2
        $tamperedEntry2 = new ComplianceLedgerEntry(
            $entry2->getId(),
            $entry2->getTenantId(),
            $entry2->getActorId(),
            $entry2->getEventType(),
            $entry2->getSequenceNumber(),
            'corrupted-hash-value-123',
            $entry2->getCurrentHash(),
            $entry2->getSignature(),
            $entry2->getPayload(),
            $entry2->getCreatedAt()
        );

        // Replace entry2 in our memory representation
        $this->savedEntries[1] = $tamperedEntry2;

        $this->assertFalse($validationResult['isValid']);
        $this->assertEquals(2, $validationResult['failedSequenceNumber']);
        $this->assertStringContainsString('Chaining hash mismatch', $validationResult['reason']);
    }

    public function testValidationFailsIfBlockContentIsTampered()
    {

        // Tamper with the payload data without updating hash/signature
        $tamperedEntry1 = new ComplianceLedgerEntry(
            $entry1->getId(),
            $entry1->getTenantId(),
            $entry1->getActorId(),
            $entry1->getEventType(),
            $entry1->getSequenceNumber(),
            $entry1->getPreviousHash(),
            $entry1->getCurrentHash(),
            $entry1->getSignature(),
            json_encode(['sku' => 'TAMPERED-SKU']),
            $entry1->getCreatedAt()

        $this->savedEntries[0] = $tamperedEntry1;

        $this->assertEquals(1, $validationResult['failedSequenceNumber']);
        $this->assertStringContainsString('Block content hash mismatch', $validationResult['reason']);
    }

    public function testValidationFailsIfSignatureIsInvalid()
    {

        // Tamper with signature
            'invalid-signature-value',
            $entry1->getPayload(),


        $this->assertStringContainsString('Cryptographic signature validation failed', $validationResult['reason']);
    }
}




{

    {

        


            }

    }

    {
        


    }

    {
        



    }

    {



    }

    {



    }
}
