<?php

namespace InventoryApp\Domain\Accounting\Events;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use DateTimeImmutable;

class JournalEntryRecorded implements DomainEvent
{
    private string $entryId;
    private string $tenantId;
    private DateTimeImmutable $entryDate;
    private string $description;
    private ?string $referenceId;
    private string $method;
    private array $lines;
    private DateTimeImmutable $occurredOn;

    public function __construct(JournalEntry $entry)
    {
        $this->entryId     = $entry->id;
        $this->tenantId    = $entry->tenantId;
        $this->entryDate   = $entry->date;
        $this->description = $entry->description;
        $this->referenceId = $entry->referenceId;
        $this->method      = $entry->method->value;

        $this->lines = array_map(fn($l) => [
            'id'          => $l->id,
            'account'     => $l->account->code,
            'amountCents' => $l->amountCents,
            'type'        => $l->type->value,
            'memo'        => $l->memo
        ], $entry->lines());

        $this->occurredOn = new DateTimeImmutable();
    }

    public function getEntryId(): string { return $this->entryId; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getEntryDate(): DateTimeImmutable { return $this->entryDate; }
    public function getDescription(): string { return $this->description; }
    public function getReferenceId(): ?string { return $this->referenceId; }
    public function getMethod(): string { return $this->method; }
    public function getLines(): array { return $this->lines; }
    public function occurredOn(): DateTimeImmutable { return $this->occurredOn; }
}
