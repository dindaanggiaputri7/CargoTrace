# CargoTrace
### Global Shipment Visibility & Tracking Platform

CargoTrace is a web-based shipment visibility and logistics management platform built with Laravel. It helps logistics operators manage shipment requests, bookings, quotations, transport trips, tracking activities, and delivery exceptions in one integrated application.

The platform provides customers with shipment progress visibility while giving logistics operators tools to manage operational workflows and monitor shipment activities.

<p align="center">
  <a href="https://cargotrace-eoch.ethiodeploy.app">Live Application</a> ·
  <a href="https://github.com/dindaanggiaputri7/CargoTrace">GitHub Repository</a>
</p>

---

## Table of Contents

- [Project Overview](#project-overview)
- [Problem Statement](#problem-statement)
- [Key Features](#key-features)
- [User Roles](#user-roles)
- [Shipment Workflow](#shipment-workflow)
- [Technology Stack](#technology-stack)
- [System Architecture](#system-architecture)
- [Installation and Setup](#installation-and-setup)
- [Environment Configuration](#environment-configuration)
- [Running the Application](#running-the-application)
- [Testing](#testing)
- [API Documentation](#api-documentation)
- [Deployment](#deployment)
- [Project Documentation](#project-documentation)
- [System Limitations](#system-limitations)
- [Future Improvements](#future-improvements)
- [Attribution](#attribution)

---

## Project Overview

Shipment information is often distributed across multiple operational processes, making it difficult for customers and logistics teams to monitor delivery progress. CargoTrace addresses this problem by providing a centralized platform for shipment management and tracking visibility.

CargoTrace is developed as a **Laravel modular monolith** with three integrated functional layers:

1. **Shipment Visibility** — shipment management, tracking events, timeline, ETA, and exceptions.
2. **Customer & Booking Management** — customer records, shipment requests, quotations, and payment processing.
3. **Transport & External Integration** — transport assets, trips, shipment assignments, position records, and provider integrations.

The application is designed as an academic software engineering project using demonstration data and sandbox integrations.

## Problem Statement

The project addresses several common logistics challenges:

- Shipment information is difficult to monitor when stored in separate systems.
- Customers may not have clear visibility into shipment status and journey history.
- Delays and operational exceptions require centralized monitoring.
- Logistics operators need a dashboard to identify shipments that require attention.
- Booking and shipment activities need a structured workflow with traceable records.

CargoTrace provides a centralized application to organize these activities and improve shipment visibility.

## Key Features

### 1. Shipment Management

- Create and manage shipment records.
- Store shipment details, cargo information, origin, and destination.
- Manage shipment status through defined lifecycle stages.
- Connect shipments with customer and transport records.

### 2. Tracking and Shipment Timeline

- Record shipment tracking events.
- Display shipment journey history.
- Store event timestamps and location information.
- Display shipment locations on a map.
- Distinguish recorded position data from verified live tracking.

### 3. ETA, Delay, and Exception Management

- Display estimated shipment arrival information.
- Apply deterministic ETA and delay rules.
- Monitor shipment exceptions.
- Manage exception investigation and resolution status.

### 4. Customer and Booking Management

- Allow customers to submit shipment requests.
- Allow operators to review, approve, reject, or request revisions.
- Create and manage shipment quotations.
- Convert eligible paid bookings into shipment records.
- Maintain customer records for portal and walk-in customers.

### 5. Payment Integration

- Optional Midtrans Snap checkout integration.
- Support for Midtrans Sandbox demonstrations.
- Verify payment notifications through server-to-server webhook signatures.
- Handle booking payment expiration.

Payment confirmation relies on verified gateway notifications, not only browser redirects. The sandbox integration is intended for testing and academic demonstration, not real financial transactions.

### 6. Transport Management

- Register transport assets such as vessels, trucks, aircraft, and trains.
- Manage transport trips and voyages.
- Assign shipments to transport trip legs.
- Record manual trip positions.
- Maintain tracking provider configurations and synchronization logs.

### 7. Dashboard and Operational Monitoring

- Display shipment portfolio metrics.
- Monitor shipment status.
- View in-transit and delivered shipment counts.
- Monitor delayed shipments and open exceptions.
- Display shipment information through an operational dashboard.

Dashboard figures are derived from application records.

### 8. Role-Based Access

CargoTrace provides role-based access for administrators, logistics operators, and customers. Access to shipment and operational information is restricted according to the user's role and ownership.

---

## User Roles

| Role | Main Responsibilities |
|---|---|
| Administrator | Manage users, system configuration, carriers, locations, and operational records. |
| Logistics Operator | Manage shipment requests, quotations, shipments, tracking events, exceptions, and transport operations. |
| Customer | Submit shipment requests and view owned shipment information, booking progress, and transport journeys. |

Authorization is enforced through application-side access controls. Customer access to shipment information is scoped to the customer's own records.

## Shipment Workflow

The main booking and shipment workflow is:

1. A customer submits a shipment request.
2. A logistics operator reviews the request.
3. The operator approves, rejects, or requests revisions.
4. The operator prepares a shipment quotation.
5. The customer accepts the quotation.
6. The customer completes payment through the configured payment gateway.
7. A verified payment notification updates the booking payment status.
8. The eligible booking can be converted into a shipment.
9. The shipment continues through transport and tracking activities.

The shipment lifecycle uses the following main status sequence:

`CREATED → BOOKED → PICKED_UP → IN_TRANSIT → DELIVERED`

Tracking events are stored as historical records so that shipment journey information can be reviewed over time.

---

## Technology Stack

| Category | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Database | MySQL |
| ORM | Laravel Eloquent |
| Frontend | Blade, JavaScript, CSS |
| API | Laravel REST API |
| API Authentication | Laravel Sanctum |
| Asset Build Tool | Vite |
| Payment Integration | Midtrans Sandbox (optional) |
| Testing | PHPUnit / Laravel Testing |
| Development Tools | Composer, Node.js, NPM, Git |
| Deployment | EthioDeploy |

---

## System Architecture

CargoTrace uses a modular monolithic architecture. The application is deployed as one Laravel application while separating responsibilities into functional modules and services.

The general request flow is:

```text
User / Browser
      |
      v
Laravel Routes & Middleware
      |
      v
Controllers
      |
      v
Business Logic / Services
      |
      v
Eloquent Models
      |
      v
MySQL Database
```

External integrations communicate with the application through dedicated API endpoints and Laravel's HTTP or webhook handling mechanisms.

The modular monolith approach keeps the application manageable while maintaining clear boundaries between shipment visibility, booking management, and transport integration.

---

## Installation and Setup

### Prerequisites

Make sure the development environment has:

- PHP 8.3 or later.
- Composer.
- MySQL 8 or compatible MySQL version.
- Node.js and NPM.
- Git.
- A local PHP development environment such as Laragon.

### 1. Clone the Repository

```bash
git clone https://github.com/dindaanggiaputri7/CargoTrace.git

cd CargoTrace
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Frontend Dependencies

```bash
npm install
```

### 4. Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

On Windows, you can also copy `.env.example` manually and rename the copy to `.env`.

Generate the application key:

```bash
php artisan key:generate
```

### 5. Configure the Database

Create a MySQL database for CargoTrace.

Update the database settings in the `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cargotrace
DB_USERNAME=root
DB_PASSWORD=
```

Use the database credentials configured in your local environment.

### 6. Run Database Migrations

```bash
php artisan migrate
```

If the project provides seeders for your environment, run them with:

```bash
php artisan db:seed
```

Use the project's documented demo data and avoid running destructive database reset commands against an existing database.

### 7. Build Frontend Assets

```bash
npm run build
```

---

## Environment Configuration

CargoTrace uses environment variables for database connections, application settings, and external service credentials.

Important configuration examples include:

| Variable | Purpose |
|---|---|
| `APP_URL` | Application base URL |
| `DB_DATABASE` | MySQL database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |
| `MIDTRANS_SERVER_KEY` | Midtrans server credential |
| `MIDTRANS_IS_PRODUCTION` | Select sandbox or production mode |
| `MIDTRANS_SIGNATURE_SECRET_KEY` | Verify payment notification signatures |

The exact variables required depend on the enabled integrations. Refer to [`docs/ENVIRONMENT_VARIABLES.md`](docs/ENVIRONMENT_VARIABLES.md) for additional configuration details.

**Security note:** Never commit the `.env` file, payment credentials, webhook secrets, or other private API keys to the repository.

---

## Running the Application

Start the Laravel development server:

```bash
php artisan serve
```

Start the frontend development server when needed:

```bash
npm run dev
```

Open the application in your browser:

```text
http://127.0.0.1:8000
```

For scheduled booking-payment expiration tasks during local development, run the Laravel scheduler in a separate terminal:

```bash
php artisan schedule:work
```

---

## Testing

CargoTrace includes automated tests for application functionality and business workflows.

Run the test suite using:

```bash
php artisan test
```

The test suite can be used to verify application behavior, including:

- Authentication and access control.
- Shipment and booking workflows.
- Shipment status and tracking operations.
- Payment notification handling.
- Validation and business rules.
- Transport and integration-related behavior.

For more detailed test scenarios, refer to:

[`docs/TESTING_MATRIX.md`](docs/TESTING_MATRIX.md)

---

## API Documentation

CargoTrace provides REST API endpoints for application operations and external integrations.

The API documentation includes endpoint descriptions, request formats, authentication requirements, and integration payload information.

- [API Reference](docs/API_REFERENCE.md)
- [Postman Collection](docs/postman/)

For API testing, use Postman and configure the required authentication token and environment values.

API requests should follow the application's validation and authorization rules.

---

## Deployment

The application is deployed through EthioDeploy.

**Live URL:**  
https://cargotrace-eoch.ethiodeploy.app

The deployment uses the `deploy/cargotrace-uas` Git branch as its production branch.

General deployment considerations:

1. Configure the production environment variables.
2. Configure the production database connection.
3. Install Composer dependencies.
4. Build frontend assets.
5. Run database migrations.
6. Configure the application document root and web server.
7. Configure the Laravel scheduler if scheduled tasks are enabled.
8. Verify application access and integration endpoints.

Payment and tracking provider credentials must be configured separately when those integrations are enabled.

---

## Project Documentation

Additional technical documentation is available in the repository:

| Document | Description |
|---|---|
| [`docs/API_REFERENCE.md`](docs/API_REFERENCE.md) | API endpoints and integration reference. |
| [`docs/ENVIRONMENT_VARIABLES.md`](docs/ENVIRONMENT_VARIABLES.md) | Environment variable configuration. |
| [`docs/TESTING_MATRIX.md`](docs/TESTING_MATRIX.md) | Test scenarios and verification matrix. |
| [`docs/FRONTEND_API_MAP.md`](docs/FRONTEND_API_MAP.md) | Frontend-to-API mapping. |
| [`docs/LAYER3_IMPLEMENTATION_NOTES.md`](docs/LAYER3_IMPLEMENTATION_NOTES.md) | Transport and integration implementation notes. |
| [`README_BACKEND_INSTALL.md`](README_BACKEND_INSTALL.md) | Backend installation guide. |
| [`README_FRONTEND_INSTALL_ID.md`](README_FRONTEND_INSTALL_ID.md) | Frontend installation guide. |
| [`README_LAYER2_INSTALL_ID.md`](README_LAYER2_INSTALL_ID.md) | Booking module installation guide. |

---

## System Limitations

The following limitations define the current project scope:

- CargoTrace does not provide direct integration with real ports, customs authorities, or commercial carrier systems by default.
- Manual trip positions are labeled `MANUAL / UNVERIFIED` and must not be interpreted as live GPS tracking.
- AIS/GPS and carrier webhook functionality requires an external provider account and a compatible data feed.
- Midtrans is configured for sandbox demonstration unless production credentials and configuration are deliberately provided.
- ETA and delay handling use deterministic application rules; a trained AI/ML prediction model is not included.
- Route lines on the map are intended for shipment visibility and do not represent turn-by-turn navigation.

## Future Improvements

Potential future improvements include:

- Additional verified carrier and tracking provider integrations.
- More comprehensive geocoding and weather service integrations.
- Enhanced shipment analytics and operational reports.
- More detailed notification preferences.
- Expanded automated test coverage and continuous integration.
- Improved monitoring for external service availability.

---

## Attribution

CargoTrace is built using open-source technologies and third-party libraries, including Laravel and its ecosystem.

- [Laravel](https://laravel.com/) — PHP application framework.
- [Laravel Sanctum](https://laravel.com/docs/sanctum) — API authentication.
- [Midtrans](https://midtrans.com/) — payment gateway integration.
- Other dependencies are listed in `composer.json` and `package.json`.

All third-party libraries, frameworks, and external services remain subject to their respective licenses and terms of use.

---

## Academic Project

**Course:** Web Programming 2  
**Project:** Final Semester Project (UAS)  
**Application:** CargoTrace — Global Shipment Visibility & Tracking Platform

The project demonstrates the development of a Laravel-based logistics application involving database management, business workflows, REST API development, external integrations, testing, and deployment.

---

<p align="center">
  <strong>CargoTrace</strong><br>
  Global Shipment Visibility & Tracking Platform
</p>
