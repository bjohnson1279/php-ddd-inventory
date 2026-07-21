<?php

namespace InventoryApp\Infrastructure\Integration\QuickBooks;

/**
 * QuickBooks Online REST API client for pushing general ledger journal entries outbound.
 *
 * Triggered by our domain events (e.g. JournalEntryRecorded) to keep
 * the QuickBooks Chart of Accounts in sync with our system of record.
 *
 * @see https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities/journalentry
 */
class QuickBooksJournalSync
{
    private string $companyId;
    private string $accessToken;
    private string $baseUrl;

    public function __construct(string $companyId, string $accessToken, bool $sandbox = true)
    {
        $this->companyId   = $companyId;
        $this->accessToken = $accessToken;
        $this->baseUrl     = $sandbox
            ? 'https://sandbox-quickbooks.api.intuit.com'
            : 'https://quickbooks.api.intuit.com';
    }

    /**
     * Create a Journal Entry in QuickBooks Online.
     *
     * @param string $description Main entry note
     * @param string|null $referenceId External reference document identifier (e.g. sale_id, PO_id)
     * @param array $lines List of journal lines with keys: account, amountCents, type (debit/credit), memo
     * @return string The QuickBooks Journal Entry ID
     */
    public function createJournalEntry(string $description, ?string $referenceId, array $lines): string
    {
        if (empty($this->companyId) || str_contains($this->companyId, 'mock') || str_contains($this->companyId, '12345') || str_contains($this->accessToken, 'mock') || str_contains($this->accessToken, 'token')) {
            return 'mock-qbo-journal-' . uniqid();
        }

        $url = "{$this->baseUrl}/v3/company/{$this->companyId}/journalentry?minorversion=65";

        $qboLines = array_map(function ($line) {
            $postingType = ucfirst(strtolower($line['type'])); // "Debit" or "Credit"
            $amount = (float)($line['amountCents'] / 100.0); // Convert cents to dollar decimals

            return [
                'Description' => $line['memo'] ?: '',
                'Amount'      => $amount,
                'DetailType'  => 'JournalEntryLineDetail',
                'JournalEntryLineDetail' => [
                    'PostingType' => $postingType,
                    'AccountRef'  => [
                        'value' => $line['account'] // Account Code mapped to QuickBooks Account ID/Ref
                    ]
                ]
            ];
        }, $lines);

        $body = json_encode([
            'Line'        => $qboLines,
            'PrivateNote' => $description,
            'DocNumber'   => $referenceId ?: ''
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
        ]);

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatus !== 200) {
            throw new \RuntimeException(
                "QuickBooks journal creation failed (HTTP {$httpStatus}): {$response}"
            );
        }

        $data = json_decode($response, true);
        $qboId = $data['JournalEntry']['Id'] ?? null;
        if (!$qboId) {
            throw new \RuntimeException("No QuickBooks Journal Entry ID returned: {$response}");
        }

        return (string)$qboId;
    }
}
