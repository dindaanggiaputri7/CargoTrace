<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Layer 2 — Customer & Booking Management

CargoTrace includes an additive Layer 2 booking workflow. Customers can submit shipment requests; operators can review, request revisions, approve/reject, issue quotations, and convert a paid booking into a shipment. The workflow reuses the existing shipment lifecycle, tracking events, notifications and audit logging.

See `README_LAYER2_INSTALL_ID.md` for local installation and the full test scenario. The included checkout is a **local demo simulation only** and does not process real payments. A real payment provider and its verified webhook flow must be configured separately before production use.


## Layer 3 — Enterprise Transport & Integrations

Layer 3 is added on top of the existing Layer 1 shipment visibility and Layer 2 customer booking modules. It does not replace the current Laravel application or rebuild the earlier layers.

### Implemented in this release
- Transport asset registry for vessels, trucks, aircraft and trains.
- Transport trip/voyage management and shipment-to-trip leg assignments.
- Manual trip position capture, explicitly labelled `MANUAL / UNVERIFIED`.
- Provider configuration with encrypted API credentials and hashed webhook secrets.
- Inbound webhook support for AIS/GPS positions and carrier/port/customs shipment events.
- Provider event idempotency and tracking synchronization logs.
- Customer transport journey view scoped to shipments owned by the customer.
- Optional Midtrans Snap checkout when merchant credentials are configured, with signed notification verification.

### Configure Midtrans sandbox (optional)
1. Create a merchant account and obtain sandbox Server Key from Midtrans.
2. Set `MIDTRANS_SERVER_KEY` in `.env`; set `MIDTRANS_IS_PRODUCTION=false`.
3. Set the notification URL in Midtrans to `https://your-domain.example/api/integrations/payments/midtrans/notification`.
4. Test using sandbox methods before enabling production credentials.
5. Never commit `.env` or expose the Server Key.

Without Midtrans credentials, the existing demo checkout remains restricted to local/testing and must not be represented as a real payment.

### Configure tracking provider
1. In Admin > Transport & Tracking, register a provider and choose AIS, GPS, Carrier, Port or Customs.
2. Provide a unique webhook secret (at least 24 characters) and activate the provider.
3. Configure the provider's webhook destination as `/api/integrations/tracking/{provider-id}/positions`.
4. Send `X-CargoTrace-Webhook-Secret` and the provider payload described in `docs/API_REFERENCE.md`.
5. Use unique provider event IDs. Duplicate events are ignored.

The webhook is an integration contract, not a bundled AIS/GPS data subscription. A production feed still requires a provider account, supported API agreement, vessel/device identifiers and provider-specific mapping. The application must label old reports as stale and must not animate fabricated positions as live.

### Architecture and remaining extensions
CargoTrace remains a Laravel modular monolith with separated domain services and API boundaries. This is intentional to preserve transaction integrity and keep the project deployable as one application. Independently deployed microservices are not introduced in this release.

A trained AI/ML ETA model is not included. A valid model requires historical shipment/trip outcomes, a training/evaluation process and drift monitoring. Existing deterministic ETA/delay rules remain the authoritative baseline until a model is validated.

### Layer 3 tests
```bash
php artisan migrate
php artisan test
php artisan route:list --path=api
```
