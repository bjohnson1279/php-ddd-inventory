<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Shared\Repositories\OutboxRepositoryInterface;
use Exception;

class OutboxController
{
    public function listDeadLettered(RequestInterface $request, OutboxRepositoryInterface $repo)
    {
        try {
            $limitVal = $request->query('limit');
            $limit = $limitVal !== null ? (int)$limitVal : 50;

            $maxAttemptsVal = $request->query('maxAttempts');
            $maxAttempts = $maxAttemptsVal !== null ? (int)$maxAttemptsVal : 5;

            $events = $repo->fetchDeadLettered($limit, $maxAttempts);

            return new Response(array_map(fn($e) => [
                'id' => $e->id,
                'eventName' => $e->eventName,
                'payload' => json_decode($e->payload, true),
                'occurredOn' => $e->occurredOn->format(\DateTimeInterface::ATOM),
                'processedAt' => $e->processedAt ? $e->processedAt->format(\DateTimeInterface::ATOM) : null,
                'attempts' => $e->attempts,
                'lastError' => $e->lastError,
                'nextAttemptAt' => $e->nextAttemptAt ? $e->nextAttemptAt->format(\DateTimeInterface::ATOM) : null,
            ], $events), 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[OutboxController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function retry(RequestInterface $request, string $id, OutboxRepositoryInterface $repo)
    {
        try {
            $repo->retryEvent($id);
            return new Response(['message' => 'Event successfully scheduled for retry'], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[OutboxController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function getStats(RequestInterface $request, OutboxRepositoryInterface $repo)
    {
        try {
            $maxAttemptsVal = $request->query('maxAttempts');
            $maxAttempts = $maxAttemptsVal !== null ? (int)$maxAttemptsVal : 5;

            $stats = $repo->fetchStats($maxAttempts);

            return new Response([
                'totalPending' => $stats['totalPending'],
                'totalProcessed' => $stats['totalProcessed'],
                'totalDeadLettered' => $stats['totalDeadLettered'],
                'recentFailures' => array_map(fn($e) => [
                    'id' => $e->id,
                    'eventName' => $e->eventName,
                    'payload' => json_decode($e->payload, true),
                    'occurredOn' => $e->occurredOn->format(\DateTimeInterface::ATOM),
                    'processedAt' => $e->processedAt ? $e->processedAt->format(\DateTimeInterface::ATOM) : null,
                    'attempts' => $e->attempts,
                    'lastError' => $e->lastError,
                    'nextAttemptAt' => $e->nextAttemptAt ? $e->nextAttemptAt->format(\DateTimeInterface::ATOM) : null,
                ], $stats['recentFailures'])
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[OutboxController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
