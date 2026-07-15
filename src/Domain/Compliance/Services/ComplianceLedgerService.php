<?php

namespace InventoryApp\Domain\Compliance\Services;

use InventoryApp\Domain\Compliance\Entities\ComplianceLedgerEntry;
use InventoryApp\Infrastructure\ServiceContainer;
use DateTime;
use Exception;

class ComplianceLedgerService
{
    private static function getPrivateKey(): string
    {
        $key = getenv('COMPLIANCE_PRIVATE_KEY');
        if (!$key || empty(trim($key))) {
            return 'compliance-fallback-secret-key-12345!@#';
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
}
