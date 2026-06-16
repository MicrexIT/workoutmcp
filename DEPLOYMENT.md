# Laravel Deployment Runbook: Hetzner + Cloudflare + GitHub Actions

This document records the production setup for this app and the reusable pattern for similar Laravel apps.

Cloudflare is used for DNS, TLS proxying, and basic edge protection. Hetzner is the runtime: Caddy, PHP-FPM, Composer, Laravel queues, and SQLite live on the VPS. GitHub Actions deploys on every push to `main`.

## Current Production Values

Use these values for `workoutmcp.com`.

```shell
DOMAIN=workoutmcp.com
SERVER_IP=167.233.74.248
APP_SLUG=workout-memory-mcp
APP_PATH=/srv/apps/workout-memory-mcp
DEPLOY_USER=deploy
PHP_VERSION=8.5
QUEUE_SERVICE=workout-memory-queue.service
GITHUB_REPO=MicrexIT/workoutmcp
LOCAL_DEPLOY_KEY=~/.ssh/workoutmcp_github_actions_deploy
```

For a new app, replace every app-specific value:

```shell
DOMAIN=example.com
SERVER_IP=your.hetzner.ip
APP_SLUG=example-app
APP_PATH=/srv/apps/example-app
DEPLOY_USER=deploy-example-app
PHP_VERSION=8.5
QUEUE_SERVICE=example-app-queue.service
GITHUB_REPO=YourOrg/your-repo
LOCAL_DEPLOY_KEY=~/.ssh/example_app_github_actions_deploy
```

One VPS can run multiple small Laravel apps. Give each app its own app path, domain, Caddy site block, queue service, database file, GitHub secrets, and preferably its own deploy user/key.

## Architecture

```text
User / ChatGPT
  -> Cloudflare DNS and proxy
  -> Hetzner VPS public IP
  -> Caddy
  -> PHP-FPM
  -> Laravel app in /srv/apps/<app>
  -> SQLite database and database-backed cache/sessions/queue
```

Do not deploy this Laravel app to Cloudflare Pages or Workers. The app needs PHP-FPM, Composer dependencies, queue workers, and writable SQLite storage.

## Cloudflare Setup

1. Register or add the domain in Cloudflare.
2. Create DNS records:

```text
A  workoutmcp.com      167.233.74.248  Proxied
A  www.workoutmcp.com  167.233.74.248  Proxied
```

For another app, replace the domain and IP.

3. In Cloudflare SSL/TLS, use `Full` or `Full (strict)` once Caddy has a valid origin certificate from Let's Encrypt. If certificate issuance has trouble, temporarily set the DNS records to DNS-only, let Caddy issue the certificate, then enable proxying again.
4. Keep Cloudflare DNS pointed at the VPS. Caddy handles the actual Laravel routing.

## Fresh Hetzner Server Setup

These commands assume an Ubuntu Hetzner VPS and root SSH access.

### 1. SSH Into The Server

```shell
ssh root@167.233.74.248
```

For a new server:

```shell
ssh root@your.hetzner.ip
```

### 2. Set Variables On The Server

```shell
export DOMAIN=workoutmcp.com
export APP_SLUG=workout-memory-mcp
export APP_PATH=/srv/apps/workout-memory-mcp
export DEPLOY_USER=deploy
export PHP_VERSION=8.5
export QUEUE_SERVICE=workout-memory-queue.service
```

### 3. Install Base Packages

```shell
apt update
apt upgrade -y
apt install -y software-properties-common ca-certificates curl gnupg lsb-release unzip git rsync sqlite3 acl ufw openssl
```

### 4. Enable Firewall

```shell
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status
```

### 5. Install PHP

This app currently runs PHP 8.5. Keep the server PHP version and `.github/workflows/deploy.yml` in sync.

```shell
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y \
  php${PHP_VERSION}-cli \
  php${PHP_VERSION}-fpm \
  php${PHP_VERSION}-sqlite3 \
  php${PHP_VERSION}-mbstring \
  php${PHP_VERSION}-xml \
  php${PHP_VERSION}-curl \
  php${PHP_VERSION}-zip \
  php${PHP_VERSION}-bcmath \
  php${PHP_VERSION}-intl

systemctl enable --now php${PHP_VERSION}-fpm
php -v
```

### 6. Install Composer

