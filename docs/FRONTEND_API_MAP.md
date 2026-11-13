# CargoTrace Frontend — API map

| Workspace / screen | API calls |
|---|---|
| Login | `POST /login`, `GET /me`, `POST /logout` |
| All dashboards | `GET /dashboard` |
| Shipment directory | `GET /shipments` |
| Shipment details | `GET /shipments/{id}` |
| Create shipment | `GET /customers`, `GET /carriers`, `GET /locations`, `POST /shipments` |
| Edit shipment | `GET /customers` (not editable), `GET /carriers`, `GET /locations`, `PATCH /shipments/{id}` |
| Lifecycle | `PATCH /shipments/{id}/status` |
| ETA | `PATCH /shipments/{id}/eta` |
| Tracking | `GET /shipments/{id}/tracking-events`, `POST /shipments/{id}/tracking-events` |
| Exceptions | `GET /shipments/{id}/exceptions`, `POST /shipments/{id}/exceptions`, `PATCH /exceptions/{id}/status` |
| Admin users | `GET /users`, `POST /users`, `PATCH /users/{id}`, `PATCH /users/{id}/toggle-active` |
| Admin carriers | `GET /carriers`, `POST /carriers`, `PATCH /carriers/{id}` |
| Admin locations | `GET /locations`, `POST /locations`, `PATCH /locations/{id}`, `GET /locations/geocode/search` |
| Audit logs | `GET /audit-logs` |
| Customer notifications | `GET /notifications`, `PATCH /notifications/{id}/read`, `PATCH /notifications/read-all` |

All calls use same-origin `/api` requests with `Accept: application/json` and `Authorization: Bearer <Sanctum token>`. The token is kept in `sessionStorage` for the current browser tab. API 401 responses return the UI to login. Laravel middleware and controller scoping remain the source of authorization.

## Added read-only endpoint

`GET /api/customers` is protected by `auth:sanctum`, `active`, and `role:admin,operator`. It returns only active CUSTOMER accounts and is used by the Operator's Create Shipment dropdown. It does not expose the Admin-only user-management endpoints.

## Layer 2 — Customer & Booking Management

| Workspace / screen | API calls |
|---|---|
| Customer Request Shipment | `GET /locations`, `POST /booking-requests` |
| Customer My Requests | `GET /booking-requests`, `GET /booking-requests/{id}` |
| Customer Revise Request | `GET /booking-requests/{id}`, `PATCH /booking-requests/{id}` (only `NEEDS_REVISION`) |
| Customer Review Quotation | `POST /booking-requests/{id}/quotation/accept`, `POST /booking-requests/{id}/quotation/decline` |
| Customer Demo Checkout | `POST /booking-requests/{id}/payments`, `POST /booking-payments/{id}/demo-confirm` (local/testing only) |
| Operator Booking Queue | `GET /booking-requests` |
| Operator Review | `POST /booking-requests/{id}/review` |
| Operator Prepare Quotation | `GET /carriers`, `POST /booking-requests/{id}/quotation` |
| Operator Convert to Shipment | `POST /booking-requests/{id}/convert` |
| Customer/Operator Notifications | `GET /notifications`, `PATCH /notifications/{id}/read`, `PATCH /notifications/read-all` |
