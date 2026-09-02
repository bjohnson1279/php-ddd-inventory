## 2024-09-02 - Direct Map Hydration via pluck()
**Learning:** In Laravel's database query builder, building a hash map for O(1) lookups is traditionally done by fetching an array of IDs and flipping it: `array_flip($query->pluck('id_column')->toArray())`. However, this requires allocating a temporary 0-indexed array and performing a subsequent O(N) traversal.
**Action:** Use `->pluck('id_column', 'id_column')->toArray()` to instruct the query builder to construct the associative array natively during row hydration, eliminating the intermediate allocation and O(N) step entirely.
