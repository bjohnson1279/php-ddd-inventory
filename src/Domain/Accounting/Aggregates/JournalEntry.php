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
        $totalDebits = array_sum(array_map(fn(JournalLine $l) => $l->type === DebitCredit::Debit ? $l->amountCents : 0, $this->lines));
        $totalCredits = array_sum(array_map(fn(JournalLine $l) => $l->type === DebitCredit::Credit ? $l->amountCents : 0, $this->lines));
        if ($totalDebits !== $totalCredits) throw new \LogicException('Unbalanced journal entry');
    }

    public function lines(): array { return $this->lines; }
}
