<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Inventory\Services\AuditProcessorService;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentAuditDiscrepancyRepository;
use Exception;

class AuditController
{
    private AuditProcessorService $service;
    private EloquentAuditDiscrepancyRepository $repo;

    public function __construct()
    {
        $this->repo = new EloquentAuditDiscrepancyRepository();
        $this->service = new AuditProcessorService($this->repo);
    }

    public function runAudit(RequestInterface $request, string $tenantId)
    {
        try {
            $summary = $this->service->runAudit($tenantId);
            return new Response($summary, 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[AuditController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function listDiscrepancies(RequestInterface $request, string $tenantId)
    {
        try {
            $status = $request->query('status');
            $discrepancies = $this->repo->findAll($tenantId, $status);
            
            $formatted = [];
            foreach ($discrepancies as $d) {
                $formatted[] = [
                    'id' => $d->id,
                    'tenantId' => $d->tenantId,
                    'type' => $d->type,
                    'referenceId' => $d->referenceId,
                    'externalRefId' => $d->externalRefId,
                    'description' => $d->description,
                    'status' => $d->status,
                    'occurredAt' => $d->occurredAt?->format(\DateTimeInterface::ATOM),
                    'resolvedAt' => $d->resolvedAt?->format(\DateTimeInterface::ATOM),
                    'resolutionNotes' => $d->resolutionNotes,
                ];
            }
            return new Response(['discrepancies' => $formatted], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[AuditController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function resolveDiscrepancy(RequestInterface $request, string $tenantId, string $id)
    {
        try {
            $body = $request->validate([
                'notes' => 'required|string',
            ]);

            $notes = $body['notes'];
            $success = $this->service->resolveDiscrepancy($tenantId, $id, $notes);

            if (!$success) {
                return new Response(['error' => 'Discrepancy not found or already resolved'], 404);
            }

            return new Response(['success' => true], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[AuditController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
