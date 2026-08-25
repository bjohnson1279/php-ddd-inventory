1. **Analyze performance issue**:
`AssembleKit`, `DisassembleKit`, `InventoryService`, `OpeningBalanceService` processes multiple items in a loop and calls `append(LedgerEntry)` on `LedgerRepositoryInterface` sequentially, resulting in N+1 database queries.
2. **Update `LedgerRepositoryInterface`**: Add `public function appendAll(array $entries): void;` method.
3. **Update `EloquentLedgerRepository`**: Implement `appendAll` to execute a single `LedgerEntryModel::insert(...)` and iterate over the entries to log compliance events.
4. **Update `InMemoryLedgerRepository`**: Implement `appendAll`.
5. **Update loops in Use Cases / Services**:
   - `src/Application/Inventory/UseCases/AssembleKit.php`
   - `src/Application/Inventory/UseCases/DisassembleKit.php`
   - `src/Domain/Inventory/Services/InventoryService.php` (`decrementForKitSale`)
   - `src/Domain/Inventory/Services/OpeningBalanceService.php`
   Collect the `LedgerEntry` objects inside the loop into an array, and call `appendAll` outside the loop.
6. **Update Mock tests**: As `.jules/bolt.md` notes, ensure the `$this->callback()` closure signature in PHPUnit mock expectations is explicitly updated to accept an `array` parameter for `appendAll`.
