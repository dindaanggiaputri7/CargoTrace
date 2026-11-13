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

## Layer 2 Booking Workflow

| Requirement | Implementation | Automated coverage |
|---|---|---|
| Customer submits request | `BookingRequestController@store` | `BookingWorkflowTest::test_customer_can_submit_booking_request_and_operator_can_review_it` |
| Customer ownership | `BookingRequestController@show`, customer query scope | `BookingWorkflowTest::test_customer_cannot_view_another_customers_booking_request` |
| Operator review and quotation | `review`, `createQuotation`, `acceptQuotation` | Covered by the end-to-end booking workflow test |
| Payment demo gating | `createPaymentIntent`, `BookingPaymentController@demoConfirm` | Covered by end-to-end workflow test; live provider not implemented |
| Convert request to shipment | `convertToShipment` | Test checks shipment, initial tracking event, linked request, and idempotent retry |
| Regression | Existing Layer 1 endpoints/services | Run the full `php artisan test` suite after applying migrations |


## Layer 3 — Transport and integrations

| Requirement | Implementation | Validation |
|---|---|---|
| Transport asset management | `TransportController@storeAsset/updateAsset` | `TransportLayer3Test` and Admin-only API checks |
| Trip management | `TransportController@storeTrip/updateTrip` | `TransportLayer3Test` and Operator-only API checks |
| Shipment-trip assignment | `assignShipment/unassignShipment` | Verify unique leg order and terminal trip restrictions |
| Manual position | `addManualPosition` | Must be labelled `MANUAL / UNVERIFIED` |
| Provider position webhook | `webhook` | Secret verification, provider activation and idempotent event handling |
| Carrier/port/customs event webhook | `webhook` + `ExternalProviderEvent` | Verify append-only shipment tracking event and duplicate rejection |
| Customer journey privacy | `customerJourneys`, `authorizeTrip` | Customer A cannot read Customer B transport journey |
| Midtrans payment | `BookingRequestController@createPaymentIntent`, `BookingPaymentController@midtransNotification` | Requires merchant sandbox credentials; test signed callback and amount matching |
| AI/ML prediction | Not implemented | Requires historical labelled data and validated model |
| Microservices deployment | Not implemented | Current design is modular Laravel monolith; extraction requires infrastructure |

Run `php artisan test` after installing dependencies and applying migrations. Run provider tests only against sandbox credentials and a controlled webhook tunnel. Do not expose live credentials in screenshots, Git commits, or ZIP archives.