```shell
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

### 7. Install Caddy

```shell
install -d -m 0755 /etc/apt/keyrings
curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/gpg.key \
  | gpg --dearmor -o /etc/apt/keyrings/caddy-stable-archive-keyring.gpg
curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt \
  > /etc/apt/sources.list.d/caddy-stable.list
apt update
apt install -y caddy
systemctl enable --now caddy
caddy version
```

## App User, Directories, And Permissions

### 1. Create The Deploy User

Run on the server:

```shell
adduser --disabled-password --gecos "" "${DEPLOY_USER}" || true
usermod -aG www-data "${DEPLOY_USER}"

install -d -m 2775 -o "${DEPLOY_USER}" -g www-data /srv/apps
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}"
```

### 2. Generate The Deploy SSH Key Locally

Run on your local machine, not on the server:

```shell
ssh-keygen -t ed25519 \
  -C "github-actions workoutmcp deploy" \
  -N "" \
  -f ~/.ssh/workoutmcp_github_actions_deploy
```

For this app, the key already exists locally at:

```shell
/Users/micrex/.ssh/workoutmcp_github_actions_deploy
```

Never commit this private key and never paste it into chat. It only goes into GitHub Actions secrets.

### 3. Add The Public Key To The Server

Run locally:

```shell
scp ~/.ssh/workoutmcp_github_actions_deploy.pub root@167.233.74.248:/tmp/deploy.pub
```

Run on the server:

```shell
install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
cat /tmp/deploy.pub >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chown "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"
rm /tmp/deploy.pub
```

Verify locally:

```shell
ssh -i ~/.ssh/workoutmcp_github_actions_deploy \
  -o IdentitiesOnly=yes \
  deploy@167.233.74.248 'whoami'
```

Expected output:

```text
deploy
```

### 4. Create Writable Runtime Directories

Run on the server:

```shell
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/database"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/bootstrap/cache"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage/app"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage/app/private"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage/app/public"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage/framework/cache/data"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage/framework/sessions"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage/framework/views"
install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_PATH}/storage/logs"

touch "${APP_PATH}/database/database.sqlite"
chown "${DEPLOY_USER}:www-data" "${APP_PATH}/database/database.sqlite"
chmod 660 "${APP_PATH}/database/database.sqlite"

find "${APP_PATH}" -type d -exec chmod 2775 {} +
```

The setgid bit on directories is important. It keeps new runtime files in the `www-data` group so both the deploy user and PHP-FPM can write what they need.

Ownership matters as much as mode: only a file's owner may `chmod` it or set its timestamps, so everything rsync manages must stay owned by `${DEPLOY_USER}`. Files PHP-FPM and the queue worker create at runtime (logs, compiled views, cache) are owned by `www-data` — that is expected, and the deploy workflow skips them on purpose (`rsync --omit-dir-times`, ownership-scoped `find -user` permission pass). Never "fix permissions" with `chown -R www-data:www-data` on the app tree and never run `artisan`/`composer` on the server as root or `www-data`; use the deploy user. Both June 11, 2026 deploy outages came from exactly that kind of drift.

### 5. Allow Limited Sudo For Deploys

The GitHub workflow needs to restart the Laravel queue worker and reload PHP-FPM. It should not need full root access.

Run on the server:

```shell
cat > /etc/sudoers.d/workoutmcp-deploy <<EOF
${DEPLOY_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${PHP_VERSION}-fpm, /usr/bin/systemctl restart ${QUEUE_SERVICE}, /usr/bin/systemctl is-active ${QUEUE_SERVICE}
EOF

chmod 440 /etc/sudoers.d/workoutmcp-deploy
visudo -cf /etc/sudoers.d/workoutmcp-deploy
```

If `command -v systemctl` returns a different path, use that path in the sudoers file and in the workflow.

## Production Environment File

Production `.env` stays on the server. It must not be committed or overwritten by deploys.

Run on the server before the first GitHub Actions deploy:

```shell
APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
MCP_PRIVATE_TOKEN="$(openssl rand -hex 32)"

cat > "${APP_PATH}/.env" <<EOF
APP_NAME="Workout Memory MCP"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://${DOMAIN}

MCP_PRIVATE_TOKEN=${MCP_PRIVATE_TOKEN}
WORKOUT_MEMORY_PUBLIC_URL=https://${DOMAIN}
WORKOUT_MEMORY_USER_NAME=Michele
WORKOUT_MEMORY_USER_EMAIL=michele@example.com
WORKOUT_MEMORY_TIMEZONE=Europe/Paris
WORKOUT_MEMORY_WEIGHT_UNIT=kg
WORKOUT_MEMORY_DISTANCE_UNIT=m
WORKOUT_MEMORY_REGISTRATION_ENABLED=false

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=${APP_PATH}/database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=resend
RESEND_API_KEY=change-me
MAIL_FROM_ADDRESS="no-reply@workoutmcp.com"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
EOF

chown "${DEPLOY_USER}:www-data" "${APP_PATH}/.env"
chmod 640 "${APP_PATH}/.env"
```

Do not add `WORKOUT_MEMORY_OAUTH_APPROVAL_PIN`. OAuth authorization is based on the signed-in Laravel user.

Keep a secure copy of the production `APP_KEY`. If you lose it, encrypted Laravel data and cookies cannot be decrypted.

## Caddy Configuration

Run on the server:

```shell
cat > /etc/caddy/Caddyfile <<EOF
${DOMAIN} {
    root * ${APP_PATH}/public
    encode zstd gzip
    php_fastcgi unix//run/php/php${PHP_VERSION}-fpm.sock
    file_server
}

www.${DOMAIN} {
    redir https://${DOMAIN}{uri} permanent
}
EOF

caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy
```

For multiple apps, add one site block per domain and point each block at that app's `public` directory.

## Laravel Queue Worker

This app uses Laravel's database queue. Run one systemd worker on the server.

```shell
cat > "/etc/systemd/system/${QUEUE_SERVICE}" <<EOF
[Unit]
Description=${APP_SLUG} Laravel queue worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_PATH}
ExecStart=/usr/bin/php ${APP_PATH}/artisan queue:work database --sleep=3 --tries=3 --timeout=90
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable "${QUEUE_SERVICE}"
```

The service may fail before the first deploy because `artisan` does not exist yet. Start or restart it after the app has been deployed:

```shell
systemctl restart "${QUEUE_SERVICE}"
systemctl is-active "${QUEUE_SERVICE}"
```

## SQLite Backups

Create local server backups. This is not a replacement for Hetzner snapshots or off-server backups.

Run on the server:

```shell
install -d -m 750 -o root -g root /srv/backups/workout-memory-mcp

