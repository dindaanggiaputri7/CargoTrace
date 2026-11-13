# CargoTrace Layer 2 — v2.1.0

## Added
- Customer booking request form and My Requests page.
- Operator booking queue, request review, revision request, approval and rejection.
- Quotation preparation, customer acceptance/decline, and quotation refresh.
- Payment intent and explicitly labelled local demo checkout.
- Operator conversion from paid/approved booking into a Layer 1 shipment.
- Booking status history, in-app notifications, audit entries, optional email delivery.
- Feature tests and Postman requests for the Layer 2 workflow.

## Preserved
- Existing three roles and Layer 1 shipment lifecycle.
- Existing blue CargoTrace UI identity, sidebar, dashboards, maps, and shipment functions.
- Existing MySQL/Laravel setup; changes are delivered as additive migration.

## Not included
- Real payment gateway or financial transaction processing.
- Vessel/voyage management, AIS/GPS feed, or Layer 3 transport assignment.

## Safety
- The demo payment confirmation is blocked outside `local` and `testing` environments.
- The package excludes `.env`, `.git`, `vendor`, `node_modules`, local database files and logs.


## Layer 3 additive update

- Added transport asset, trip/voyage, shipment-trip assignment, trip position, provider configuration, external provider event and sync log persistence.
- Added role-scoped Layer 3 API routes and Customer transport journey endpoint.
- Added manual position reporting and secure provider webhook ingestion with hashed shared secret and idempotent event IDs.
- Added carrier/port/customs external event intake that appends to the shipment timeline without bypassing shipment lifecycle validation.
- Added optional Midtrans Snap transaction creation and signed server-to-server notification verification. Demo payments remain restricted to local/testing.
- Added Admin, Operator and Customer Transport & Tracking frontend navigation.
- Preserved existing Layer 1 and Layer 2 code paths; all changes are additive.
- AI/ML model training and independent microservices deployment remain future work and are documented as such.
