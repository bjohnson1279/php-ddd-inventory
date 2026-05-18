# API Endpoints

This document lists the HTTP endpoints provided by the sample application, expected request payloads, and example responses.

Note: The project does not enforce a router; the following routes are the intended mapping for the controllers under src/Infrastructure/Http/Controllers.

1) Inventory

- POST /api/inventory/receive
  - Description: Record received stock for a SKU at a location.
  - Request JSON:
    {
      "sku": "STRING",
      "quantity": INT,
      "location_id": "STRING"
    }
  - Response 200:
    { "message": "Stock received successfully" }
  - Errors: 400 with {"error": "message"}
  - Example curl:
    curl -X POST http://localhost:8000/api/inventory/receive \
      -H "Content-Type: application/json" \
      -d '{"sku":"SKU123","quantity":5,"location_id":"LOC-BACKROOM"}'

- POST /api/inventory/dispatch
  - Description: Dispatch stock (remove from inventory) for a SKU at a location.
  - Request JSON: same as receive
  - Response 200: { "message": "Stock dispatched successfully" }
  - Errors: 400

- GET /api/inventory/{sku}/stock?location_id={locationId}
  - Description: Get current stock level for a SKU (optionally scoped to location).
  - Response 200:
    {
      "sku": "SKU123",
      "location_id": "LOC-BACKROOM" | "ALL",
      "stock": INT
    }
  - Errors: 404 with {"error": "message"}
  - Example curl:
    curl http://localhost:8000/api/inventory/SKU123/stock?location_id=LOC-BACKROOM

2) Inventory Counts

- POST /api/inventory-counts
  - Description: Start a new inventory count session. Returns a generated count_id.
  - Response 201:
    { "message": "Inventory count started successfully.", "count_id": "UUID" }
  - Errors: 400
  - Example curl:
    curl -X POST http://localhost:8000/api/inventory-counts

- POST /api/inventory-counts/{count_id}/items
  - Description: Record an item count for a count session.
  - Request JSON:
    { "sku": "STRING", "quantity": INT }
  - Response 200: { "message": "Item count recorded successfully." }
  - Errors: 400
  - Example curl:
    curl -X POST http://localhost:8000/api/inventory-counts/{count_id}/items \
      -H "Content-Type: application/json" \
      -d '{"sku":"SKU123","quantity":10}'

- POST /api/inventory-counts/{count_id}/complete
  - Description: Complete the inventory count and reconcile stock.
  - Response 200: { "message": "Inventory count completed and stock reconciled." }
  - Errors: 400

3) Catalog

- POST /api/catalog/products
  - Description: Create a catalog product.
  - Request JSON:
    { "name": "Product Name", "description": "desc", "department": "GEN" }
  - Response 201: { "message": "Catalog product created successfully", "id": "prod_..." }
  - Errors: 400

- POST /api/catalog/products/{productId}/variants
  - Description: Add a variant to a product.
  - Request JSON:
    { "sku": "STRING", "attributes": { ... }, "price": 9.99 }
  - Response 201: { "message": "Variant added successfully", "id": "var_..." }
  - Errors: 400

Notes & conventions
- All endpoints return JSON and standard HTTP status codes.
- Validation uses the constructor rules from controllers; unexpected input returns a 400 error with an "error" message.
- Routes are prefixed with /api in the examples. Adjust to your routing setup.

Authentication & Authorization
- The sample controllers do not implement auth; integrate middleware as needed for production.

Extending routes
- Controllers are simple and accept use-case classes (Application/UseCases). To wire routes in Laravel, register routes in routes/api.php and bind use-case classes via the service container.

