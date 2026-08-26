<?php

namespace InventoryApp\Domain\Accounting\Aggregates;

use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;

final class JournalLine
{
    public function __construct(public readonly string $id, public readonly AccountCode $account, public readonly int $amountCents, public readonly DebitCredit $type, public readonly string $memo) {}
}

class JournalEntry
{
    private array $lines = [];

    public function __construct(public readonly string $id, public readonly string $tenantId, public readonly \DateTimeImmutable $date, public readonly string $description, public readonly ?string $referenceId, public readonly \InventoryApp\Domain\Accounting\Enums\AccountingMethod $method) {}

    public function addLine(AccountCode $account, int $amountCents, DebitCredit $type, string $memo = ''): void
    {
        if ($amountCents <= 0) throw new \InvalidArgumentException('Journal line amount must be positive.');
        $this->lines[] = new JournalLine(\Ramsey\Uuid\Uuid::uuid4()->toString(), $account, $amountCents, $type, $memo);
    }

    public function assertBalanced(): void
    {
        // Bolt ⚡ Optimization: Replace array_sum(array_map) with single pass loop
        // CPU/Mem metric: Eliminates 2 intermediate array allocations and O(N) traversals per check.
        $totalDebits = 0;
        $totalCredits = 0;
        foreach ($this->lines as $l) {
            if ($l->type === DebitCredit::Debit) {
                $totalDebits += $l->amountCents;
            } else {
                $totalCredits += $l->amountCents;
            }
        }
        if ($totalDebits !== $totalCredits) throw new \DomainException('Unbalanced journal entry');
    }

    public function lines(): array { return $this->lines; }
}
