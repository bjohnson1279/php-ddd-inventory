# DDD Inventory — REST API Endpoints Reference

This document lists the complete set of HTTP routes implemented in the PHP application's router (`public/index.php`), their expected payloads, and roles where applicable.

---

## 🔐 Authentication & Users

### 1. Register / Organization Setup
*   **URL**: `/api/setup`
*   **Method**: `POST`
*   **Body**:
    ```json
    {
      "orgName": "My Warehouse Org",
      "tenantId": "tenant-1",
      "adminName": "John Doe",
      "adminEmail": "admin@example.com",
      "adminPassword": "password123"
    }
    ```

### 2. User Login
*   **URL**: `/auth/login` or `/api/auth/login`
*   **Method**: `POST`
*   **Body**: `{ "tenantId": "tenant-1", "email": "admin@example.com", "password": "password123" }`
*   **Response**: `{ "token": "JWT_STRING_HERE" }`

### 3. List Users
*   **URL**: `/api/users`
*   **Method**: `GET`
*   **Headers**: `Authorization: Bearer <token>`

### 4. Register User / Invite
*   **URL**: `/api/users`
*   **Method**: `POST`
*   **Headers**: `Authorization: Bearer <token>`
*   **Body**: `{ "email": "operator@example.com", "role": "warehouse_operator" }`

### 5. Update User Role
*   **URL**: `/api/users/{userId}/role`
*   **Method**: `PATCH`
*   **Headers**: `Authorization: Bearer <token>`
*   **Body**: `{ "role": "accountant" }`

---

## 📦 Stock Control & Operations

### 1. Receive Stock
*   **URL**: `/api/inventory/receive`
*   **Method**: `POST`
*   **Body**: `{ "sku": "SKU123", "quantity": 10, "location_id": "LOC-A1" }`

### 2. Dispatch Stock
*   **URL**: `/api/inventory/dispatch`
*   **Method**: `POST`
*   **Body**: `{ "sku": "SKU123", "quantity": 2, "location_id": "LOC-A1" }`

### 3. Allocate Stock (Committed to order)
*   **URL**: `/api/inventory/allocate`
*   **Method**: `POST`
*   **Body**: `{ "sku": "SKU123", "quantity": 1, "location_id": "LOC-A1" }`

### 4. Release / Fulfill Allocation
*   **URL**: `/api/inventory/release-allocation` or `/api/inventory/fulfill-allocation`
*   **Method**: `POST`

### 5. Create / Receive In-Transit Transfers
*   **URL**: `/api/inventory/create-in-transit` or `/api/inventory/receive-in-transit`
*   **Method**: `POST`

### 6. Process Sale / Return (Batch or Single)
*   **URL**: `/api/inventory/sale` or `/api/inventory/return`
*   **Method**: `POST`

### 7. Get Stock level
*   **URL**: `/api/inventory/{sku}/stock?location_id=LOC-A1`
*   **Method**: `GET`

---

## 📝 Cycle Counts & Audits

### 1. Start Count Session
*   **URL**: `/api/inventory/counts`
*   **Method**: `POST`
*   **Response**: `{ "message": "Inventory count started successfully.", "count_id": "UUID" }`

### 2. Record Item Count
*   **URL**: `/api/inventory/counts/{count_id}/items`
*   **Method**: `POST`
*   **Body**: `{ "sku": "SKU123", "quantity": 12 }`

### 3. Complete Count & Reconcile
*   **URL**: `/api/inventory/counts/{count_id}/complete`
*   **Method**: `POST`

---

## 🔔 Real-time Notifications (SSE)

### 1. Subscribe to Notification Stream (Server-Sent Events)
Stream notifications (e.g. LowStockDetected, OpeningBalancePosted) live to dashboard clients.
*   **URL**: `/api/notifications/subscribe?token=<auth_token>`
*   **Method**: `GET`
*   **Headers**: `Content-Type: text/event-stream`

### 2. List Notifications
*   **URL**: `/api/notifications`
*   **Method**: `GET`

### 3. Mark Notification as Read
*   **URL**: `/api/notifications/{id}/read` (POST) or `/api/notifications/read-all` (POST)

---

## 🏷️ Barcodes, Serials & Returns (RMA)

### 1. Assign Barcode
*   **URL**: `/api/barcodes/assign`
*   **Method**: `POST`
*   **Body**: `{ "sku": "SKU123", "barcodeValue": "123456", "symbology": "qr", "source": "internal", "makePrimary": true }`

### 2. Register / Receive Serialized Item
*   **URL**: `/api/serials` (POST) or `/api/serials/{serial}/receive` (POST)

### 3. Create / Receive RMA Return
*   **URL**: `/api/returns/rma` (POST) or `/api/returns/rma/{id}/receive` (POST)

### 4. Resolve Quarantine
*   **URL**: `/api/returns/quarantine/{id}/resolve`
*   **Method**: `POST`
    ```json
    { "resolution": "RESTOCKED" } // or SCRAPPED
    ```

---

## 🧾 Accounting Ledger & Journal Entries

### 1. Record Journal Entry
*   **URL**: `/api/journal/entries`
*   **Method**: `POST`
*   **Body**:
    ```json
    {
      "id": "entry-uuid",
      "tenantId": "tenant-1",
      "date": "2026-06-17",
      "description": "Inventory opening balance adjustment",
      "method": "accrual",
      "lines": [
        { "accountCode": "1200", "amountCents": 10000, "type": "debit", "memo": "inventory asset" },
        { "accountCode": "3000", "amountCents": 10000, "type": "credit", "memo": "opening equity" }
      ]
    }
    ```
*   **Integrations note**: Creating a journal entry automatically triggers async event listeners pushing entries to configured **QuickBooks**, **Xero**, and **NetSuite** accounts.

### 2. Fetch Ledger Accounts
*   **URL**: `/api/journal/entries`
*   **Method**: `GET`

### 3. Stock Valuation Report
*   **URL**: `/api/reports/valuation`
*   **Method**: `GET`

