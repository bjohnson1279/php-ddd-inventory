## 2024-02-21 - N+1 Query Verification
**Learning:** To resolve N+1 query performance issues in webhook controllers processing multiple line items, fetch all related entities upfront using batched repository methods like findBySkus, process them in memory, and persist via saveAll, rather than calling single-item UseCases in a loop.
**Action:** When reviewing webhook or bulk endpoint code, always check for loop iterations over database queries or repository calls.
