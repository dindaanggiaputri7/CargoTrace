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
# Local/testing only; backend additionally blocks mock confirmation outside local/testing.
CARGOTRACE_ALLOW_DEMO_PAYMENTS=true

# Optional outbound email. Configure a real SMTP/Mail provider before setting true.
CARGOTRACE_EMAIL_NOTIFICATIONS=false
```

The mock checkout is for workflow testing only. It is not a payment gateway integration and must not be used to collect real customer funds.
