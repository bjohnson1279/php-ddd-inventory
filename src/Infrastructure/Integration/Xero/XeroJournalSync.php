<?php

namespace InventoryApp\Infrastructure\Integration\Xero;

/**
 * Xero REST API client for pushing general ledger journal entries outbound (Manual Journals).
 *
 * Triggered by our domain events (e.g. JournalEntryRecorded) to keep
 * the Xero chart of accounts in sync.
 * @see https://developer.xero.com/documentation/api/accounting/manualjournals
 */
class XeroJournalSync
{
    private string $tenantId;
    private string $accessToken;
    private string $baseUrl;

    public function __construct(string $tenantId, string $accessToken)
    {
        $this->tenantId    = $tenantId;
        $this->accessToken = $accessToken;
        $this->baseUrl     = 'https://api.xero.com/api.xro/2.0';
    }

    /**
     * Create a Manual Journal in Xero.
     *
     * @param string $description Main entry note
     * @param string|null $referenceId External reference
     * @param array $lines List of journal lines with keys: account, amountCents, type (debit/credit), memo
     * @return string The Xero Manual Journal ID
     */
    public function createManualJournal(string $description, ?string $referenceId, array $lines): string
    {
        if (empty($this->tenantId) || str_contains($this->tenantId, 'mock') || str_contains($this->accessToken, 'mock') || str_contains($this->accessToken, 'token')) {
            return 'mock-xero-journal-' . uniqid();
        }

        $url = "{$this->baseUrl}/ManualJournals";

        $xeroLines = array_map(function ($line) {
            $isCredit = strtolower($line['type']) === 'credit';
            $amount = (float)($line['amountCents'] / 100.0);
            

            // Xero Manual Journals use positive for debits, negative for credits in general ledger lines
            $lineAmount = $isCredit ? -$amount : $amount;

            return [
                'Description' => $line['memo'] ?: '',
                'LineAmount'  => $lineAmount,
                'AccountCode' => $line['account']
            ];
        }, $lines);

        $body = json_encode([
            'ManualJournals' => [[
                'Narration' => $description,
                'Reference' => $referenceId ?: '',
                'JournalLines' => $xeroLines
            ]]
        ]);

        $connectTimeout = (int)(getenv('XERO_CONNECT_TIMEOUT') ?: 10);
        $timeout = (int)(getenv('XERO_TIMEOUT') ?: 30);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Xero-tenant-id: ' . $this->tenantId,
                'Authorization: Bearer ' . $this->accessToken,
            ],

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Xero manual journal creation failed (cURL error): {$error}");
        }

        $response   = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatus !== 200 && $httpStatus !== 201) {
            throw new \RuntimeException(
                "Xero manual journal creation failed (HTTP {$httpStatus}): {$response}"
            );
        }

        $data = json_decode($response, true);
        $xeroId = $data['ManualJournals'][0]['ManualJournalID'] ?? null;
        if (!$xeroId) {
            throw new \RuntimeException("No Xero Manual Journal ID returned: {$response}");
        }

        return (string)$xeroId;
    }
}


{

    {
    }

    {
        }


            



            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,

        $response   = curl_exec($ch);

        }

        }

    }
}
