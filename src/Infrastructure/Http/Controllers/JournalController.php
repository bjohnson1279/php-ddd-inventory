<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Accounting\Repositories\JournalRepositoryInterface;
use InventoryApp\Domain\Accounting\Aggregates\JournalEntry;
use InventoryApp\Domain\Accounting\ValueObjects\AccountCode;
use InventoryApp\Domain\Accounting\Enums\DebitCredit;
use InventoryApp\Domain\Accounting\Enums\AccountingMethod;
use Ramsey\Uuid\Uuid;
use Exception;

class JournalController
{
    public function record(RequestInterface $request, JournalRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'date'        => 'required|string',
                'description' => 'required|string',
                'method'      => 'required|string',
                'lines'       => 'required|array',
            ]);

            // Optional fields
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $referenceId = $body['reference_id'] ?? null;

            $id = Uuid::uuid4()->toString();
            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $date = new \DateTimeImmutable($validated['date']);

            $method = AccountingMethod::tryFrom($validated['method']);
            if ($method === null) {
                throw new \InvalidArgumentException("Invalid accounting method");
            }

            $entry = new JournalEntry($id, $tenantId, $date, $validated['description'], $referenceId, $method);

            foreach ($validated['lines'] as $line) {
                if (empty($line['account']) || !isset($line['amount']) || empty($line['type'])) {
                    throw new Exception("Each line must contain account, amount, and type");
                }
                $account = $this->resolveAccountCode($line['account']);

                $type = DebitCredit::tryFrom($line['type']);
                if ($type === null) {
                    throw new \InvalidArgumentException("Invalid line type (debit/credit)");
                }

                $amount = (int)$line['amount'];
                $memo = $line['memo'] ?? '';
                $entry->addLine($account, $amount, $type, $memo);
            }

            $entry->assertBalanced();
            $repo->save($entry);

            return new Response([
                'message' => 'Journal entry recorded successfully',
                'id'      => $id,
            ], 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[JournalController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function list(RequestInterface $request, JournalRepositoryInterface $repo)
    {
        try {
            $entries = $repo->all();
            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $filtered = array_values(array_filter($entries, fn($e) => $e['tenantId'] === $tenantId));

            return new Response(['entries' => $filtered], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[JournalController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function resolveAccountCode(string $code): AccountCode
    {
        return match ($code) {
            '1000' => AccountCode::cash(),
            '1100' => AccountCode::accountsReceivable(),
            '1200' => AccountCode::inventory(),
            '2000' => AccountCode::accountsPayable(),
            '4000' => AccountCode::salesRevenue(),
            '5000' => AccountCode::costOfGoodsSold(),
            '5100' => AccountCode::inventoryExpense(),
            default => new AccountCode($code, 'Account ' . $code, 'asset'),
        };
    }
}
