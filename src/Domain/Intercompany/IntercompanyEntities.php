<?php

namespace App\Domain\Intercompany;

class TransferStatus {
    public const DRAFT = 'DRAFT';
    public const SHIPPED = 'SHIPPED';
    public const RECEIVED = 'RECEIVED';
    public const COMPLETED = 'COMPLETED';
}

class PricingRuleType {
    public const COST_PLUS = 'COST_PLUS';
    public const MARKET_BASED = 'MARKET_BASED';
}

class LegalEntity {
    public string $id;
    public string $tenantId;
    public string $name;
    public string $currencyCode;
    public string $taxIdentificationNumber;
}

class TransferPricingRule {
    public string $sourceEntityId;
    public string $destinationEntityId;
    public string $ruleType;
    public float $markupPercentage;
}

class IntercompanyTransfer {
    public string $id;
    public string $tenantId;
    public string $sourceEntityId;
    public string $destinationEntityId;
    public string $sku;
    public int $quantity;
    public int $transferPriceCents;
    public string $status;
    public int $tariffsCents;

    public function __construct(
        string $id,
        string $tenantId,
        string $sourceEntityId,
        string $destinationEntityId,
        string $sku,
        int $quantity,
        int $transferPriceCents,
        string $status = TransferStatus::DRAFT,
        int $tariffsCents = 0
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->sourceEntityId = $sourceEntityId;
        $this->destinationEntityId = $destinationEntityId;
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->transferPriceCents = $transferPriceCents;
        $this->status = $status;
        $this->tariffsCents = $tariffsCents;
    }

    public function ship(): void {
        if ($this->status !== TransferStatus::DRAFT) {
            throw new \Exception("Can only ship DRAFT transfers");
        }
        $this->status = TransferStatus::SHIPPED;
    }

    public function receive(): void {
        if ($this->status !== TransferStatus::SHIPPED) {
            throw new \Exception("Can only receive SHIPPED transfers");
        }
        $this->status = TransferStatus::RECEIVED;
    }

    public function complete(): void {
        if ($this->status !== TransferStatus::RECEIVED) {
            throw new \Exception("Can only complete RECEIVED transfers");
        }
        $this->status = TransferStatus::COMPLETED;
    }
}

class IntercompanyJournalEntry {
    public string $transferId;
    public string $entityId;
    public string $debitAccount;
    public string $creditAccount;
    public int $amountCents;
    public bool $isElimination;

    public function __construct(
        string $transferId,
        string $entityId,
        string $debitAccount,
        string $creditAccount,
        int $amountCents,
        bool $isElimination
    ) {
        $this->transferId = $transferId;
        $this->entityId = $entityId;
        $this->debitAccount = $debitAccount;
        $this->creditAccount = $creditAccount;
        $this->amountCents = $amountCents;
        $this->isElimination = $isElimination;
    }
}
