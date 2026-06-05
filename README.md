# Volgjeraad.nl

Open-source platform dat gemeenteraadsvergaderingen samenvat en als nieuwsbrief verstuurt. Begint met de gemeente Brummen (ORI-index `ori_brummen`).

## Stack

- **Backend**: PHP 8.3+, Laravel 13, MySQL
- **Frontend**: Inertia.js 3 + React 19 + TypeScript + Tailwind CSS v4 + shadcn/ui
- **Wachtrij**: Laravel Queue (database driver)
- **Mail**: Lettermint
- **AI**: laravel/ai (OpenAI, gpt-4o-mini default)
- **ORI-data**: Open Raadsinformatie API v1/elastic
- **Tests**: Pest 4
- **Licentie**: EUPL-1.2

## Lokale setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Vul minimaal de volgende variabelen in `.env` in:

```
DB_DATABASE=volgjeraad
DB_USERNAME=root
DB_PASSWORD=
OPENAI_API_KEY=
LETTERMINT_PROJECT_TOKEN=
VOLGJERAAD_LAUNCH_DATE=2024-01-01
```

## Testcommando's

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

## Omgevingsvariabelen (zonder echte waarden)

| Variabele | Omschrijving |
|-----------|-------------|
| `DB_CONNECTION` | `mysql` |
| `DB_DATABASE` | Naam van de MySQL-database |
| `OPENAI_API_KEY` | OpenAI API-sleutel |
| `AI_SUMMARY_MODEL` | Overschrijf het default AI-model (default: `gpt-4o-mini`) |
| `AI_EVAL_MODEL` | AI-model voor evaluaties |
| `LETTERMINT_PROJECT_TOKEN` | Lettermint project-token voor mailing |
| `MAIL_FROM_ADDRESS` | Afzenderadres |
| `MAIL_FROM_NAME` | Afzendernaam |
| `VOLGJERAAD_LAUNCH_DATE` | Lanceerdatum (Y-m-d); bepaalt backfill-window |
| `ORI_BASE_URL` | ORI Elastic base URL |
| `ADMIN_EMAIL` | E-mailadres initiële beheerdersaccount |
| `ADMIN_PASSWORD` | Wachtwoord initiële beheerdersaccount (alleen lokaal) |

## Licentie

Dit project is beschikbaar onder de [EUPL-1.2](LICENSE).
