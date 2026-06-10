<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- This project is currently deployed on a Hetzner VPS, not Laravel Cloud. Cloudflare is used for DNS/proxy/TLS edge in front of the VPS; Cloudflare is not hosting the Laravel app.

## Production Infrastructure

- Domain: `workoutmcp.com`.
- VPS provider: Hetzner Cloud.
- Production server: `apps-01`.
- Production SSH target: `root@167.233.74.248`.
- Public app URL: `https://workoutmcp.com`.
- Web server: Caddy.
- PHP runtime: PHP 8.5 FPM.
- Database: SQLite at `/srv/apps/workout-memory-mcp/database/database.sqlite`.
- Queue worker: `workout-memory-queue.service`.
- Local database backup timer: `workout-memory-db-backup.timer`.

## Cloudflare Setup

- Cloudflare owns DNS for `workoutmcp.com`.
- DNS records should point at the Hetzner VPS:
  - `A workoutmcp.com -> 167.233.74.248`, proxied.
  - `A www.workoutmcp.com -> 167.233.74.248`, proxied.
- Caddy serves the apex domain and redirects `www.workoutmcp.com` to `https://workoutmcp.com{uri}`.
- Do not try to deploy the Laravel app to Cloudflare Pages/Workers for this project. The app needs PHP-FPM, Composer dependencies, Laravel queues, and SQLite writes, so the VPS is the runtime.

## Production Deploy Runbook

Automatic deploys are configured through GitHub Actions in `.github/workflows/deploy.yml`. A push to `main` runs Composer install, builds Vite assets, runs the PHPUnit suite, syncs the app to Hetzner with `rsync`, runs migrations, rebuilds Laravel caches, restarts the queue worker, reloads PHP-FPM, and verifies the public health/OAuth metadata endpoints.

The workflow expects these repository secrets:

- `HETZNER_HOST`: `167.233.74.248`
- `HETZNER_USER`: `deploy`
- `HETZNER_PATH`: `/srv/apps/workout-memory-mcp`
- `HETZNER_SSH_KEY`: private SSH key for the server `deploy` user

The server has a non-root `deploy` user for GitHub Actions. The app tree is owned by `deploy:www-data`, writable runtime directories use the setgid bit, and `/etc/sudoers.d/workoutmcp-deploy` only allows the deploy user to reload `php8.5-fpm`, restart `workout-memory-queue.service`, and check the queue service status without a password.

Deploys require a shell that is allowed to open outbound SSH connections to `root@167.233.74.248` on port 22. If a Codex chat or sandbox returns `ssh: connect to host 167.233.74.248 port 22: Operation not permitted`, stop there: that is a local environment/network permission block before SSH authentication, not a Hetzner, Cloudflare, or app failure. Run the deploy from this desktop workspace, from a local terminal with the Hetzner SSH key, or from another environment with outbound SSH allowed.

1. Build frontend assets locally before syncing:

```shell
npm run build
```

2. Sync the app to Hetzner with generated/runtime files excluded. Do not sync `.env`, `vendor`, `node_modules`, SQLite data, `storage` runtime files, or `bootstrap/cache`.

```shell
rsync -az --delete \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='bootstrap/cache/' \
  --exclude='database/database.sqlite' \
  --exclude='storage/logs/' \
  --exclude='storage/framework/cache/' \
  --exclude='storage/framework/sessions/' \
  --exclude='storage/framework/views/' \
  --exclude='storage/app/' \
  ./ root@167.233.74.248:/srv/apps/workout-memory-mcp/
```

3. On the server, install optimized production dependencies when dependencies may have changed, rebuild Laravel caches, restart workers, and reload PHP-FPM. For manual root deploys, preserve `deploy:www-data` ownership so later GitHub Actions deploys can still write the app:

```shell
ssh root@167.233.74.248 'cd /srv/apps/workout-memory-mcp \
  && composer install --no-dev --optimize-autoloader \
  && php artisan migrate --force \
  && chown -R deploy:www-data storage bootstrap/cache database \
  && chmod -R ug+rwX storage bootstrap/cache \
  && chmod ug+rwX database database/database.sqlite \
  && find storage bootstrap/cache database -type d -exec chmod 2775 {} + \
  && php artisan config:clear \
  && php artisan event:clear \
  && php artisan route:clear \
  && php artisan view:clear \
  && php artisan optimize \
  && php artisan queue:restart \
  && systemctl reload php8.5-fpm'
```

If Composer dependencies did not change, `composer dump-autoload --no-dev --optimize` is enough instead of `composer install`.

4. Verify production immediately after every deploy:

```shell
curl -I https://workoutmcp.com/up
curl -I https://workoutmcp.com/login
curl -sS https://workoutmcp.com/.well-known/oauth-authorization-server
ssh root@167.233.74.248 'cd /srv/apps/workout-memory-mcp && php artisan route:list --path=oauth/authorize -vv'
ssh root@167.233.74.248 'systemctl is-active workout-memory-queue.service'
```

Expected OAuth route middleware includes `web`, `auth`, and `throttle:60,1`. The `web` middleware is required so ChatGPT OAuth requests survive the redirect through `/login`.

## Production Gotchas

- Never sync local `bootstrap/cache/packages.php` or `bootstrap/cache/services.php` to production. Local development may include `laravel/boost`; production uses `--no-dev`, so syncing local cache files can crash production with `Class "Laravel\Boost\BoostServiceProvider" not found`.
- Avoid `php artisan optimize:clear` during normal deploys because it clears the configured cache store. This app stores OAuth client/token state in cache, so `optimize:clear` can force ChatGPT reconnects. Use `config:clear`, `event:clear`, `route:clear`, `view:clear`, then `optimize`.
- SQLite needs the database directory to be writable, not just the `.sqlite` file. Login, sessions, cache, queues, and OAuth token storage can fail if `/srv/apps/workout-memory-mcp/database` is not writable by the deploy/web server users.
- Keep `storage`, `bootstrap/cache`, the SQLite file, and the SQLite directory owned by `deploy:www-data` and group writable. Runtime directories should keep mode `2775` so new files inherit the `www-data` group.
- Production `.env` must remain server-local and should not be overwritten by rsync.
- Do not add `WORKOUT_MEMORY_OAUTH_APPROVAL_PIN`. OAuth authorization is based on the signed-in Laravel user; there is no separate approval PIN.
- MCP OAuth depends on these public URLs staying stable:
  - Protected resource metadata: `https://workoutmcp.com/.well-known/oauth-protected-resource/mcp/workout-memory`
  - Authorization server metadata: `https://workoutmcp.com/.well-known/oauth-authorization-server`
  - Authorization endpoint: `https://workoutmcp.com/oauth/authorize`
  - Token endpoint: `https://workoutmcp.com/oauth/token`
- Local backups are stored under `/srv/backups/workout-memory-mcp` by the systemd timer. This is not an offsite backup; add Hetzner snapshots or off-server backups before treating this as durable production data.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