cat > /usr/local/bin/backup-workout-memory-db <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

APP_PATH=/srv/apps/workout-memory-mcp
BACKUP_DIR=/srv/backups/workout-memory-mcp
STAMP="$(date -u +%Y%m%d-%H%M%S)"

sqlite3 "${APP_PATH}/database/database.sqlite" ".backup '${BACKUP_DIR}/database-${STAMP}.sqlite'"
find "${BACKUP_DIR}" -name 'database-*.sqlite' -type f -mtime +14 -delete
EOF

chmod 750 /usr/local/bin/backup-workout-memory-db

cat > /etc/systemd/system/workout-memory-db-backup.service <<'EOF'
[Unit]
Description=Backup workout-memory-mcp SQLite database

[Service]
Type=oneshot
ExecStart=/usr/local/bin/backup-workout-memory-db
EOF

cat > /etc/systemd/system/workout-memory-db-backup.timer <<'EOF'
[Unit]
Description=Run workout-memory-mcp SQLite backup every day

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now workout-memory-db-backup.timer
systemctl list-timers | grep workout-memory
```

For a new app, rename the backup script, unit names, app path, and backup path.

## GitHub Actions Auto Deploy

This repository deploys from `.github/workflows/deploy.yml`.

On push to `main`, the workflow:

1. Checks out the repo.
2. Installs PHP dependencies.
3. Generates a temporary CI `.env`.
4. Installs Node dependencies.
5. Builds Vite assets.
6. Runs `php artisan test --compact`.
7. Starts an SSH agent with `HETZNER_SSH_KEY`.
8. Syncs the repo to Hetzner with `rsync`.
9. Runs server-side Composer install, migrations, the idempotent exercise-catalog seeder (`db:seed --force`, safe to re-run: it only upserts seed catalog rows), cache rebuild, queue restart, and PHP-FPM reload.
10. Verifies `https://workoutmcp.com/up` and OAuth metadata.

### Required GitHub Secrets

Add these repository secrets:

```text
HETZNER_HOST=167.233.74.248
HETZNER_USER=deploy
HETZNER_PATH=/srv/apps/workout-memory-mcp
HETZNER_SSH_KEY=<contents of /Users/micrex/.ssh/workoutmcp_github_actions_deploy>
```

