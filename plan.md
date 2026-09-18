1.  **Analyze memory/journal context:**
    The `.jules/bolt.md` file notes:
    "When generating keyed hash maps from Eloquent/Database collections, replacing `->keyBy('field')` with `->mapWithKeys(fn($item) => [(string)$item->field => $item])` is a de-optimization. `mapWithKeys` instantiates a new array for every single item and triggers an inner foreach loop. The fastest approach that avoids the `data_get` overhead of `keyBy('string')` while ensuring explicit string key casting is `->keyBy(fn($item) => (string)$item->field)`."
    And the context mentions using `->keyBy(fn($item) => (string)$item->id)` over `->mapWithKeys(...)`.

2.  **Target code:**
    In `src/Infrastructure/Http/Controllers/ReportController.php` lines 27-29:
    ```php
            // Fetch all locations to initialize location names
            // ⚡ Bolt: Use pluck() directly to retrieve key-value pairs without hydrating intermediate stdClass objects
            $locations = DB::table('locations')->pluck('name', 'id');
            if ($locations instanceof \Illuminate\Support\Collection) {
                $locations = $locations->mapWithKeys(function ($name, $id) { return [(string)$id => $name]; })->toArray();
            } else {
    ```
    This uses `mapWithKeys` on a collection created by `pluck('name', 'id')`.

3.  **Optimization:**
    Wait, `pluck('name', 'id')` already returns a key-value pair of id => name. So the collection isn't a collection of items, but a collection of primitives: `[1 => 'Loc A', 2 => 'Loc B']`. We want string keys `['1' => 'Loc A']`.
    Is there a better way to cast keys to string without `mapWithKeys`? `mapWithKeys` creates an array per item `[(string)$id => $name]` and merges them.
    A faster approach is a simple `foreach` loop over the collection, which doesn't allocate an intermediate array per item. Wait, we can just avoid the `if/else` entirely and write:

    ```php
            $locationsRaw = DB::table('locations')->pluck('name', 'id');
            $locations = [];
            foreach ($locationsRaw as $id => $name) {
                $locations[(string)$id] = $name;
            }
    ```
    This removes the `mapWithKeys` overhead. Let's benchmark it vs `mapWithKeys` (already done in `test_perf_4.php` above, where `foreach` was `0.79s` vs `1.04s` for `mapWithKeys`). But wait, memory mentions:
    "When using `keyBy('id')` in Laravel/Illuminate collections... pass a closure directly to `keyBy`: `->keyBy(fn($item) => (string)$item->id)`. Avoid using `mapWithKeys` for this purpose, as it is a de-optimization that creates unnecessary array instantiations per item."

    Wait, `keyBy` doesn't work on `pluck('name', 'id')` because `keyBy` is used to index objects/arrays by a key. Here the collection values are strings (`name`), not objects. So we cannot use `keyBy(fn($item) => (string)$item->id)` directly if the value is just the name. If we did `$locations = DB::table('locations')->get(['id', 'name'])`, we could use `keyBy`. But `pluck('name', 'id')` is already faster than `get()` because it bypasses object hydration.

    Is there any other place with `mapWithKeys`?
    `grep -rn "\->mapWithKeys(" src/` only shows `ReportController.php:29`.

    Let's check if there is another opportunity.
    Wait, the memory from `.jules/bolt.md` specifically says: "The fastest approach that avoids the `data_get` overhead of `keyBy('string')` while ensuring explicit string key casting is `->keyBy(fn($item) => (string)$item->field)`."
    Let's grep for `keyBy(` in the code to see if there is any `keyBy('id')` or something.
    Ah, I already ran `grep -rn "\->keyBy(" src/` and found:
    - `WebhookDeliveryWorker.php`: `keyBy(fn($item) => (string)$item->id)`
    - `AuditProcessorService.php`: `keyBy(fn($item) => (string)$item->sku)`
    - `ReportController.php`: `keyBy(fn($item) => (string)$item->id)`
    They have already been optimized! Wait, where is `mapWithKeys` used?
    `ReportController.php:29`
    Wait, could the task be to replace `mapWithKeys` in `ReportController.php:29`?
    If I look at `ReportController.php`, line 29:
    ```php
                $locations = $locations->mapWithKeys(function ($name, $id) { return [(string)$id => $name]; })->toArray();
    ```
    I can replace the whole `if/else` block:
    ```php
            $locationsRaw = DB::table('locations')->pluck('name', 'id');
            $locations = [];
            foreach ($locationsRaw as $id => $name) {
                $locations[(string)$id] = $name;
            }
    ```
    This avoids `mapWithKeys` (which creates an array on every loop iteration).

    Let me check `src/Infrastructure/Http/Controllers/ReportController.php`.
