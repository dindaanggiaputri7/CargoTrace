# CargoTrace Layer 3 — Implementation Notes

## Integration with the existing project

Layer 3 is an additive extension to the existing CargoTrace Laravel project. Layer 1 shipment visibility and Layer 2 customer booking remain in the same repository, database, authentication system, UI shell and API.

## Five extensions discussed from the UAS scope slide

| Formerly outside mandatory scope | Layer 3 implementation status | Boundary |
|---|---|---|
| Real carrier, port and customs integrations | Inbound provider webhook contract for carrier/port/customs events | A live provider account, payload mapping and endpoint agreement are still required |
| Real financial payment | Optional Midtrans Snap transaction and signed notification flow | Merchant Server Key is required; test with sandbox before production |
| Physical-device real-time GPS | AIS/GPS provider webhook ingestion, position history, stale detection and 15-second customer-view refresh | No physical GPS device or AIS subscription is bundled; without provider data the system does not claim live location |
| AI/ML prediction | Not implemented as ML | Existing ETA/delay business rules remain deterministic. A validated model requires historical labelled data and evaluation |
| Complex microservices | Not split into independently deployed services | The project uses a modular Laravel monolith with domain boundaries; this preserves transaction consistency and can later be extracted if justified |

## Layer 3 domain modules

- Transport assets: vessels, trucks, aircraft and trains.
- Transport trips/voyages: carrier, mode, asset, origin, destination, schedules and status.
- Shipment-trip assignments: multiple legs and shipment-to-trip relationship.
- Trip positions: timestamp, coordinates, speed, heading, source and quality.
- Tracking providers: provider type, driver, endpoint, encrypted credential, hashed webhook secret and stale threshold.
- External provider events: idempotent carrier/port/customs events appended to the shipment timeline.
- Tracking sync logs: success/failure and record counts for provider submissions.
- Customer transport journey: role-scoped read access to trips and positions belonging to the customer's shipments.

## Data integrity and security

- Shipment lifecycle remains controlled by the existing ShipmentLifecycleService. External events do not silently change shipment status.
- Customer endpoints filter through shipment ownership.
- Manual positions are labelled `MANUAL / UNVERIFIED`.
- Provider positions are labelled `PROVIDER`; stale reports are marked `STALE`.
- Provider event IDs are unique per provider to prevent duplicate processing.
- Provider credentials are encrypted with Laravel Crypt.
- Webhook shared secrets are hashed and checked server-side.
- Midtrans notifications require a valid SHA-512 signature and matching amount before payment is marked paid.
- Payment demo confirmation remains local/testing only.

## Setup checklist

1. Use a PHP version supported by the current Composer lockfile and Laravel framework. The supplied project currently requires PHP 8.3+.
2. Install Composer dependencies.
3. Copy `.env.example` to `.env`, generate an app key and configure MySQL.
4. Run `php artisan migrate --seed`.
5. Run `php artisan test`.
6. Configure an AIS/GPS/carrier/port/customs provider only after obtaining its official API documentation and credentials.
7. Configure Midtrans sandbox credentials before testing real payment gateway callbacks.
8. Never commit `.env`, API keys, webhook secrets or production payment credentials.

## Known limitations

- Provider polling is not yet implemented as a provider-specific scheduled driver; this release supports inbound webhooks.
- The provider's external event schema must be mapped to the documented CargoTrace webhook contract.
- GPS/AIS coverage, freshness and accuracy depend on the provider and identifier mapping (IMO/MMSI/device ID).
- No ML model is included.
- Independent microservice deployment is not included.
- Full end-to-end verification must be run in the user's PHP/MySQL environment after dependencies are installed.