Using the GitHub CLI:

```shell
brew install gh
gh auth login

gh secret set HETZNER_HOST -R MicrexIT/workoutmcp --body '167.233.74.248'
gh secret set HETZNER_USER -R MicrexIT/workoutmcp --body 'deploy'
gh secret set HETZNER_PATH -R MicrexIT/workoutmcp --body '/srv/apps/workout-memory-mcp'
gh secret set HETZNER_SSH_KEY -R MicrexIT/workoutmcp < /Users/micrex/.ssh/workoutmcp_github_actions_deploy
```

Or use GitHub's web UI:

```text
Repository -> Settings -> Secrets and variables -> Actions -> New repository secret
```

### Deploy Ignore File

The workflow uses `.deployignore`. Keep runtime and generated files out of rsync:

```text
.env
.env.*
.git/
.github/
.phpunit.result.cache
node_modules/
vendor/
bootstrap/cache/
database/database.sqlite
storage/app/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/logs/
```

The important rule is that production `.env`, SQLite data, runtime storage, `vendor`, `node_modules`, and `bootstrap/cache` must not be copied from local development.

### First Auto Deploy

Commit the workflow and deploy ignore file:

```shell
git add AGENTS.md DEPLOYMENT.md .deployignore .github/workflows/deploy.yml
git commit -m "Add GitHub Actions deployment"
git push origin main
```

After pushing, watch the workflow:

```text
GitHub repository -> Actions -> Deploy
```

## Manual Deploy Fallback

Use this if GitHub Actions is unavailable.

Run locally:

```shell
npm run build
php artisan test --compact

rsync -az --delete \
  -e 'ssh -i /Users/micrex/.ssh/workoutmcp_github_actions_deploy -o IdentitiesOnly=yes' \
  --exclude-from=.deployignore \
  ./ deploy@167.233.74.248:/srv/apps/workout-memory-mcp/
```

Then run the remote deploy commands:

```shell
ssh -i /Users/micrex/.ssh/workoutmcp_github_actions_deploy \
  -o IdentitiesOnly=yes \
  deploy@167.233.74.248 'cd /srv/apps/workout-memory-mcp \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && php artisan migrate --force \
    && php artisan db:seed --force \
    && chmod -R ug+rwX storage bootstrap/cache \
    && chmod ug+rwX database database/database.sqlite \
    && find storage bootstrap/cache database -type d -exec chmod 2775 {} + \
    && php artisan config:clear \
    && php artisan event:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan optimize \
    && php artisan queue:restart \
    && sudo -n systemctl restart workout-memory-queue.service \
    && sudo -n systemctl reload php8.5-fpm'
```

If using root for a one-off recovery deploy, preserve deploy ownership afterwards:

```shell
ssh root@167.233.74.248 'cd /srv/apps/workout-memory-mcp \
  && chown -R deploy:www-data storage bootstrap/cache database \
  && chmod -R ug+rwX storage bootstrap/cache \
  && chmod ug+rwX database database/database.sqlite \
  && find storage bootstrap/cache database -type d -exec chmod 2775 {} +'
```

## Verification Checklist

Run after every deploy:

```shell
curl -I https://workoutmcp.com/up
curl -I https://workoutmcp.com/login
curl -sS https://workoutmcp.com/.well-known/oauth-authorization-server
curl -sS https://workoutmcp.com/.well-known/oauth-protected-resource/mcp/workout-memory

ssh -i /Users/micrex/.ssh/workoutmcp_github_actions_deploy \
  -o IdentitiesOnly=yes \
  deploy@167.233.74.248 'sudo -n systemctl is-active workout-memory-queue.service'
```

For this MCP app, also verify:

```shell
ssh root@167.233.74.248 \
  'cd /srv/apps/workout-memory-mcp && php artisan route:list --path=oauth/authorize -vv'
```

The OAuth authorize route must include `web`, `auth`, and `throttle:60,1` middleware. The `web` middleware is required so ChatGPT OAuth requests survive the redirect through `/login`.

## Common Failures

### SSH blocked by the environment

If a deploy attempt fails with:

```text
ssh: connect to host 167.233.74.248 port 22: Operation not permitted
```

The local environment is blocking outbound SSH before authentication. Run the deploy from a terminal that allows SSH, or use GitHub Actions.

### Deploy fails at rsync or the permission pass (ownership drift)

