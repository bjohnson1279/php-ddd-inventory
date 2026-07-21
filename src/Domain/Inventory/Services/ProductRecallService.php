<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use Exception;

class ProductRecallService
{
    public function __construct(private readonly LedgerRepositoryInterface $ledgerRepo) {}

    public function traceProductRecall(string $lotNumber): array
    {
        if (empty($lotNumber) || trim($lotNumber) === '') {
            throw new Exception("Lot number cannot be empty.");
        }

        $entries = $this->ledgerRepo->findRecallEntries($lotNumber);

        // Recall tracing is focused on deductions/dispatches (where quantity < 0)
        $dispatches = array_filter($entries, fn($e) => $e->isDeduction());

        $result = [];
        foreach ($dispatches as $e) {
            $result[] = [
                'ledgerEntryId' => $e->id,
                'locationId'    => $e->metadata['locationId'] ?? 'default',
                'quantity'      => abs($e->quantity),
                'referenceId'   => $e->referenceId,
                'occurredAt'    => $e->occurredAt->format('Y-m-d H:i:s'),
                'actorId'       => $e->actorId,
            ];
        }

        return $result;
    }
}
