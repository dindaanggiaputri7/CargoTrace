# CargoTrace Environment Variables

The project `.env` file must remain local and must never be committed to GitHub.

Existing Laravel/MySQL/Sanctum configuration remains in your local `.env`. Optional geocoding settings:

```dotenv
CARGOTRACE_GEOCODING_ENDPOINT=https://nominatim.openstreetmap.org
CARGOTRACE_GEOCODING_USER_AGENT="CargoTrace/1.0 (contact: your-email@example.com)"
```

The selected Nominatim endpoint does not require a secret API key. Use a clear User-Agent, identify the project contact in production, and comply with the provider's usage policy and rate limits. If a different geocoding provider requiring an API key is selected later, store that key only in `.env` and read it from `config/services.php`.
