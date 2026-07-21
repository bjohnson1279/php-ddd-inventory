<?php

namespace InventoryApp\Infrastructure\Persistence\Repositories;

use InventoryApp\Domain\Shared\Events\DomainEvent;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use InventoryApp\Domain\Shared\Entities\OutboxEvent;
use InventoryApp\Infrastructure\Models\OutboxEventModel;
use Ramsey\Uuid\Uuid;
use DateTimeImmutable;
use ReflectionClass;
use DateTimeInterface;

class EloquentOutboxRepository implements OutboxRepositoryInterface
{
    public function save(DomainEvent|array $event): void
    {
        $id = Uuid::uuid4()->toString();

        if (is_array($event)) {
            $eventName = $event['eventName'] ?? 'UnknownEvent';
            $occurredOn = $event['occurredOn'] ?? new DateTimeImmutable();

            $payloadData = $event;
            unset($payloadData['eventName']);
            unset($payloadData['occurredOn']);

            // Format dates inside payload if any
            foreach ($payloadData as $k => $v) {
                if ($v instanceof DateTimeInterface) {
                    $payloadData[$k] = $v->format(DateTimeInterface::ATOM);
                }
            }

            $payloadData['traceId'] = $event['traceId'] ?? \InventoryApp\Infrastructure\Telemetry\TraceContext::getTraceId();
            $payload = json_encode($payloadData);
        } else {
            $eventName = (new ReflectionClass($event))->getShortName();
            $occurredOn = $event->occurredOn();

            $payloadData = $this->serializeEvent($event);
            unset($payloadData['occurredOn']);

            $payloadData['traceId'] = $payloadData['traceId'] ?? \InventoryApp\Infrastructure\Telemetry\TraceContext::getTraceId();
            $payload = json_encode($payloadData);
        }

        $occurredOnStr = $occurredOn instanceof DateTimeInterface
            ? $occurredOn->format('Y-m-d H:i:s')
            : date('Y-m-d H:i:s');

        OutboxEventModel::create([
            'id' => $id,
            'event_name' => $eventName,
            'payload' => $payload,
            'occurred_on' => $occurredOnStr,
            'processed_at' => null,
            'attempts' => 0,
            'last_error' => null,
            'next_attempt_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function fetchPending(int $limit, int $maxAttempts = 5): array
    {
        $models = OutboxEventModel::whereNull('processed_at')
            ->where('attempts', '<', $maxAttempts)
            ->where('next_attempt_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('occurred_on', 'asc')
            ->limit($limit)
            ->get();

        return array_map([$this, 'mapToEntity'], $models->all());
    }

    public function markProcessed(string $id): void
    {
        OutboxEventModel::where('id', $id)->update([
            'processed_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function markFailed(string $id, string $error): void
    {
        $model = OutboxEventModel::find($id);
        if (!$model) {
            return;
        }

        $nextAttempts = $model->attempts + 1;
        $backoffSeconds = min(pow(2, $nextAttempts), 24 * 60 * 60);
        $nextAttemptAt = date('Y-m-d H:i:s', time() + $backoffSeconds);

        $model->update([
            'attempts' => $nextAttempts,
            'last_error' => $error,
            'next_attempt_at' => $nextAttemptAt
        ]);
    }

    public function fetchDeadLettered(int $limit, int $maxAttempts = 5): array
    {
        $models = OutboxEventModel::whereNull('processed_at')
            ->where('attempts', '>=', $maxAttempts)
            ->orderBy('occurred_on', 'desc')
            ->limit($limit)
            ->get();

        return array_map([$this, 'mapToEntity'], $models->all());
    }

    public function retryEvent(string $id): void
    {
        OutboxEventModel::where('id', $id)->update([
            'attempts' => 0,
            'last_error' => null,
            'next_attempt_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function fetchStats(int $maxAttempts = 5): array
    {
        $pendingCount = OutboxEventModel::whereNull('processed_at')
            ->where('attempts', '<', $maxAttempts)
            ->count();

        $processedCount = OutboxEventModel::whereNotNull('processed_at')->count();

        $deadLetteredCount = OutboxEventModel::whereNull('processed_at')
            ->where('attempts', '>=', $maxAttempts)
            ->count();

        $recentFailures = OutboxEventModel::whereNull('processed_at')
            ->where('attempts', '>', 0)
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('occurred_on', 'desc')
            ->limit(10)
            ->get();

        return [
            'totalPending' => $pendingCount,
            'totalProcessed' => $processedCount,
            'totalDeadLettered' => $deadLetteredCount,
            'recentFailures' => array_map([$this, 'mapToEntity'], $recentFailures->all())
        ];
    }

    private function serializeEvent(object $event): array
    {
        $ref = new ReflectionClass($event);
        $data = [];
        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            $value = $prop->getValue($event);
            if ($value instanceof DateTimeInterface) {
                $data[$prop->getName()] = $value->format(DateTimeInterface::ATOM);
            } elseif (is_object($value) && method_exists($value, 'getValue')) {
                $data[$prop->getName()] = $value->getValue();
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $data[$prop->getName()] = (string)$value;
            } else {
                $data[$prop->getName()] = $value;
            }
        }
        return $data;
    }

    private function mapToEntity(OutboxEventModel $model): OutboxEvent
    {
        return new OutboxEvent(
            $model->id,
            $model->event_name,
            $model->payload,
            new DateTimeImmutable($model->occurred_on),
            $model->processed_at ? new DateTimeImmutable($model->processed_at) : null,
            $model->attempts,
            $model->last_error,
            $model->next_attempt_at ? new DateTimeImmutable($model->next_attempt_at) : null
        );
    }
}
