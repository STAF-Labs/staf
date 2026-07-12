# STAF

STAF is organized as a monorepo.

## Structure

```text
apps/
  api/      Laravel backend
  admin/    Vue admin app
  client/   Vue public app
packages/
  assets/    Shared fonts, images, and styles
  contracts/ Shared TypeScript API contracts
  config/    Shared frontend config placeholder
```

## Frontend

Install workspace dependencies from the repository root:

```sh
pnpm install
```

Run or build apps:

```sh
pnpm --filter @staf/admin dev
pnpm --filter @staf/admin build
pnpm --filter @staf/client dev
pnpm --filter @staf/client build
```

## Backend

Laravel lives in `apps/api`.

```sh
cd apps/api
composer install
php artisan optimize:clear
php artisan route:list
php artisan test
```

For Sail:

```sh
cd apps/api
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
```
