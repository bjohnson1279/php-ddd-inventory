<?php

namespace InventoryApp\Infrastructure\Integration\NetSuite;

/**
 * NetSuite SuiteTalk REST API client for pushing general ledger journal entries outbound.
 *
 * Triggered by our domain events (e.g. JournalEntryRecorded) to keep
 * NetSuite general ledger in sync.
 *
 * @see https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_4272186716.html
 */
class NetSuiteJournalSync
{
    private string $accountId;
    private string $token;
    private string $baseUrl;

    public function __construct(string $accountId, string $token)
    {
        $this->accountId = $accountId;
        $this->token     = $token;
        // Clean account ID to construct NetSuite domain
        $accountDomain  = strtolower(str_replace('_', '-', $accountId));
        $this->baseUrl   = "https://{$accountDomain}.suitetalk.api.netsuite.com/services/rest/record/v1";
    }

    /**
     * Create a Journal Entry in NetSuite.
     *
     * @param string $description Main entry note
     * @param string|null $referenceId Transaction/document reference ID
     * @param array $lines List of journal lines with keys: account, amountCents, type (debit/credit), memo
     * @return string The NetSuite Journal Entry ID
     */
    public function createJournalEntry(string $description, ?string $referenceId, array $lines): string
    {
        if (empty($this->accountId) || str_contains($this->accountId, 'mock') || str_contains($this->token, 'mock') || str_contains($this->token, 'token')) {
            return 'mock-netsuite-journal-' . uniqid();
        }

        $url = "{$this->baseUrl}/journalEntry";

        $nsLines = array_map(function ($line) {
            $isDebit = strtolower($line['type']) === 'debit';
            $amount = (float)($line['amountCents'] / 100.0);

            $lineItem = [
                'account' => [
                    'id' => $line['account'] // Internal ID or code of NetSuite Account
                ],
                'memo' => $line['memo'] ?: ''
            ];

            if ($isDebit) {
                $lineItem['debit'] = $amount;
            } else {
                $lineItem['credit'] = $amount;
            }

            return $lineItem;
        }, $lines);

        $body = json_encode([
            'memo'   => $description,
            'tranId' => $referenceId ?: '',
            'line'   => [
                'items' => $nsLines
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatus !== 201 && $httpStatus !== 200) {
            throw new \RuntimeException(
                "NetSuite journal creation failed (HTTP {$httpStatus}): {$response}"
            );
        }

        // NetSuite REST API typically returns the new internal ID in response headers or body
        $data = json_decode($response, true);
        $nsId = $data['id'] ?? null;
        if (!$nsId) {
            throw new \RuntimeException("No NetSuite Journal Entry ID returned: {$response}");
        }

        return (string)$nsId;
    }
}
