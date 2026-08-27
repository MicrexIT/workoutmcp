# Laravel Cloud Deployment Runbook

Workout Memory MCP is deployed on Laravel Cloud. There is no self-managed production server, remote shell deploy, service unit, or server-local database to manage in this repository.

## Production Overview

- Public app URL: `https://workoutmcp.com`.
- Runtime: Laravel Cloud PHP runtime, PHP 8.5.
- Source repository: `MicrexIT/workoutmcp`.
- Production deploys: Laravel Cloud deploys from the connected Git repository.
- GitHub Actions: `.github/workflows/ci.yml` only validates build and tests; it does not deploy.
- DNS / edge: Cloudflare may own DNS for `workoutmcp.com`, but DNS must point to the Laravel Cloud custom-domain target shown in the Laravel Cloud dashboard, not to a retired hosting target.

## Laravel Cloud Application

In Laravel Cloud:

1. Create or open the `workoutmcp` application from the `MicrexIT/workoutmcp` repository.
2. Select the PHP runtime and PHP `8.5`.
3. Configure the production environment to deploy from `main`.
4. Attach the production database resource used by the app. Prefer a Laravel Cloud managed Postgres database for production.
5. Configure a queue option:
   - For the current small app, database-backed queues are acceptable.
   - For higher traffic, use Laravel Cloud managed queues or a worker cluster.
6. Add `workoutmcp.com` as the custom domain and follow Laravel Cloud's DNS instructions.

## Build And Deploy Commands

Use Laravel Cloud environment settings for these commands.

Build command:

```shell
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader && npm ci && npm run build
```

Deploy command:

```shell
php artisan migrate --force && php artisan db:seed --force
```

Do not add these commands to Laravel Cloud deploy commands:

- `php artisan queue:restart` - Laravel Cloud restarts managed workers during deploys.
- `php artisan horizon:terminate` - Horizon is managed by Laravel Cloud when used.
- `php artisan optimize:clear` - this app stores OAuth state in cache, and clearing cache can force users to reconnect.
- `php artisan storage:link` - deploy-command filesystem changes do not persist on Laravel Cloud.

## Environment Variables

Set production environment variables in Laravel Cloud, not in committed files.

Required app variables:

```dotenv
APP_NAME="Workout Memory MCP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://workoutmcp.com

WORKOUT_MEMORY_PUBLIC_URL=https://workoutmcp.com
WORKOUT_MEMORY_USER_NAME=Michele
WORKOUT_MEMORY_USER_EMAIL=michele@example.com
WORKOUT_MEMORY_TIMEZONE=Europe/Paris
WORKOUT_MEMORY_WEIGHT_UNIT=kg
WORKOUT_MEMORY_DISTANCE_UNIT=m
WORKOUT_MEMORY_REGISTRATION_ENABLED=false

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=resend
MAIL_FROM_ADDRESS=support@workoutmcp.com
MAIL_FROM_NAME="Workout Memory MCP"
```

Secrets and generated values:

- `APP_KEY` - generate with `php artisan key:generate --show`.
- `MCP_PRIVATE_TOKEN` - only needed for any private-token MCP clients; OAuth clients do not use it.
- `RESEND_API_KEY` - required for production email when `MAIL_MAILER=resend` (`RESEND_KEY` remains supported as a legacy alias).

Database variables are injected automatically when a Laravel Cloud database resource is attached. Do not hardcode retired server-local database paths.

## GitHub Actions

The repository keeps a CI workflow at `.github/workflows/ci.yml`.

The CI workflow:

1. Checks out the repository.
2. Installs PHP 8.5 dependencies.
3. Installs Node 24 dependencies.
4. Builds Vite assets.
5. Runs `php artisan test --compact`.

The CI workflow intentionally does not:

- Read legacy hosting secrets.
- Start a remote-deploy SSH agent.
- Run remote shell deploy steps.
- Sync files to a self-managed server.
- Restart self-managed services.
- Verify production after deploy.

Laravel Cloud is responsible for deployment status, build logs, deploy logs, runtime logs, workers, and custom-domain routing.

## Verify Production

After Laravel Cloud reports a successful production deploy:

```shell
curl -I https://workoutmcp.com/up
curl -I https://workoutmcp.com/login
curl -sS https://workoutmcp.com/.well-known/oauth-authorization-server
```

Expected OAuth route middleware includes `web`, `auth`, and `throttle:60,1`. The `web` middleware is required so ChatGPT OAuth requests survive the redirect through `/login`.

## Production Gotchas

- Do not deploy this app to Cloudflare Pages or Workers. Laravel Cloud is the runtime for PHP, Composer dependencies, queues, database access, and writable storage integrations.
- Do not commit `.env` files or production secrets.
- Do not add `WORKOUT_MEMORY_OAUTH_APPROVAL_PIN`. OAuth authorization is based on the signed-in Laravel user; there is no separate approval PIN.
- Do not run `php artisan optimize:clear` as part of normal production deploys because it clears the configured cache store and can force ChatGPT reconnects.
- Keep these public URLs stable:
  - Protected resource metadata: `https://workoutmcp.com/.well-known/oauth-protected-resource/mcp/workout-memory`
  - Authorization server metadata: `https://workoutmcp.com/.well-known/oauth-authorization-server`
  - Authorization endpoint: `https://workoutmcp.com/oauth/authorize`
  - Token endpoint: `https://workoutmcp.com/oauth/token`
- If production deploys fail, check Laravel Cloud deployment logs first. A red GitHub Actions run means CI failed, not that Laravel Cloud necessarily deployed or rolled back.
- If `workoutmcp.com` stops resolving, check the Laravel Cloud custom-domain target and Cloudflare DNS records. Do not restore retired hosting `A` records.

## Useful Links

- [Laravel Cloud dashboard](https://cloud.laravel.com/)
- [Laravel Cloud deployments documentation](https://cloud.laravel.com/docs/deployments)
- [Laravel Cloud environments documentation](https://cloud.laravel.com/docs/environments)
- [Laravel Cloud queues documentation](https://cloud.laravel.com/docs/queues)
