<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Compliance\Services\ComplianceLedgerService;
use InventoryApp\Infrastructure\ServiceContainer;
use Exception;

class ComplianceController
{
    public function list(RequestInterface $request)
    {
        try {
            $tenantId = $request->query('tenantId') ?: null;
            $repo = ServiceContainer::complianceLedgerRepo();
            $entries = $repo->findAll($tenantId);

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
            $tenantId = $request->query('tenantId') ?: null;
            $result = ComplianceLedgerService::validateLedger($tenantId);
            return new Response($result, 200);
        } catch (Exception $e) {
            error_log('[ComplianceController] ' . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }
}
