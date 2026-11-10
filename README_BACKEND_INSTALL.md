# CargoTrace Backend Final Pack (v2.0)

This is an **overlay/upgrade pack** for the existing CargoTrace Laravel project. It is not a new Laravel installation and does not contain `.env`, `vendor`, `node_modules`, local database files, or Git metadata.

It is aligned with `SRS_CargoTrace_v1.0_Review` and `CargoTrace_Design_Blueprint_v1.0`:

- Three roles: `admin`, `operator`, `customer`.
- Nine required business tables, plus Laravel infrastructure tables and Sanctum tokens.
- Shipment lifecycle: `CREATED → BOOKED → PICKED_UP → IN_TRANSIT → DELIVERED`.
- Append-only tracking timeline, persisted map coordinates, ETA/delay calculation, exception handling, in-app notifications, and audit logs.
- Server-side search/filter/sorting/pagination and role-aware shipment scope.
- OpenStreetMap Nominatim geocoding using Laravel HTTP Client; no API key is required for the selected public service.

## 1. Before installing

1. Stop `php artisan serve` with `Ctrl+C` if it is running. Keep Laragon Apache/MySQL available.
2. Make a copy of the current project folder or commit your current work to Git first.
3. Extract this ZIP. It contains folders such as `app`, `bootstrap`, `config`, `database`, `routes`, `tests`, and `docs`.
4. Copy/merge those folders into `D:\laragon\www\cargotrace`.
5. When Windows asks to merge folders, choose **Merge**. When asked to replace files, replace matching backend files with this pack's files. Do not replace your `.env`, `.git`, `vendor`, `node_modules`, or `public` folder.

## 2. Database upgrade (additive only)

This pack adds one new migration:

`database/migrations/2026_11_10_130001_expand_cargotrace_backend_schema.php`

It adds:

- carrier contact name and transport mode;
- location region and timezone;
- shipment cargo type, expected departure, volume fields, in-transit timestamp, and `deleted_at` for soft archive;
- exception `resolved_by` relation.

It does **not** rewrite the migrations already run on your MySQL database. Do not run `migrate:fresh` and do not delete the database.

From the project root, run:

```bash
php artisan optimize:clear
php artisan migrate
```

## 3. Seed demo data

The seeder is idempotent for its known demo identifiers. It keeps the original three demo roles and adds two synthetic customer accounts, sample carriers/locations, twelve synthetic shipments, tracking events, and three exceptions.

```bash
php artisan db:seed --class=CargoTraceDemoSeeder
```

All demo accounts use this password for local demonstration only:

`CargoTraceDemo!2026`

| Role | Email |
|---|---|
| Administrator | `admin@cargotrace.test` |
| Logistics Operator | `operator@cargotrace.test` |
| Customer Aster Retail | `customer@cargotrace.test` |
| Customer Northstar Labs | `northstar@cargotrace.test` |
| Customer Vertex Motors | `vertex@cargotrace.test` |

Change demo passwords before any public deployment. Never use these accounts or passwords in production.

## 4. Geocoding configuration

The selected service is OpenStreetMap Nominatim. It does not require an API key. Add/update these optional values in `.env`:

```dotenv
CARGOTRACE_GEOCODING_ENDPOINT=https://nominatim.openstreetmap.org
CARGOTRACE_GEOCODING_USER_AGENT="CargoTrace/1.0 (your contact email)"
```

Use a descriptive User-Agent and observe Nominatim's published usage policy/rate limits. The API returns a controlled `503` response when the provider is unavailable; users can still enter coordinates manually.

## 5. Run tests and inspect routes

```bash
php artisan test
php artisan route:list --path=api
```

The pack includes feature tests for public registration role restriction, customer data isolation, operator shipment creation, lifecycle validation, chronological tracking, exception progression/notifications, soft archiving, and ETA boundaries.

Tests use the SQLite in-memory connection configured in `phpunit.xml`; your development `.env` remains configured for MySQL.

## 6. Run locally

```bash
php artisan serve
```

Open `http://127.0.0.1:8000`. The web UI has not been replaced by this backend pack; the existing Laravel welcome page remains until the frontend phase.

## 7. API authentication

1. `POST /api/login` with `email` and `password`.
2. Keep the returned token private.
3. Send `Authorization: Bearer <token>` and `Accept: application/json` to protected endpoints.
4. `POST /api/logout` revokes the current token.

Public `/api/register` always creates a `customer`; submitted `role` values are ignored. Admin/operator accounts must be created by an administrator.

## 8. Important business/security decisions

- Admin manages users, carriers, locations, dashboard overview, and read-only audit logs. Admin does not update operational shipment status through the standard API.
- Operator creates/updates shipment, changes lifecycle, adds location tracking, updates ETA, and manages exceptions.
- Customer can only list/open their own shipments and their own notifications. Access to another customer's shipment returns `404`.
- `DELIVERED` is terminal. Lifecycle transitions are sequential only.
- Status changes create a new tracking event in the same database transaction. Location-only tracking events inherit the current shipment status.
- Tracking events and audit logs are immutable through Eloquent model events and are not exposed with update/delete endpoints.
- Shipment delete is implemented as a soft archive for CREATED shipments. Tracking, exceptions, and audit history remain in the database.
- Delay is independent from exception. An ETA delay does not auto-create an exception.
- ETA boundaries: `<= 0h ON_TIME`, `>0–6h LOW`, `>6–24h MEDIUM`, `>24h HIGH`; exactly `+24h` is `MEDIUM`.

## 9. Files included

- `app/Models`: relationships, casts, append-only safeguards, and soft-delete shipment model.
- `app/Services`: audit, ETA, exception, geocoding, notification, lifecycle, and tracking business logic.
- `app/Http/Controllers/Api`: REST API controllers with validation, role scopes, pagination, and audit/notification triggers.
- `app/Http/Middleware`: active-account and role middleware.
- `routes/api.php`: API route definitions and role boundaries.
- `database/migrations`: one additive schema migration.
- `database/seeders`: synthetic demo dataset.
- `tests`: PHPUnit feature and ETA unit tests.
- `docs`: API notes and Postman collection.

## 10. Known scope boundaries

This backend does not provide physical live GPS, direct integrations with carriers/ports/customs, payments, AI/ML, microservices, or email notifications. The map is based on saved origin/destination/checkpoint coordinates and is intended to be rendered in the frontend with Leaflet + OpenStreetMap.
