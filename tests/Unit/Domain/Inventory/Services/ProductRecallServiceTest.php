<?php

namespace Tests\Unit\Domain\Inventory\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Services\ProductRecallService;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\LedgerEntry;
use InventoryApp\Domain\Inventory\Enums\ReasonCode;
use Exception;
use DateTimeImmutable;

class ProductRecallServiceTest extends TestCase
{
    private $ledgerRepo;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledgerRepo = $this->createMock(LedgerRepositoryInterface::class);
        $this->service = new ProductRecallService($this->ledgerRepo);
    }

    public function emptyLotNumberProvider(): array
    {
        return [
            'empty string' => [''],
            'spaces' => ['   '],
            'tab' => ["\t"],
            'newline' => ["\n"],
            'mixed whitespace' => [" \t\n\r "],
        ];
    }

    /**
     * @dataProvider emptyLotNumberProvider
     */
    public function testTraceThrowsExceptionOnEmptyLotNumber(string $lotNumber): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Lot number cannot be empty.");

        $this->service->traceProductRecall($lotNumber);
    }

    public function testTraceProductRecallFailsOnEmptyLot(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Lot number cannot be empty.");

        $this->service->traceProductRecall('');
    }

    public function testTraceFiltersAndMapsCorrectly(): void
    {
        $date1 = new DateTimeImmutable('2023-10-27 10:00:00');
        $date2 = new DateTimeImmutable('2023-10-27 11:00:00');
        $date3 = new DateTimeImmutable('2023-10-27 12:00:00');

        $entry1 = clone (new LedgerEntry(
            id: 'entry-1',
            variantId: 'var-1',
            quantity: -5,
            reason: ReasonCode::Dispatch,
            actorId: 'actor-1',
            referenceId: 'ref-1',
            occurredAt: $date1,
            metadata: ['locationId' => 'loc-1']
        ));

        // This entry should be filtered out (quantity > 0)
        $entry2 = clone (new LedgerEntry(
            id: 'entry-2',
            variantId: 'var-1',
            quantity: 10,
            reason: ReasonCode::PurchaseReceipt,
            actorId: 'actor-2',
            referenceId: 'ref-2',
            occurredAt: $date2,
            metadata: ['locationId' => 'loc-1']
        ));

        // This entry has missing locationId, should fallback to 'default'
        $entry3 = clone (new LedgerEntry(
            id: 'entry-3',
            variantId: 'var-1',
            quantity: -2,
            reason: ReasonCode::Sale,
            actorId: 'actor-3',
            referenceId: 'ref-3',
            occurredAt: $date3,
            metadata: []
        ));

        $this->ledgerRepo->expects($this->once())
            ->method('findRecallEntries')
            ->with('LOT123')
            ->willReturn([$entry1, $entry2, $entry3]);

        $result = $this->service->traceProductRecall('LOT123');

        $this->assertCount(2, $result);

        $this->assertEquals([
            'ledgerEntryId' => 'entry-1',
            'locationId'    => 'loc-1',
            'quantity'      => 5,
            'referenceId'   => 'ref-1',
            'occurredAt'    => '2023-10-27 10:00:00',
            'actorId'       => 'actor-1',
        ], $result[0]);

        $this->assertEquals([
            'ledgerEntryId' => 'entry-3',
            'locationId'    => 'default',
            'quantity'      => 2,
            'referenceId'   => 'ref-3',
            'occurredAt'    => '2023-10-27 12:00:00',
            'actorId'       => 'actor-3',
        ], current(array_slice($result, 1, 1))); // Use array_slice + current because array_filter preserves keys
    }

    public function testTraceReturnsEmptyArrayWhenNoEntriesFound(): void
    {
        $this->ledgerRepo->expects($this->once())
            ->method('findRecallEntries')
            ->with('LOT123')
            ->willReturn([]);

        $result = $this->service->traceProductRecall('LOT123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testTraceReturnsEmptyArrayWhenOnlyPositiveQuantitiesExist(): void
    {
        $date1 = new DateTimeImmutable('2023-10-27 10:00:00');
        $date2 = new DateTimeImmutable('2023-10-27 11:00:00');

        $entry1 = clone (new LedgerEntry(
            id: 'entry-1',
            variantId: 'var-1',
            quantity: 10,
            reason: ReasonCode::PurchaseReceipt,
            actorId: 'actor-1',
            referenceId: 'ref-1',
            occurredAt: $date1,
            metadata: ['locationId' => 'loc-1']
        ));

        $entry2 = clone (new LedgerEntry(
            id: 'entry-2',
            variantId: 'var-1',
            quantity: 5,
            reason: ReasonCode::Adjustment,
            actorId: 'actor-2',
            referenceId: 'ref-2',
            occurredAt: $date2,
            metadata: ['locationId' => 'loc-1']
        ));

        $this->ledgerRepo->expects($this->once())
            ->method('findRecallEntries')
            ->with('LOT123')
            ->willReturn([$entry1, $entry2]);

        $result = $this->service->traceProductRecall('LOT123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
