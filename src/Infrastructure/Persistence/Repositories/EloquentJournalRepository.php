<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Infrastructure\Models\JournalEntryModel;
use Ramsey\Uuid\Uuid;

class EloquentJournalRepository implements JournalRepositoryInterface
{
    public function save(JournalEntry $entry): void
    {
        $id = $entry->id;
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            $id = Uuid::uuid4()->toString();
        }

        $lines = array_map(fn($l) => [
            'account' => $l->account->code,
            'amount'  => $l->amountCents,
            'type'    => $l->type->value,
            'memo'    => $l->memo
        ], $entry->lines());

        JournalEntryModel::updateOrCreate(
            ['id' => $id],
            [
                'tenant_id'    => $entry->tenantId,
                'entry_date'   => $entry->date->format('Y-m-d'),
                'description'  => $entry->description,
                'reference_id' => $entry->referenceId,
                'method'       => $entry->method->value,
                'lines'        => $lines,
                'created_at'   => date('Y-m-d H:i:s'),
            ]
        );

        \InventoryApp\Infrastructure\ServiceContainer::dispatcher()->dispatch(
            new \InventoryApp\Domain\Accounting\Events\JournalEntryRecorded($entry)
        );
    }

    public function all(): array
    {
        $models = JournalEntryModel::all();
        $rows = [];
        foreach ($models as $model) {
            $rows[] = [
                'id'          => $model->id,
                'tenantId'    => $model->tenant_id,
                'date'        => is_string($model->entry_date) ? $model->entry_date : $model->entry_date->format('Y-m-d'),
                'description' => $model->description,
                'referenceId' => $model->reference_id,
                'method'      => $model->method,
                'lines'       => $model->lines,
            ];
        }
        return $rows;
    }
}
