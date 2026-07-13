1. **Add `appendAll` method to `LedgerRepositoryInterface`**
   - In `src/Domain/Inventory/Repositories/LedgerRepositoryInterface.php`, add a new method signature `public function appendAll(array $entries): void;`.

2. **Implement `appendAll` method in `EloquentLedgerRepository`**
   - In `src/Infrastructure/Persistence/Repositories/EloquentLedgerRepository.php`, implement the `appendAll` method to handle bulk inserts of `LedgerEntry` objects efficiently using `insert`.

3. **Implement `appendAll` method in `InMemoryLedgerRepository` (if it exists)**
   - Find and implement `appendAll` in `src/Infrastructure/Persistence/Repositories/InMemoryLedgerRepository.php` to append all items in the array to the in-memory array.

4. **Refactor `AssembleKit` Use Case**
   - In `src/Application/Inventory/UseCases/AssembleKit.php`, update the `execute` method.
   - Collect all component ledger entries in a `$ledgerEntries` array inside the `foreach ($componentsToConsume as $comp)` loop instead of calling `append` individually.
   - Also, append the `$kitLedgerEntry` to this array after it is created.
   - Call `$this->ledgerRepository->appendAll($ledgerEntries);` once, replacing the individual `append` calls.

5. **Run tests**
   - Ensure `AssembleKitTest.php` and any ledger repository tests pass. We will run both Unit and Integration tests.

6. **Complete pre commit steps**
   - Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.
