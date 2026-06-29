# StoreTrack

StoreTrack is a multi-store inventory and accounting platform for small retail/wholesale
businesses. A single account can own one or more **businesses**, each with multiple
**stores**, and manage the full operational cycle: products and stock, purchase and sale
invoices, supplier/customer debt, payments, and financial reports.

## Live Application

https://storetrack.io.vn

### Demo Account

**Email:** test@example.com<br>
**Password:** Demo@1234
> *The demo account is publicly available. Data may be modified or reset periodically.*

## Features

- **Catalog** — products, categories, units, tags, and per-store stock levels.
- **Parties** — suppliers and customers, scoped to the stores they belong to.
- **Invoices** — purchase and sale invoices with **FIFO cost tracking** and
  **per-store taxes**, so profit is computed from the exact batch slices sold.
- **Debt & payments** — accounts-receivable / accounts-payable ledger with
  user-chosen per-invoice payment allocation and derived partial/paid status.
- **Reports** — stock, sales, and profit reports, including an owner-only
  consolidated view across all stores in a business.
- **Import / export** — async Excel/CSV import (with an imports history) and export
  to Excel as well as formal PDF documents (ZIP of per-invoice PDFs).
- **AI invoice scan** — optional invoice extraction from photos/PDFs via Google Gemini.
- **Banking** — banks and bank accounts linked to parties and invoices.
- **Audit log** — every create/update/delete is recorded.
- **Bilingual UI** — Vietnamese (default) and English, including localized PDF exports.
- **Backups** — scheduled database + file backups (see [BACKUPS.md](BACKUPS.md)).

## Tech stack

| Layer      | Technology |
| ---------- | ---------- |
| Backend    | PHP 8.2, [Laravel 12](https://laravel.com/), [Lighthouse](https://lighthouse-php.com/) (GraphQL), Sanctum (auth), [Horizon](https://laravel.com/docs/horizon) (queues) |
| Frontend   | [Vue 3](https://vuejs.org/) + TypeScript, Vite, Pinia, Vue Router, vue-i18n |
| Data       | MySQL 8, Redis (queue + cache) |
| Tooling    | Docker / Docker Compose, maatwebsite/excel, dompdf + headless Chromium (Browsershot) for PDFs, spatie/laravel-backup |

## Architecture

The repo is a two-app monorepo wired together by Docker Compose:

- **`backend/`** — Laravel API exposing a GraphQL endpoint at `/graphql`. Background work
  (exports, imports, PDF rendering, backups) runs on Redis-backed queues via Horizon.
  Data access follows a repository pattern; business logic lives in services.
- **`frontend/`** — Vue 3 single-page app that talks to the backend over GraphQL.
- **Infra** — MySQL for storage, Redis for queues/cache, nginx (dev) or Caddy (prod)
  as the web server, plus dedicated `queue` (Horizon) and `scheduler` containers.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose v2 (`docker compose`)

That's all you need for local development — PHP, Node, MySQL, and Redis all run inside
containers. (If you prefer running the apps directly on your host, you'll need PHP 8.2+,
Composer, Node 20+, MySQL 8, and Redis instead.)

## Local setup

### 1. Clone

```bash
git clone git@github.com:Nhi21pham/storetrack.git
cd storetrack
```

### 2. Configure environment

Create both env files from their examples (neither is committed — they hold credentials):

```bash
cp .env.example .env                  # MySQL credentials used by Docker Compose
cp backend/.env.example backend/.env  # Laravel app config
```

Then edit `backend/.env` so it points at the Docker services instead of SQLite. Set:

```env
DB_CONNECTION=mysql
DB_HOST=mysql_db
DB_PORT=3306
DB_DATABASE=storetrack
DB_USERNAME=user
DB_PASSWORD=password

REDIS_HOST=redis_cache
QUEUE_CONNECTION=redis
```

> The hostnames `mysql_db` and `redis_cache` are the Compose container names — leave them
> as-is. The `DB_*` values must match what you set in the root `.env`; use strong, unique
> values for any real deployment.

Optional: to enable AI invoice scanning, add your own Google Gemini key:

```env
GEMINI_API_KEY=your-key-here
```

### 3. Build and start the stack

```bash
docker compose up -d --build
```

This starts the backend (php-fpm), nginx, MySQL, Redis, the Vite dev server, the Horizon
queue worker, and the scheduler.

### 4. Initialize the application

```bash
# Generate the Laravel app key
docker compose exec app php artisan key:generate

# Run migrations and seed reference data (banks, provinces, units, categories, taxes)
docker compose exec app php artisan migrate --seed
```

### 5. Open the app

| Service              | URL |
| -------------------- | --- |
| Frontend (Vite dev)  | http://localhost:5173 |
| Backend / nginx      | http://localhost |
| GraphQL endpoint     | http://localhost/graphql |
| GraphiQL explorer    | http://localhost/graphiql |
| Horizon dashboard    | http://localhost/horizon |

Create your first account through the **Register** screen in the frontend, then sign in.

## Common commands

```bash
# Tail logs / check status
docker compose ps
docker compose logs -f app

# Open a shell or Tinker in the backend container
docker compose exec app bash
docker compose exec app php artisan tinker

# Run a fresh migration with seeders
docker compose exec app php artisan migrate:fresh --seed

# Restart the queue worker after changing queued-job code
docker compose restart queue

# Stop everything
docker compose down
```

## Testing

```bash
# Backend (PHPUnit)
docker compose exec app php artisan test

# Frontend unit tests (Vitest) and e2e (Playwright)
cd frontend
npm run test:unit
npm run test:e2e
```

## Production

A production stack is provided via [docker-compose.prod.yml](docker-compose.prod.yml),
which replaces nginx + the Vite dev server with [Caddy](Caddyfile). Caddy serves the
built Vue SPA from `frontend/dist` and reverse-proxies `/api`, `/graphql`, and `/storage`
to Laravel, with automatic HTTPS.

```bash
# Build the frontend
cd frontend && npm install && npm run build && cd ..

# Launch the production stack
docker compose -f docker-compose.prod.yml up -d --build
```

See [BACKUPS.md](BACKUPS.md) for the backup and restore procedure.

## Project structure

```
storetrack/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── GraphQL/         # Lighthouse resolvers
│   │   ├── Services/        # Business logic
│   │   ├── Repositories/    # Data access (Eloquent)
│   │   ├── Models/          # Eloquent models
│   │   ├── Imports/ Exports/# Excel import & export
│   │   └── Jobs/            # Queued work (exports, PDFs, backups)
│   ├── graphql/             # GraphQL schema (schema.graphql + partials)
│   └── database/            # Migrations & seeders
├── frontend/                # Vue 3 SPA
│   └── src/
│       ├── features/        # Per-feature UI (invoices, products, reports, …)
│       ├── views/ layouts/ components/
│       └── stores/ router/ i18n/
├── nginx/                   # Dev web-server config
├── docker-compose.yml       # Local development stack
├── docker-compose.prod.yml  # Production stack (Caddy + HTTPS)
├── Caddyfile                # Production reverse proxy
└── Dockerfile               # Backend image (php-fpm + Chromium + tooling)
```
