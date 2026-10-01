<?php

namespace App\Domain\Intercompany;

class TransferPricingService {
    public function calculateTransferPrice(int $baseUnitCostCents, TransferPricingRule $rule): int {
        if ($rule->ruleType === PricingRuleType::COST_PLUS) {
            $markup = $baseUnitCostCents * ($rule->markupPercentage / 100.0);
            return (int)($baseUnitCostCents + $markup);
        } elseif ($rule->ruleType === PricingRuleType::MARKET_BASED) {
            return (int)($baseUnitCostCents * (1 + $rule->markupPercentage / 100.0));
        }
        return $baseUnitCostCents;
    }
}

class IntercompanyAccountingService {
    public function generateEntriesForShipment(IntercompanyTransfer $transfer, int $unitCostCents): array {
        $totalCost = $transfer->quantity * $unitCostCents;
        $totalRevenue = $transfer->quantity * $transfer->transferPriceCents;

        $entries = [];

        $entries[] = new IntercompanyJournalEntry(
            $transfer->id, $transfer->sourceEntityId, "1200-INTERCOMPANY-AR", "4000-INTERCOMPANY-REVENUE", $totalRevenue, false
        );

        $entries[] = new IntercompanyJournalEntry(
            $transfer->id, $transfer->sourceEntityId, "5000-COGS", "1400-INVENTORY", $totalCost, false
        );

        $entries[] = new IntercompanyJournalEntry(
            $transfer->id, $transfer->sourceEntityId, "4000-INTERCOMPANY-REVENUE", "5000-COGS", $totalRevenue, true
        );

        return $entries;
    }

    public function generateEntriesForReceipt(IntercompanyTransfer $transfer): array {
        $totalCost = $transfer->quantity * $transfer->transferPriceCents;
        $entries = [];

        $entries[] = new IntercompanyJournalEntry(
            $transfer->id, $transfer->destinationEntityId, "1400-INVENTORY", "2200-INTERCOMPANY-AP", $totalCost, false
        );

        $entries[] = new IntercompanyJournalEntry(
            $transfer->id, $transfer->destinationEntityId, "2200-INTERCOMPANY-AP", "1200-INTERCOMPANY-AR", $totalCost, true
        );

        if ($transfer->tariffsCents > 0) {
            $entries[] = new IntercompanyJournalEntry(
                $transfer->id, $transfer->destinationEntityId, "5100-DUTIES-AND-TARIFFS", "2000-ACCOUNTS-PAYABLE", $transfer->tariffsCents, false
            );
        }

        return $entries;
    }
}

class ConsolidationReportService {
    public function generateConsolidatedLedger(string $tenantId, array $entries): int {
        $netRevenue = 0;
        foreach ($entries as $entry) {
            if ($entry->creditAccount === "4000-INTERCOMPANY-REVENUE" && !$entry->isElimination) {
                $netRevenue += $entry->amountCents;
            }
            if ($entry->debitAccount === "4000-INTERCOMPANY-REVENUE" && $entry->isElimination) {
                $netRevenue -= $entry->amountCents;
            }
        }
        return $netRevenue;
    }
}
