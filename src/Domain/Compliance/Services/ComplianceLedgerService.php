<?php

namespace InventoryApp\Domain\Compliance\Services;

use InventoryApp\Domain\Compliance\Entities\ComplianceLedgerEntry;
use InventoryApp\Infrastructure\ServiceContainer;
use DateTime;

class ComplianceLedgerService
{
    private static function getPrivateKey(): string
    {
        $key = getenv('COMPLIANCE_PRIVATE_KEY') ?: getenv('COMPLIANCE_KEY');
        if (!$key || empty(trim($key))) {
            if (getenv('APP_ENV') === 'testing') {
            $env = getenv('APP_ENV');
            if ($env === 'testing' || !$env || $env === 'development') {
                return 'compliance-fallback-secret-key-12345!@#';
            }
            throw new \RuntimeException('Compliance private key environment variable is not set.');
        }
        return $key;
    }

    public static function logEvent(string $tenantId, string $actorId, string $eventType, array $payloadData): ComplianceLedgerEntry
    {
        $repo = ServiceContainer::complianceLedgerRepo();
        $lastEntry = $repo->getLastEntry($tenantId);

        if ($lastEntry) {
            $previousHash = $lastEntry->getCurrentHash();
            $sequenceNumber = $lastEntry->getSequenceNumber() + 1;
        } else {
            $previousHash = str_repeat('0', 64);
            $sequenceNumber = 1;
        }

        $payloadJson = json_encode($payloadData);
        $dataToHash = $sequenceNumber . $previousHash . $eventType . $payloadJson . $tenantId . $actorId;
        $currentHash = hash('sha256', $dataToHash);
        $signature = hash_hmac('sha256', $currentHash, self::getPrivateKey());

        $id = bin2hex(random_bytes(16)); // UUID alternative in native PHP

        $entry = new ComplianceLedgerEntry(
            $id,
            $tenantId,
            $actorId,
            $eventType,
            $sequenceNumber,
            $previousHash,
            $currentHash,
            $signature,
            $payloadJson,
            new DateTime()
        );

        $repo->save($entry);
        return $entry;
    }

    public static function validateLedger(string $tenantId = null): array
    {
        $repo = ServiceContainer::complianceLedgerRepo();
        $entries = $repo->findAll($tenantId);
        $privateKey = self::getPrivateKey();

        for ($i = 0; $i < count($entries); $i++) {
            $entry = $entries[$i];

            // 1. Verify previous hash chaining
            if ($i > 0) {
                $prev = $entries[$i - 1];
                if ($entry->getPreviousHash() !== $prev->getCurrentHash()) {
                    return [
                        'isValid' => false,
                        'failedSequenceNumber' => $entry->getSequenceNumber(),
                        'reason' => "Chaining hash mismatch. Sequence #" . $entry->getSequenceNumber() . " references " . $entry->getPreviousHash() . ", but previous block has " . $prev->getCurrentHash()
                    ];
                }
            } else {
                $expectedPrevHash = str_repeat('0', 64);
                if ($entry->getPreviousHash() !== $expectedPrevHash) {
                    return [
                        'isValid' => false,
                        'failedSequenceNumber' => $entry->getSequenceNumber(),
                        'reason' => "First block must have zeroed previous hash. Found: " . $entry->getPreviousHash()
                    ];
                }
            }

            // 2. Recalculate block hash
            $dataToHash = $entry->getSequenceNumber() . $entry->getPreviousHash() . $entry->getEventType() . $entry->getPayload() . $entry->getTenantId() . $entry->getActorId();
            $recalculatedHash = hash('sha256', $dataToHash);

            if ($entry->getCurrentHash() !== $recalculatedHash) {
                return [
                    'isValid' => false,
                    'failedSequenceNumber' => $entry->getSequenceNumber(),
                    'reason' => "Block content hash mismatch. Recalculated: $recalculatedHash, stored: " . $entry->getCurrentHash()
                ];
            }

            // 3. Verify signature
            $expectedSignature = hash_hmac('sha256', $entry->getCurrentHash(), $privateKey);
            if ($entry->getSignature() !== $expectedSignature) {
                return [
                    'isValid' => false,
                    'failedSequenceNumber' => $entry->getSequenceNumber(),
                    'reason' => "Cryptographic signature validation failed for sequence #" . $entry->getSequenceNumber()
                ];
            }
        }

        return ['isValid' => true];
    }

    public static function reconstructState(string $tenantId, ?string $timestampStr = null): array
    {
        $repo = ServiceContainer::complianceLedgerRepo();
        $entries = $repo->findAll($tenantId);

        $cutoffDate = $timestampStr ? new DateTime($timestampStr) : new DateTime();
        $filtered = array_filter($entries, function ($e) use ($cutoffDate) {
            return $e->getTimestamp() <= $cutoffDate;
        });

        $stockLevels = [];
        $binConfigurations = [];
        $accountBalances = [];

        foreach ($filtered as $entry) {
            $p = json_decode($entry->getPayload(), true) ?: [];
            $eventType = $entry->getEventType();

            if (isset($p['sku'])) {
                $loc = $p['locationId'] ?? 'LOC-DEFAULT';
                $key = $p['sku'] . '@' . $loc;
                if (!isset($stockLevels[$key])) {
                    $stockLevels[$key] = ['sku' => $p['sku'], 'locationId' => $loc, 'quantity' => 0];
                }
                if (isset($p['quantityDelta'])) {
                    $stockLevels[$key]['quantity'] += (int)$p['quantityDelta'];
                } elseif (isset($p['quantity'])) {
                    $stockLevels[$key]['quantity'] = (int)$p['quantity'];
                }
            }

            if (str_contains($eventType, 'BIN') || isset($p['binCode']) || isset($p['locationId'])) {
                $binKey = $p['binCode'] ?? $p['locationId'] ?? 'BIN-101';
                $binConfigurations[$binKey] = [
                    'binCode' => $binKey,
                    'locationId' => $p['locationId'] ?? 'LOC-DEFAULT',
                    'currentCapacity' => $p['currentCapacity'] ?? $p['quantity'] ?? 10,
                    'maxCapacity' => $p['maxCapacity'] ?? 100
                ];
            }

            if (isset($p['lines']) && is_array($p['lines'])) {
                foreach ($p['lines'] as $line) {
                    $code = $line['accountCode'] ?? '1000-ASSET';
                    if (!isset($accountBalances[$code])) {
                        $accountBalances[$code] = ['accountCode' => $code, 'accountName' => $line['accountName'] ?? 'Account', 'balance' => 0];
                    }
                    $accountBalances[$code]['balance'] += ($line['debit'] ?? 0) - ($line['credit'] ?? 0);
                }
            }
        }

        $filteredArr = array_values($filtered);
        $lastSeq = count($filteredArr) > 0 ? end($filteredArr)->getSequenceNumber() : 0;

        return [
            'timestamp' => $cutoffDate->format(DateTime::ATOM),
            'tenantId' => $tenantId,
            'eventsReplayedCount' => count($filteredArr),
            'lastSequenceNumber' => $lastSeq,
            'stockLevels' => array_values($stockLevels),
            'binConfigurations' => array_values($binConfigurations),
            'accountBalances' => array_values($accountBalances)
        ];
    }

    public static function replayAudit(string $tenantId, ?string $upToTimestamp = null): array
    {
        $repo = ServiceContainer::complianceLedgerRepo();
        $entries = $repo->findAll($tenantId);

        if ($upToTimestamp) {
            $cutoffDate = new DateTime($upToTimestamp);
            $entries = array_filter($entries, function ($e) use ($cutoffDate) {
                return $e->getTimestamp() <= $cutoffDate;
            });
        }

        return array_map(function ($e) {
            return [
                'sequenceNumber' => $e->getSequenceNumber(),
                'eventType' => $e->getEventType(),
                'timestamp' => $e->getTimestamp()->format(DateTime::ATOM),
                'hash' => $e->getCurrentHash(),
                'previousHash' => $e->getPreviousHash(),
                'payload' => json_decode($e->getPayload(), true) ?: $e->getPayload()
            ];
        }, array_values($entries));
    }
}

