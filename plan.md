1. **Modify `ShopifyMappingRepository` to support bulk preloading of SKUs and reverse locations**
   - Use `replace_with_git_merge_diff` to add `preloadSkus(array $skus)` and `preloadReverseLocationIds(array $ourLocationIds)` methods to `src/Infrastructure/Integration/Shopify/ShopifyMappingRepository.php`. These methods will perform batched `whereIn` queries to populate the `skuCache` and reverse lookup mapping in `locationCache`, respectively, avoiding N+1 queries.

2. **Verify `ShopifyMappingRepository` modification**
   - Use `read_file` to inspect `src/Infrastructure/Integration/Shopify/ShopifyMappingRepository.php` and verify the new methods were added correctly.

3. **Modify `ShopifyOrderMapper` to invoke the new preload methods**
   - Use `replace_with_git_merge_diff` on `src/Infrastructure/Integration/Shopify/ShopifyOrderMapper.php`. In both `handleOrderPaid` and `handleRefundCreated`, collect all SKUs from the payload and call `preloadSkus()`. After mapping the locations, collect the resolved internal location IDs and call `preloadReverseLocationIds()` before executing the batch use cases. This will prevent the subsequent `SyncStockToShopify` listener from hitting the database sequentially for each item.

4. **Verify `ShopifyOrderMapper` modification**
   - Use `read_file` to inspect `src/Infrastructure/Integration/Shopify/ShopifyOrderMapper.php` and verify the preload invocations were added correctly.

5. **Run test suite**
   - Use `run_in_bash_session` to run `DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/phpunit` to ensure all tests pass.

6. **Complete pre-commit steps**
   - Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.

7. **Submit PR**
   - Use the `submit` tool to create the PR.
   - Exact Title: "⚡ Bolt: Prevent N+1 queries during Shopify webhook batch processing"
   - Exact Description:
     * 💡 What: Added `preloadSkus` and `preloadReverseLocationIds` to `ShopifyMappingRepository` and invoked them in `ShopifyOrderMapper` to bulk-cache mappings before dispatching events.
     * 🎯 Why: When Shopify webhook batches were processed (e.g., `orders/paid`), `SyncStockToShopify` evaluated mappings sequentially for each item, leading to N+1 database queries against mapping tables.
     * 📊 Impact: Eliminates N+1 queries against `shopify_sku_mappings` and `shopify_location_mappings` during bulk sales and returns processing, significantly reducing processing latency and database load.
     * 🔬 Measurement: Verify by executing a Shopify webhook payload containing multiple line items; observe the database query logs to confirm only bulk `whereIn` queries are executed instead of sequential single-item lookups.
