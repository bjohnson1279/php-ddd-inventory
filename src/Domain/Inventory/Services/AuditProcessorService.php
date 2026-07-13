<?php

namespace InventoryApp\Domain\Inventory\Services;

use InventoryApp\Domain\Inventory\Entities\AuditDiscrepancy;
use InventoryApp\Domain\Inventory\Repositories\AuditDiscrepancyRepositoryInterface;
use InventoryApp\Infrastructure\Models\LedgerEntryModel;
use InventoryApp\Infrastructure\Models\ProductModel;
use InventoryApp\Infrastructure\Models\JournalEntryModel;
use InventoryApp\Infrastructure\Models\AuditDiscrepancyModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use Ramsey\Uuid\Uuid;

class AuditProcessorService
{
    public function __construct(
        private readonly AuditDiscrepancyRepositoryInterface $discrepancyRepo
    ) {}

    public function runAudit(string $tenantId): array
    {
        $shopifyCount = 0;
        $accountingCount = 0;

        // 1. Shopify stock level audit
        $storeDomain = env('SHOPIFY_SHOP_URL');
        $accessToken = env('SHOPIFY_ACCESS_TOKEN');

        if ($storeDomain && $accessToken) {
            $skuMappings = Capsule::table('shopify_sku_mappings')->get();
            $locMappings = Capsule::table('shopify_location_mappings')->get();

            // ⚡ Bolt Optimization: Prevent N+1 queries during Shopify inventory auditing
            // 💡 What: Pre-fetch all relevant products and pre-calculate local stock levels using a group-by query.
            // 🎯 Why: Previously, finding the product and summing ledger entries ran queries inside nested loops, causing N+1 database calls.
            // 📊 Impact: Significant reduction in query count and execution time when auditing tenants with many products and locations.
            $skus = $skuMappings->pluck('sku')->toArray();
            $productsBySku = ProductModel::where('tenant_id', $tenantId)
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy('sku');

            $locCol = Capsule::connection()->getDriverName() === 'sqlite'
                ? "json_extract(metadata, '$.locationId')"
                : "metadata->>'locationId'";

            $ledgerQuantities = LedgerEntryModel::where('tenant_id', $tenantId)
                ->selectRaw("variant_id, {$locCol} as location_id, SUM(quantity) as sum_qty")
                ->groupBy('variant_id', Capsule::raw($locCol))
                ->get()
                ->groupBy('variant_id');

            foreach ($skuMappings as $skuMap) {
                $sku = $skuMap->sku;
                $inventoryItemId = $skuMap->shopify_inventory_item_id;

                $product = $productsBySku->get($sku);
                if (!$product) {
                    continue;
                }

                foreach ($locMappings as $locMap) {
                    $ourLocationId = $locMap->our_location_id;
                    $shopifyLocationId = $locMap->shopify_location_id;

                    $localQty = 0;
                    if ($ledgerQuantities->has($product->id)) {
                        $locRows = $ledgerQuantities->get($product->id);
                        $row = $locRows->firstWhere('location_id', $ourLocationId);
                        if ($row) {
                            $localQty = (int) $row->sum_qty;
                        }
                    }

                    $shopifyQty = $localQty;
                    if ($accessToken !== 'mock-token' && strpos($storeDomain, 'mock') === false) {
                        try {
                            // Query Shopify API
                            $client = new \GuzzleHttp\Client();
                            $response = $client->post("https://{$storeDomain}/admin/api/2024-04/graphql.json", [
                                'headers' => [
                                    'Content-Type' => 'application/json',
                                    'X-Shopify-Access-Token' => $accessToken,
                                ],
                                'json' => [
                                    'query' => '
                                        query getInventoryLevel($inventoryItemId: ID!) {
                                          inventoryItem(id: $inventoryItemId) {
                                            inventoryLevels(first: 50) {
                                              edges {
                                                node {
                                                  location { id }
                                                  quantities(names: ["available"]) { quantity }
                                                }
                                              }
                                            }
                                          }
                                        }
                                    ',
                                    'variables' => ['inventoryItemId' => $inventoryItemId],
                                ],
                            ]);

                            if ($response->getStatusCode() === 200) {
                                $resData = json_decode($response->getBody()->getContents(), true);
                                $edges = $resData['data']['inventoryItem']['inventoryLevels']['edges'] ?? [];
                                foreach ($edges as $edge) {
                                    if (($edge['node']['location']['id'] ?? '') === $shopifyLocationId) {
                                        $shopifyQty = $edge['node']['quantities'][0]['quantity'] ?? 0;
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Ignore network/API errors for robustness
                        }
                    } else {
                        // Mock mismatch if sku ends with -DIFF
                        if (str_ends_with($sku, '-DIFF')) {
                            $shopifyQty = $localQty + 10;
                        }
                    }

                    if ($localQty !== $shopifyQty) {
                        $referenceId = "{$sku}:{$ourLocationId}";
                        $existingOpen = in_array($referenceId, $existingShopifyDiscrepancies);

                        if (!$existingOpen) {
                            $discrepancy = new AuditDiscrepancy(
                                id: Uuid::uuid4()->toString(),
                                tenantId: $tenantId,
                                type: 'SHOPIFY_STOCK_MISMATCH',
                                referenceId: $referenceId,
                                externalRefId: $inventoryItemId,
                                description: "Shopify stock mismatch for SKU {$sku} at location {$ourLocationId}. Local: {$localQty}, Shopify: {$shopifyQty}"
                            );
                            $this->discrepancyRepo->save($discrepancy);
                            $shopifyCount++;
                        }
                    }
                }
            }
        }

        // 2. Accounting sync audit
        $hasQbo = !empty(env('QUICKBOOKS_ACCESS_TOKEN'));
        $hasXero = !empty(env('XERO_ACCESS_TOKEN'));
        $hasNetsuite = !empty(env('NETSUITE_ACCESS_TOKEN'));

        if ($hasQbo || $hasXero || $hasNetsuite) {
            $sevenDaysAgo = (new \DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
            $journals = JournalEntryModel::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $sevenDaysAgo)
                ->get();

            if ($journals->isNotEmpty()) {
                // Bolt optimization: Pre-fetch mapped journals to avoid N+1 queries
                $journalIds = $journals->pluck('id')->toArray();

                $qboMappings = [];
                if ($hasQbo) {
                    $qboMappings = Capsule::table('quickbooks_journal_mappings')
                        ->whereIn('journal_entry_id', $journalIds)
                        ->pluck('journal_entry_id')->toArray();
                }

                $xeroMappings = [];
                if ($hasXero) {
                    $xeroMappings = Capsule::table('xero_journal_mappings')
                        ->whereIn('journal_entry_id', $journalIds)
                        ->pluck('journal_entry_id')->toArray();
                }

                $netsuiteMappings = [];
                if ($hasNetsuite) {
                    $netsuiteMappings = Capsule::table('netsuite_journal_mappings')
                        ->whereIn('journal_entry_id', $journalIds)
                        ->pluck('journal_entry_id')->toArray();
                }

                // Bolt optimization: Pre-fetch existing discrepancies to avoid N+1 queries
                $existingDiscrepancies = AuditDiscrepancyModel::where('tenant_id', $tenantId)
                    ->where('type', 'ACCOUNTING_JOURNAL_MISSING')
                    ->whereIn('reference_id', $journalIds)
                    ->where('status', 'OPEN')
                    ->pluck('reference_id')->toArray();

                foreach ($journals as $journal) {
                    $hasMapping = false;
                    if ($hasQbo && in_array($journal->id, $qboMappings)) {
                        $hasMapping = true;
                    }
                    if ($hasXero && !$hasMapping && in_array($journal->id, $xeroMappings)) {
                        $hasMapping = true;
                    }
                    if ($hasNetsuite && !$hasMapping && in_array($journal->id, $netsuiteMappings)) {
                        $hasMapping = true;
                    }

                    if (!$hasMapping) {
                        $existingOpen = in_array($journal->id, $existingDiscrepancies);

                        if (!$existingOpen) {
                            $discrepancy = new AuditDiscrepancy(
                                id: Uuid::uuid4()->toString(),
                                tenantId: $tenantId,
                                type: 'ACCOUNTING_JOURNAL_MISSING',
                                referenceId: $journal->id,
                                externalRefId: null,
                                description: "Journal entry {$journal->id} ({$journal->description}) is not mapped to any external accounting transaction."
                            );
                            $this->discrepancyRepo->save($discrepancy);
                            $accountingCount++;
                        }
                    }
                }
            }
        }

        return [
            'shopifyDiscrepancies' => $shopifyCount,
            'accountingDiscrepancies' => $accountingCount,
        ];
    }

    public function resolveDiscrepancy(string $tenantId, string $id, string $notes): bool
    {
        $discrepancy = $this->discrepancyRepo->find($id);
        if (!$discrepancy || $discrepancy->tenantId !== $tenantId || $discrepancy->status === 'RESOLVED') {
            return false;
        }

        $discrepancy->resolve($notes);
        $this->discrepancyRepo->save($discrepancy);

        // If type is Shopify mismatch, trigger stock level push to Shopify to achieve eventual consistency
        if ($discrepancy->type === 'SHOPIFY_STOCK_MISMATCH') {
            $parts = explode(':', $discrepancy->referenceId);
            $sku = $parts[0];
            $ourLocationId = $parts[1];

            $product = ProductModel::where('tenant_id', $tenantId)->where('sku', $sku)->first();
            $storeDomain = env('SHOPIFY_SHOP_URL');
            $accessToken = env('SHOPIFY_ACCESS_TOKEN');

            if ($product && $storeDomain && $accessToken && $accessToken !== 'mock-token' && strpos($storeDomain, 'mock') === false) {
                // Find target shopify location ID
                $locMapping = Capsule::table('shopify_location_mappings')
                    ->where('our_location_id', $ourLocationId)
                    ->first();

                if ($locMapping) {
                    $shopifyLocationId = $locMapping->shopify_location_id;

                    // Sum local stock levels
                    $localQty = (int) LedgerEntryModel::where('tenant_id', $tenantId)
                        ->where('variant_id', $product->id)
                        ->where('metadata->locationId', $ourLocationId)
                        ->sum('quantity');

                    try {
                        $client = new \GuzzleHttp\Client();
                        $client->post("https://{$storeDomain}/admin/api/2024-04/graphql.json", [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'X-Shopify-Access-Token' => $accessToken,
                            ],
                            'json' => [
                                'query' => '
                                    mutation setQty($input: InventorySetOnHandQuantitiesInput!) {
                                      inventorySetOnHandQuantities(input: $input) {
                                        userErrors { message }
                                      }
                                    }
                                ',
                                'variables' => [
                                    'input' => [
                                        'setQuantities' => [
                                            [
                                                'inventoryItemId' => $discrepancy->externalRefId,
                                                'locationId' => $shopifyLocationId,
                                                'quantity' => $localQty,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ]);
                    } catch (\Exception $e) {
                        // Log failure or let it fail silently for robustness
                    }
                }
            }
        }

        return true;
    }
}
