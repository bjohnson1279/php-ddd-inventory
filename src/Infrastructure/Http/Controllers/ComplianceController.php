<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Compliance\Services\ComplianceLedgerService;
use InventoryApp\Domain\Compliance\Repositories\ComplianceLedgerRepositoryInterface;
use InventoryApp\Infrastructure\ServiceContainer;
use Exception;

class ComplianceController
{
    private ComplianceLedgerRepositoryInterface $repo;
    private ComplianceLedgerService $service;

    public function __construct(
        ?ComplianceLedgerRepositoryInterface $repo = null,
        ?ComplianceLedgerService $service = null
    ) {
        $this->repo = $repo ?? ServiceContainer::complianceLedgerRepo();
        $this->service = $service ?? new ComplianceLedgerService($this->repo);
    }

    public function list(RequestInterface $request)
    {
        try {
            $tenantId = function_exists('tenantId') ? tenantId() : 'system';
            $entries = $this->repo->findAll($tenantId);

            $data = array_map(function($entry) {
                return [
                    'id'             => $entry->getId(),
                    'tenantId'       => $entry->getTenantId(),
                    'actorId'        => $entry->getActorId(),
                    'eventType'      => $entry->getEventType(),
                    'sequenceNumber' => $entry->getSequenceNumber(),
                    'previousHash'   => $entry->getPreviousHash(),
                    'currentHash'    => $entry->getCurrentHash(),
                    'signature'      => $entry->getSignature(),
                    'payload'        => json_decode($entry->getPayload(), true),
                    'createdAt'      => $entry->getCreatedAt()->format(\DateTime::ATOM)
                ];
            }, $entries);

            // Return in reverse order (descending sequence number) to match Express REST API behavior
            usort($data, fn($a, $b) => $b['sequenceNumber'] <=> $a['sequenceNumber']);

            return new Response($data, 200);
        } catch (Exception $e) {
            error_log('[ComplianceController] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }

    public function verify(RequestInterface $request)
    {
        try {
            $tenantId = function_exists('tenantId') ? tenantId() : 'system';
            $result = $this->service->validateLedger($tenantId);
            return new Response($result, 200);
        } catch (Exception $e) {
            error_log('[ComplianceController] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }

    public function reconstruct(RequestInterface $request)
    {
        try {
            $tenantId = function_exists('tenantId') ? tenantId() : 'system';
            $timestamp = $request->query('timestamp') ?: null;
            $result = $this->service->reconstructState($tenantId, $timestamp);
            return new Response($result, 200);
        } catch (Exception $e) {
            error_log('[ComplianceController] ' . $e->getMessage());
            return new Response(['error' => 'Failed to reconstruct state.'], 500);
        }
    }

    public function replay(RequestInterface $request)
    {
        try {
            $tenantId = function_exists('tenantId') ? tenantId() : 'system';
            $timestamp = $request->query('timestamp') ?: null;
            $result = $this->service->replayAudit($tenantId, $timestamp);
            return new Response($result, 200);
        } catch (Exception $e) {
            error_log('[ComplianceController] ' . $e->getMessage());
            return new Response(['error' => 'Failed to replay audit.'], 500);
        }
    }
}

