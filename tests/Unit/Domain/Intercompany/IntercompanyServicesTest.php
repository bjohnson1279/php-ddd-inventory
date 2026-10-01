<?php

namespace Tests\Unit\Domain\Intercompany;

use PHPUnit\Framework\TestCase;
use App\Domain\Intercompany\TransferPricingRule;
use App\Domain\Intercompany\PricingRuleType;
use App\Domain\Intercompany\TransferPricingService;
use App\Domain\Intercompany\IntercompanyTransfer;
use App\Domain\Intercompany\TransferStatus;
use App\Domain\Intercompany\IntercompanyAccountingService;
use App\Domain\Intercompany\ConsolidationReportService;

class IntercompanyServicesTest extends TestCase
{
    public function testTransferPricingServiceCalculatesCorrectPrice()
    {
        $service = new TransferPricingService();
        $rule = new TransferPricingRule();
        $rule->sourceEntityId = 'E1';
        $rule->destinationEntityId = 'E2';
        $rule->ruleType = PricingRuleType::COST_PLUS;
        $rule->markupPercentage = 10.0;
        
        $this->assertEquals(1100, $service->calculateTransferPrice(1000, $rule));
    }

    public function testGeneratesCorrectEliminationEntriesAndConsolidationReport()
    {
        $acctService = new IntercompanyAccountingService();
        $consolidationService = new ConsolidationReportService();

        $transfer = new IntercompanyTransfer('TR1', 'T1', 'E1', 'E2', 'SKU1', 10, 1100, TransferStatus::DRAFT, 500);

        $transfer->ship();
        $shipmentEntries = $acctService->generateEntriesForShipment($transfer, 1000);
        $this->assertCount(3, $shipmentEntries);

        $revenueElim = null;
        foreach ($shipmentEntries as $e) {
            if ($e->isElimination && $e->debitAccount === "4000-INTERCOMPANY-REVENUE") {
                $revenueElim = $e;
            }
        }
        $this->assertNotNull($revenueElim);
        $this->assertEquals(11000, $revenueElim->amountCents);

        $transfer->receive();
        $receiptEntries = $acctService->generateEntriesForReceipt($transfer);
        $this->assertCount(3, $receiptEntries);

        $tariffEntry = null;
        foreach ($receiptEntries as $e) {
            if ($e->debitAccount === "5100-DUTIES-AND-TARIFFS") {
                $tariffEntry = $e;
            }
        }
        $this->assertNotNull($tariffEntry);
        $this->assertEquals(500, $tariffEntry->amountCents);

        $allEntries = array_merge($shipmentEntries, $receiptEntries);
        $netRev = $consolidationService->generateConsolidatedLedger("T1", $allEntries);
        $this->assertEquals(0, $netRev);
    }
}
