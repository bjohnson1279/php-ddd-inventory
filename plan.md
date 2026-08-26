1. **Add `findActiveByTenantAndLocation` to `PurchaseOrderRepositoryInterface`**
   - We'll add this to only load the POs for a specific tenant and location that are in an active state (Draft, Approved, Sent).

2. **Implement `findActiveByTenantAndLocation` in `EloquentPurchaseOrderRepository`**
   - Query the DB efficiently for the active POs for the tenant/location.

3. **Refactor `ReorderPolicyService::evaluatePolicies()` and `ReorderPolicyService::checkPolicy()`**
   - Replace `$allPos = $this->poRepository->findAll();` with a single `$this->poRepository->findActiveByTenantAndLocation($tenantId, $policy->locationId);` query.
   - Alternatively, even simpler and faster, just fetch all active POs for a tenant. Let's add `findAllActiveByTenant(string $tenantId)` instead.
   - Actually, since we need to track this per location and tenant for all policies, maybe `findAllActiveByTenant` is best, it avoids fetching received POs and POs for other tenants. Wait, even better, we can avoid touching the interface by just doing the `$allPos = $this->poRepository->findAll();` lookup once inside `evaluatePolicies` outside of the loop and indexing it! Oh, wait! It's already doing it outside the loop: `if ($pendingPoLookup === null) { $allPos = $this->poRepository->findAll(); ... }`.
   - Ah! Wait, look at `checkPolicy`. It calls `findAll()` for every single policy check: `$allPos = $this->poRepository->findAll();` inside the method.
   - Look at `evaluatePolicies`. It does lazy load `findAll()` once into `$pendingPoLookup`. It uses memory, but wait. If we have 10,000 Purchase Orders, `findAll()` brings all 10,000 into memory. That's a huge memory spike. If we introduce `findAllActiveByTenant(string $tenantId)` or `findPendingItemSkusByTenant(string $tenantId)`, it would be much faster.

   Let's check `ReorderPolicyService::evaluatePolicies`:
   ```php
                if ($pendingPoLookup === null) {
                    $allPos = $this->poRepository->findAll();
                    $pendingPoLookup = [];
                    foreach ($allPos as $po) {
                        if (
                            $po->getStatus() === PurchaseOrderStatus::Draft ||
                            $po->getStatus() === PurchaseOrderStatus::Approved ||
                            $po->getStatus() === PurchaseOrderStatus::Sent
                        ) {
   ```
   If we optimize the interface to add a method to only fetch pending POs or we just optimize `checkPolicy` to use a more targeted method. Wait! We are restricted from changing interfaces if it breaks things.

   Let's just implement `findActiveByTenant(string $tenantId): array` in `PurchaseOrderRepositoryInterface` and `EloquentPurchaseOrderRepository`.

   Let's update the interface:
   ```php
    public function findActiveByTenant(string $tenantId): array;
   ```
   In Eloquent:
   ```php
    public function findActiveByTenant(string $tenantId): array
    {
        $models = PurchaseOrderModel::with('items')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'approved', 'sent'])
            ->get();
        $results = [];
        foreach ($models as $model) {
            $results[] = $this->mapToDomain($model);
        }
        return $results;
    }
   ```
   In `ReorderPolicyService`:
   ```php
                if ($pendingPoLookup === null) {
                    // ⚡ Bolt: Fetch only active POs for the tenant to reduce memory and processing overhead
                    $allPos = $this->poRepository->findActiveByTenant($tenantId);
                    $pendingPoLookup = [];
                    foreach ($allPos as $po) {
                        foreach ($po->getItems() as $item) {
                            if ($item->getReceivedQuantity() < $item->quantity) {
                                $pendingPoLookup[$po->tenantId][$po->locationId][$item->variantId] = true;
                            }
                        }
                    }
                }
   ```
   For `checkPolicy`:
   ```php
            // 2. Check if a draft/approved/sent purchase order already exists for this vendor/location and includes this sku
            $allPos = $this->poRepository->findActiveByTenant($tenantId);
   ```

   Actually, `checkPolicy` is just an individual check, we could use `findActiveByTenantAndLocation($tenantId, $locationId)`. But `findActiveByTenant` is good enough.

4. **Verify Tests pass**
5. **Submit PR**
