# CargoTrace REST API Reference

Base URL (local): `http://127.0.0.1:8000/api`

Protected routes require:

```http
Accept: application/json
Authorization: Bearer <sanctum-token>
```

All request bodies are JSON. Validation errors return HTTP `422`; unauthenticated requests return `401`; role failures return `403`; out-of-scope customer shipment access returns `404`.

## Authentication

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| POST | `/register` | Public, throttled | Register a customer account only |
| POST | `/login` | Public, throttled | Obtain Sanctum token |
| POST | `/logout` | Authenticated | Revoke current token |
| GET | `/me` or `/user` | Authenticated | Current account |

Register JSON: `name`, `email`, `password`, `password_confirmation`. Do not pass a role; public registration always creates `customer`.

## Dashboard and users

| Method | Endpoint | Role | Purpose |
|---|---|---|---|
| GET | `/dashboard` | All | Role-scoped KPIs, charts, recent/delayed shipments, open exceptions |
| GET | `/users` | Admin | Search/filter/paginate users |
| POST | `/users` | Admin | Create user and assign role |
| PUT/PATCH | `/users/{user}` | Admin | Update user |
| PATCH | `/users/{user}/toggle-active` | Admin | Enable/disable user; deactivation revokes tokens |
| GET | `/audit-logs` | Admin | Read-only, filterable activity log |

Dashboard stats include total, active, in-transit, delivered, delayed, delay rate, unresolved exceptions, exception rate, and unread notifications. Admin receives total/active user counts.

## Carriers and locations

| Method | Endpoint | Role | Purpose |
|---|---|---|---|
| GET | `/carriers` | All | List/filter/paginate |
| GET | `/carriers/{carrier}` | All | Carrier detail |
| POST | `/carriers` | Admin | Create carrier |
| PUT/PATCH | `/carriers/{carrier}` | Admin | Update carrier |
| DELETE | `/carriers/{carrier}` | Admin | Delete only if no shipment references it |
| GET | `/locations` | All | List/filter/paginate |
| GET | `/locations/{location}` | All | Location detail |
| POST | `/locations` | Admin | Create location |
| PUT/PATCH | `/locations/{location}` | Admin | Update location |
| DELETE | `/locations/{location}` | Admin | Delete only if no shipment/tracking references it |
| GET | `/locations/geocode/search?query=...` | Admin, throttled | Nominatim geocoding |

Location coordinates must be valid latitude `-90..90` and longitude `-180..180`.

## Shipments

| Method | Endpoint | Role | Purpose |
|---|---|---|---|
| GET | `/shipments` | All | Role-scoped list, search, filter, sort, paginate |
| POST | `/shipments` | Operator | Create shipment; system assigns `CREATED` and initial event |
| GET | `/shipments/{shipment}` | All | Detail, timeline, exceptions, ETA assessment, current location |
| PUT/PATCH | `/shipments/{shipment}` | Operator | Update shipment details |
| DELETE | `/shipments/{shipment}` | Operator | Soft archive only while `CREATED`; retains history |
| PATCH | `/shipments/{shipment}/status` | Operator | Sequential lifecycle transition |
| PATCH | `/shipments/{shipment}/eta` | Operator | Update current ETA and calculate delay |

List query params: `search`, `status`, `carrier_id`, `origin_location_id`, `destination_location_id`, `date_from`, `date_to`, `delayed`, `sort`, `direction`, `per_page`.

Create shipment JSON fields: `customer_id`, `carrier_id`, `origin_location_id`, `destination_location_id`, `cargo_type`, `package_count`, `total_weight`, `weight_unit`, `total_volume`, `volume_unit`, `expected_departure_at`, `expected_arrival_at`; optional: `shipment_number`, `customer_reference`, `cargo_description`, `notes`, and `items[]`.

Status transition JSON: `{ "status": "BOOKED", "description": "Booking confirmed" }`. Only the next lifecycle status is accepted.

ETA update JSON: `{ "current_eta": "2026-11-14T08:00:00Z", "reason": "Carrier advised a revised arrival estimate" }`.

## Tracking and exceptions

| Method | Endpoint | Role | Purpose |
|---|---|---|---|
| GET | `/shipments/{shipment}/tracking-events` or `/tracking` | All (customer ownership enforced) | Chronological, paginated event history |
| POST | `/shipments/{shipment}/tracking-events` or `/tracking` | Operator | Append location-only event |
| GET | `/shipments/{shipment}/exceptions` | All (customer ownership enforced) | Related exceptions |
| POST | `/shipments/{shipment}/exceptions` | Operator | Create exception, default status `OPEN` |
| PATCH | `/exceptions/{exception}/status` or `/exceptions/{exception}` | Operator | Advance exception status |

Location tracking JSON: `description`, `event_time`, optional `location_id`, or paired `latitude` + `longitude`, optional `metadata`. The endpoint sets event status to the shipment's current status; lifecycle changes must use the status endpoint.

Exception types: `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, `DOCUMENTATION_ISSUE`.

Exception severities: `LOW`, `MEDIUM`, `HIGH` (design scale; severity is independent from ETA delay severity).

Exception status transitions: `OPEN → INVESTIGATING → RESOLVED`. `resolution_notes` is required to resolve.

## Notifications

| Method | Endpoint | Role | Purpose |
|---|---|---|---|
| GET | `/notifications` | Authenticated | Current user's notification feed; optional `unread=1` |
| PATCH | `/notifications/{notification}/read` | Owner only | Mark a notification read |
| PATCH | `/notifications/read-all` | Authenticated | Mark all of current user's notifications read |

## Response and pagination

Paginated endpoints use Laravel's standard pagination envelope (`data`, `links`, `meta`). Single-record endpoints use `data`; create operations return `201`. Validation errors return `422` with field messages. API errors are rendered as JSON for `/api/*` routes.
