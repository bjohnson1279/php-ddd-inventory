## 2024-05-15 - Prevent Information Leakage in API Responses
**Vulnerability:** Catch-all blocks returning `$e->getMessage()` for generic 500 errors risk exposing stack traces, DB errors, and internal state.
**Learning:** Only validate/domain errors (400 level) should safely return messages. Real internal exceptions (500) should be abstracted.
**Prevention:** Explicitly separate 400 validation exceptions from 500 server errors, logging the actual message and returning a sanitized generic message.
