# ADR-0002: Monorepo Structure

## Status

Принято

## Context

STAF contains a Laravel backend, a public Vue app, and an admin Vue app. The previous structure kept Laravel files at the repository root while frontend applications lived beside backend directories.

The project needs a clearer monorepo layout where applications are isolated and shared frontend resources are reusable without creating a broad catch-all package.

## Decision

Use this top-level structure:

```text
apps/
  api/      Laravel backend
  admin/    Vue admin app
  client/   Vue public app
packages/
  assets/    Shared fonts, images, and CSS
  contracts/ Shared TypeScript API contracts
  config/    Shared frontend config placeholder
docs/
```

Laravel lives in `apps/api` without changing PHP namespaces.

Frontend workspace packages are managed with pnpm workspaces:

```yaml
packages:
  - "apps/*"
  - "packages/*"
```

The shared assets package is the source of truth for frontend fonts, images, and shared CSS:

```text
packages/assets/
  package.json
  src/
    fonts/
    images/
    styles/
      index.css
      foundations/
      themes/
      components/
```

`apps/admin` and `apps/client` import shared styles from:

```ts
import '@staf/assets/styles/index.css'
```

The admin app imports the logo from:

```ts
import logoUrl from '@staf/assets/images/logo.svg'
```

Shared API DTOs used by frontend code live in `packages/contracts`.

## Consequences

Benefits:

- Laravel, admin, and client apps have clear ownership boundaries.
- Frontend apps share styles and assets through one focused package.
- API contracts can be shared without coupling frontend apps together.
- The repository root is mostly orchestration and documentation.

Costs:

- Tooling must run from the correct workspace or app directory.
- Laravel Sail commands now run from `apps/api`.
- `pnpm install` is required to generate workspace links and `pnpm-lock.yaml`.

## Verification

Expected checks:

```sh
pnpm install
pnpm --filter @staf/admin build
pnpm --filter @staf/client build
cd apps/api && php artisan route:list
cd apps/api && php artisan test
```

When using Sail:

```sh
cd apps/api
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan test
```
