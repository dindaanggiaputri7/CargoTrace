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

## Layer 2 — Customer booking workflow

Booking endpoints require Sanctum authentication. Customer access is owner-scoped; Operator can review and convert. Admin may read the queue but has no operational booking action in this release.

| Method | Endpoint | Role | Purpose |
|---|---|---|---|
| GET | `/booking-requests` | All authenticated roles | Customer sees own requests; Operator/Admin see the authorized queue |
| POST | `/booking-requests` | Customer | Submit booking request; returns `PENDING_REVIEW` and a `BR-...` reference |
| GET | `/booking-requests/{booking}` | All authenticated roles | Detail with cargo items, history, latest quotation/payment and linked shipment |
| PATCH | `/booking-requests/{booking}` | Owner Customer | Update and resubmit only when status is `NEEDS_REVISION` |
| POST | `/booking-requests/{booking}/review` | Operator | `decision`: `approve`, `request_revision`, or `reject`; revision/rejection requires a note |
| POST | `/booking-requests/{booking}/quotation` | Operator | Create/send a quotation with carrier, amount, currency and expected schedule |
| POST | `/booking-requests/{booking}/quotation/accept` | Owner Customer | Accept the current unexpired quotation |
| POST | `/booking-requests/{booking}/quotation/decline` | Owner Customer | Decline the current quotation; operator can prepare a new version |
| POST | `/booking-requests/{booking}/payments` | Owner Customer | Create payment intent for accepted quotation; local/testing only until a real provider is configured |
| POST | `/booking-payments/{payment}/demo-confirm` | Owner Customer, local/testing only | Simulate successful payment; never processes real funds |
| POST | `/booking-requests/{booking}/convert` | Operator | Convert `READY_FOR_CONVERSION` booking into one shipment; duplicate conversion is idempotent |

Create booking JSON contains `origin_location_id`, `destination_location_id`, `cargo_type`, `package_count`, `total_weight`, `weight_unit`, `items[]`; optional fields include desired dates, description, total volume, volume unit and customer notes. Each item includes `item_name`, `quantity` and optional description/weight/volume fields.

Booking progression: `PENDING_REVIEW` → `NEEDS_REVISION` (if requested) → resubmitted as `PENDING_REVIEW`; or `APPROVED` → `QUOTED` → `QUOTE_ACCEPTED` → `PENDING_PAYMENT` → `READY_FOR_CONVERSION` → `CONVERTED`. Rejected/declined/cancelled states do not create shipments.

Quotation and payment are separate from shipment lifecycle. Shipment number `CT-...` is only generated during conversion, after approval, accepted quotation and paid payment status. The initial shipment lifecycle still begins at `CREATED`.

**Payment limitation:** the bundled payment checkout is a mock/demo adapter, enabled only when `APP_ENV` is `local` or `testing` and `CARGOTRACE_ALLOW_DEMO_PAYMENTS=true`. It is visibly marked DEMO and does not charge real money. Configure a real provider and verify signed server-to-server callbacks before any production transaction.


## Layer 3 — Transport, position tracking and external integrations

All authenticated Layer 3 endpoints use Sanctum. Admin manages transport assets and provider configurations; Operator manages trips, assignments and manual position reports; Customer reads only journeys attached to shipments they own.

| Method | Endpoint | Role | Purpose |
|---|---|---|---|
| GET | `/transport-assets` | Admin/Operator | List registered transport assets |
| POST | `/transport-assets` | Admin | Register vessel/truck/aircraft/train |
| PATCH | `/transport-assets/{asset}` | Admin | Update asset metadata/identifier |
| GET | `/transport-trips` | All | List trips; Customer results are ownership-scoped |
| POST | `/transport-trips` | Operator | Create one transport journey/leg |
| PATCH | `/transport-trips/{trip}` | Operator | Update schedule, asset and trip status |
| POST | `/transport-trips/{trip}/assignments` | Operator | Attach a shipment to a trip and leg order |
| PATCH | `/transport-assignments/{assignment}/unassign` | Operator | End an assignment |
| POST | `/transport-trips/{trip}/positions` | Operator | Add a manual position; response is explicitly `MANUAL / UNVERIFIED` |
| GET | `/transport-trips/{trip}/positions` | All, customer owner only | Read ordered position history |
| GET | `/customer/transport-journeys` | Customer | Read only transport journeys linked to owned shipments |
| GET | `/tracking-providers` | Admin | List provider configurations without secrets |
| POST | `/tracking-providers` | Admin | Configure AIS/GPS/carrier/port/customs provider |
| POST | `/integrations/tracking/{provider}/positions` | External provider | Receive provider webhook; requires `X-CargoTrace-Webhook-Secret` |

### Provider webhook payload — AIS/GPS position

```json
{
  "trip_number": "TRIP-261113-ABC123",
  "event_id": "provider-event-unique-id",
  "latitude": -6.1,
  "longitude": 106.8,
  "observed_at": "2026-11-13T08:30:00Z",
  "speed_knots": 12.4,
  "heading": 125.0
}
```

### Provider webhook payload — Carrier / Port / Customs event

```json
{
  "event_id": "provider-event-unique-id",
  "shipment_number": "CT-261113-ABC123",
  "event_type": "CUSTOMS_RELEASED",
  "description": "Customs release confirmed by the connected provider.",
  "observed_at": "2026-11-13T08:30:00Z",
  "latitude": -6.1,
  "longitude": 106.8
}
```

The webhook secret is hashed at rest and compared server-side. Provider credentials are encrypted using Laravel encryption. Duplicate provider event IDs are ignored. Provider payloads are retained for traceability. A provider must be active before it can submit events.

**Live-data boundary:** manual positions are always labelled `MANUAL / UNVERIFIED`. Provider positions are labelled `PROVIDER` and marked `STALE` when older than the provider's configured threshold. This integration provides a secure inbound webhook contract; a provider-specific polling driver, commercial AIS/GPS account, coverage and credentials are still required for a production live feed. No GPS device or global AIS service is bundled.

### Midtrans Snap payment gateway (optional Layer 3 extension)

Set `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, and `MIDTRANS_IS_PRODUCTION=false` in `.env` using credentials from the merchant's own Midtrans account. Keep the server key private. Configure Midtrans's notification URL to:

`https://your-domain.example/api/integrations/payments/midtrans/notification`

The server creates a Snap transaction through the configured environment and verifies the SHA-512 server-to-server notification signature before marking a payment as paid. Booking conversion remains operator-controlled and occurs only after payment confirmation. Without credentials, the local/testing demo checkout remains available only when `CARGOTRACE_ALLOW_DEMO_PAYMENTS=true`; production does not silently fall back to demo payment.

### Remaining advanced extensions

The current Laravel application remains a modular monolith. This keeps deployment and transaction boundaries consistent while isolating Transport, Booking, Payment and Tracking integration services. Queue-based provider polling, independently deployed microservices and a trained ML prediction model are not represented as completed features. They require deployment infrastructure, provider contracts/credentials, operational data and model validation before being enabled.
