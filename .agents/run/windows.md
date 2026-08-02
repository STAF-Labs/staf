# Running STAF locally — Windows

Canonical, crutch-free startup for Windows (Docker Desktop + PowerShell). Backend
runs **entirely through Laravel Sail** (Docker); frontends run on the host via Vite.
No local PHP and no `DB_HOST` overrides — the `.env` stays canonical (`DB_HOST=pgsql`).

All commands are run from the repo root unless noted.

## Stack & ports

| Service | URL | Notes |
|---|---|---|
| API (`laravel.test`) | http://localhost | port **80** |
| client | http://localhost:5173 | `FRONTEND_URL` |
| admin | http://localhost:**5174** | `ADMIN_PANEL_URL` |
| pgsql | localhost:5433 → 5432 | |
| redis | localhost:6379 | |
| meilisearch | localhost:7701 | |
| mailpit (web) | http://localhost:8026 | |
| soketi (ws) | localhost:6001 | |

Seeded admin (`AdminUserSeeder`): **`admin@staf.ru` / `password`** (role `admin`).

## Prerequisites
- Docker Desktop running.
- `pnpm` + Node 24 on PATH.
- Local PHP/Composer **not** required — Composer runs in a container.

## Steps

**1. Frontend deps**
```powershell
pnpm install
```

**2. Backend `.env`** — `apps/api/.env` must exist with the canonical Sail values.
Copy from `.env.example` if missing and make sure these are set:
```
APP_KEY=base64:...            # must be set; do NOT re-run key:generate later
DB_CONNECTION=pgsql
DB_HOST=pgsql                 # internal Sail hostname, NOT 127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
FRONTEND_URL=http://localhost:5173
ADMIN_PANEL_URL=http://localhost:5174
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,localhost:5174,127.0.0.1,127.0.0.1:5173,127.0.0.1:5174
```

**3. PHP deps (vendor/) via container** — required before the Sail image build
(build context is `vendor/laravel/sail/runtimes/8.5`).
```powershell
cd apps/api
docker run --rm -v "${PWD}:/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install --no-interaction --prefer-dist
```

**4. Build image & bring up the stack**
```powershell
cd apps/api
$env:WWWGROUP=1000; $env:WWWUSER=1000
docker compose build
docker compose up -d
docker compose ps
```

**5. Migrate + seed (inside the container)**
```powershell
docker compose exec laravel.test php artisan migrate:fresh --seed
```

**6. Frontends on the host** — Vite binds IPv6-only by default here, so pass
`--host 127.0.0.1`; admin is pinned to **5174**.
```powershell
# terminal 1 — client
pnpm --filter @staf/client exec vite --host 127.0.0.1 --port 5173 --strictPort
# terminal 2 — admin (MUST be 5174)
pnpm --filter @staf/admin  exec vite --host 127.0.0.1 --port 5174 --strictPort
```

**7. Verify**
```powershell
curl.exe -s -o NUL -w "api    -> %{http_code}`n" http://localhost/sanctum/csrf-cookie   # 204
curl.exe -s -o NUL -w "client -> %{http_code}`n" http://localhost:5173/                  # 200
curl.exe -s -o NUL -w "admin  -> %{http_code}`n" http://localhost:5174/                  # 200
```
Then open http://localhost:5174 and sign in with `admin@staf.ru` / `password`.

## Gotchas

1. **Admin must run on :5174, or login fails.** `config/cors.php` allows
   origins `FRONTEND_URL` (5173) and `ADMIN_PANEL_URL` (5174). Always pass
   `--port 5174 --strictPort`, or use the pinned config in `apps/admin/vite.config.ts`:
   `server: { host: '127.0.0.1', port: 5174, strictPort: true }`.

2. **Vite binds IPv6-only** on this setup — without `--host 127.0.0.1` the socket
   listens on `::1` and `localhost`/`127.0.0.1` are unreachable. Same `server.host`
   config fixes it permanently.

3. **`docker compose build` can fail at `npm install -g npm pnpm bun`** with
   `ECONNRESET` to `registry.npmjs.org` (flaky network; apt/PPA are fine). Usually a
   plain **retry** of `docker compose build` gets through. If it keeps failing, add
   before that line in `vendor/laravel/sail/runtimes/8.5/Dockerfile`:
   ```dockerfile
       && npm config set registry https://registry.npmmirror.com \
       && npm config set fetch-retries 8 \
       && npm config set fetch-retry-maxtimeout 120000 \
   ```
   (a `vendor/` edit — `composer update` will overwrite it).

4. **APP_KEY is already set** in the canonical `.env`; do **not** run
   `php artisan key:generate` afterwards or existing encrypted sessions break.

5. Redis/Meilisearch/Soketi aren't actually used by the app right now (no
   `laravel/scout`; cache/session/queue on `database`; broadcast `log`). Only
   **pgsql** is required, but full Sail starts them all via `depends_on` — that's fine.

## Daily commands
```powershell
cd apps/api
docker compose up -d                                 # start backend (image cached → instant)
docker compose exec laravel.test php artisan <cmd>   # artisan inside Sail
docker compose logs -f laravel.test                  # API logs
docker compose down                                  # stop backend (pgsql volume persists)
```
Frontends: the two `vite` commands from step 6.
