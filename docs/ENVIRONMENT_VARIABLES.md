# CargoTrace Environment Variables

The project `.env` file must remain local and must never be committed to GitHub.

Existing Laravel/MySQL/Sanctum configuration remains in your local `.env`. Optional geocoding settings:

```dotenv
CARGOTRACE_GEOCODING_ENDPOINT=https://nominatim.openstreetmap.org
CARGOTRACE_GEOCODING_USER_AGENT="CargoTrace/1.0 (contact: your-email@example.com)"
```

The selected Nominatim endpoint does not require a secret API key. Use a clear User-Agent, identify the project contact in production, and comply with the provider's usage policy and rate limits. If a different geocoding provider requiring an API key is selected later, store that key only in `.env` and read it from `config/services.php`.

## Layer 2 optional configuration

```dotenv
# Midtrans Sandbox credentials; never place these in GitHub or a shared ZIP.
MIDTRANS_SERVER_KEY=your-sandbox-server-key
MIDTRANS_CLIENT_KEY=your-sandbox-client-key
MIDTRANS_IS_PRODUCTION=false

# Optional outbound email. Configure a real SMTP/Mail provider before setting true.
CARGOTRACE_EMAIL_NOTIFICATIONS=false
```

No mock payment confirmation exists. The application refuses to create a payment intent unless `MIDTRANS_SERVER_KEY` is configured. Only a verified Midtrans webhook can mark a payment as `PAID`. Run `php artisan schedule:work` locally to execute the 24-hour expiry job every minute.