Failure signatures in the GitHub Actions log:

```text
rsync: [generator] failed to set times on ".../database": Operation not permitted (1)
rsync error: some files/attrs were not transferred ... (code 23)
```

```text
chmod: cannot access 'bootstrap/cache/...': Permission denied
```

Cause: paths under the app tree stopped being owned by the `deploy` user (usually after someone ran `chown -R www-data:www-data` or ran `artisan`/`composer` on the server as root or `www-data`). Only the owner of a path can set its times or mode, so the deploy aborts before migrations, caches, and service restarts — production keeps running the old finalized state while pushes silently stop landing.

The workflow tolerates `www-data`-owned *runtime* files by design (`rsync --omit-dir-times`, `find -user "$(id -un)"` permission pass), so this only happens when ownership of tracked/synced paths drifts. Recovery, on the server as root:

```shell
chown -R deploy:www-data /srv/apps/workout-memory-mcp/database \
  /srv/apps/workout-memory-mcp/storage /srv/apps/workout-memory-mcp/bootstrap/cache
find /srv/apps/workout-memory-mcp/database /srv/apps/workout-memory-mcp/storage \
  /srv/apps/workout-memory-mcp/bootstrap/cache -type d -exec chmod 2775 {} +
```

Then re-run the failed workflow (`gh run rerun <run-id>`) or push again. Prevention: do server maintenance as the `deploy` user, never chown the tree to `www-data`.

### Production crashes with missing dev package classes

Never sync local `bootstrap/cache/packages.php` or `bootstrap/cache/services.php` to production. Local development may include dev packages such as Laravel Boost; production uses `composer install --no-dev`.

Fix:

```shell
ssh deploy@167.233.74.248 'cd /srv/apps/workout-memory-mcp \
  && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
  && php artisan config:clear \
  && php artisan event:clear \
  && php artisan route:clear \
  && php artisan view:clear \
  && php artisan optimize'
```

### Login or OAuth fails after deploy

SQLite requires both the database file and the database directory to be writable.

Fix:

```shell
ssh root@167.233.74.248 'cd /srv/apps/workout-memory-mcp \
  && chown -R deploy:www-data database storage bootstrap/cache \
  && chmod -R ug+rwX database storage bootstrap/cache \
  && find database storage bootstrap/cache -type d -exec chmod 2775 {} + \
  && chmod 660 database/database.sqlite'
```

### ChatGPT OAuth reconnects unexpectedly

Avoid `php artisan optimize:clear` during normal deploys. This app stores OAuth client/token state in the configured cache store. Use:

```shell
php artisan config:clear
php artisan event:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

### Queue worker is not running

```shell
ssh root@167.233.74.248 'systemctl status workout-memory-queue.service --no-pager'
ssh root@167.233.74.248 'journalctl -u workout-memory-queue.service -n 100 --no-pager'
```

Restart:

```shell
ssh root@167.233.74.248 'systemctl restart workout-memory-queue.service'
```

### Caddy or PHP-FPM issue

```shell
ssh root@167.233.74.248 'caddy validate --config /etc/caddy/Caddyfile'
ssh root@167.233.74.248 'systemctl status caddy --no-pager'
ssh root@167.233.74.248 'systemctl status php8.5-fpm --no-pager'
ssh root@167.233.74.248 'journalctl -u caddy -n 100 --no-pager'
ssh root@167.233.74.248 'journalctl -u php8.5-fpm -n 100 --no-pager'
```

## Reusing This For A New Laravel App

1. Create or choose a Hetzner VPS. Reuse the current VPS for small apps if CPU/RAM/disk are fine.
2. Add the domain to Cloudflare.
3. Point `A` records for apex and `www` at the VPS IP.
4. Pick a unique app slug, path, queue service name, deploy user, and SSH key.
5. Run the server setup sections above if the VPS is new.
6. Create the app directories, `.env`, SQLite file, Caddy block, queue service, and backup timer.
7. Copy `.deployignore` and `.github/workflows/deploy.yml` into the new repo.
8. Replace domain, app path, PHP version, queue service name, and verification URLs in the workflow.
9. Add GitHub Actions secrets for the new repo.
10. Push to `main` and verify the app.

For a larger app, switch from SQLite to managed Postgres or MariaDB, add offsite backups, and consider release-directory deploys with symlink switching. The current setup is intentionally simple and appropriate for small Laravel apps on one VPS.
