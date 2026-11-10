# CargoTrace Backend Test Matrix

| SRS requirement | Implementation area | Included automated test / manual API check |
|---|---|---|
| FR-01 Authentication and authorization | `AuthController`, `EnsureRole`, `EnsureAccountIsActive`, Sanctum | Public registration is forced to CUSTOMER; authenticated role access is checked in feature tests |
| FR-02 User management | `AdminUserController` | Manual Postman checks for list/create/update/toggle-active; self-demotion and last-active-admin guards in controller |
| FR-03 Carrier management | `CarrierController` | Manual Postman CRUD checks; deletion is blocked when referenced by shipments |
| FR-04 Location management | `LocationController`, `GeocodingService` | Geocoding endpoint is restricted to Admin; invalid coordinates fail validation |
| FR-05 Shipment management | `ShipmentController` | Operator create and customer shipment ownership are covered by feature tests |
| FR-06 Shipment lifecycle | `ShipmentLifecycleService` | Invalid skipped transition and DELIVERED terminal state are covered by feature tests |
| FR-07 Tracking event | `TrackingService`, `TrackingEventController` | Append-only and backdated event rejection are covered by feature tests |
| FR-08 Timeline/map data | `ShipmentController@show`, `TrackingEventController@index` | Detail returns ordered events, saved coordinates and computed current location; manually verify in Postman |
| FR-09 ETA and delay | `EtaService`, `ShipmentController@updateEta` | Unit tests cover ON_TIME, exact 6h, >6h, exact 24h and >24h boundaries |
| FR-10 Exception management | `ExceptionService`, `ShipmentExceptionController` | Feature test covers OPEN → INVESTIGATING → RESOLVED and resolution-note requirement |
| FR-11 Map tracking | Stored origin/destination/checkpoint coordinates | Backend supplies saved coordinates; Leaflet/OSM rendering belongs to frontend and remains to be implemented |
| FR-12 External API | `GeocodingService` | Laravel HTTP Client with timeout/retry and controlled 503 fallback; live provider test should be run manually |
| FR-13 Dashboard | `DashboardController` | Dashboard response includes actual database KPIs, status/carrier chart series, recent, delayed and unresolved exceptions |
| FR-14 Search/filter/sort/pagination | `ShipmentController@index`, user/carrier/location/audit endpoints | Manual Postman checks for each filter combination and pagination are still required |
| FR-15 Notifications | `NotificationService`, `NotificationController` | Feature test verifies customer notifications on exception creation and status transitions |
| FR-16 Audit log | `AuditService`, `AuditLogController`, immutable `AuditLog` model | Feature tests verify shipment creation/archive audit entries; manual review of other actions is recommended |
| FR-17 REST API | `routes/api.php`, API controllers | Route list and Postman collection included; run all requests with each role before release |

## Test commands

```bash
php artisan test
php artisan route:list --path=api
```

## Important test boundary

The feature suite verifies core backend behavior, but it is not a substitute for end-to-end testing against the actual MySQL database and Postman. After installing this pack, run the automated suite locally and manually exercise every endpoint using the included Postman collection. Then add integration tests for any extra business behavior agreed with the lecturer.
