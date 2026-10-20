#!/usr/bin/env bash
# Runs on the VPS after GitHub Actions has copied the new code into $LMS_HOME/app.
# Builds the image, installs the site on the first run, upgrades the database on later runs.
set -euo pipefail

LMS_HOME="${LMS_HOME:-/opt/insuretech-lms}"
cd "$LMS_HOME/app"
set -a; . "$LMS_HOME/.env"; set +a

files=(-f deploy/docker-compose.yml)
# Join the server's existing reverse-proxy network (e.g. Easypanel/Traefik) when one is configured.
[ -n "${LMS_PROXY_NETWORK:-}" ] && files+=(-f deploy/docker-compose.proxy.yml)
dc() { docker compose --env-file "$LMS_HOME/.env" "${files[@]}" "$@"; }
php_cli() { dc exec -T -u www-data web php "$@"; }

# GitHub Actions builds the image and loads it on the server (LMS_BUILD=0); set LMS_BUILD=1 to build here instead.
if [ "${LMS_BUILD:-1}" = "1" ]; then
    echo "==> Building image"
    dc build --pull web
fi

echo "==> Starting database"
dc up -d db
for _ in $(seq 1 30); do
    dc exec -T db pg_isready -U "$LMS_DB_USER" -d "$LMS_DB_NAME" >/dev/null 2>&1 && break
    sleep 2
done

installed=$(dc exec -T db psql -U "$LMS_DB_USER" -d "$LMS_DB_NAME" -tAc \
    "SELECT 1 FROM information_schema.tables WHERE table_name = 'mdl_config'" || true)

echo "==> Starting web and cron"
dc up -d --no-build --remove-orphans web cron

if [ "$installed" != "1" ]; then
    echo "==> First deploy: installing the LMS"
    php_cli admin/cli/install_database.php --agree-license --lang=en \
        --adminuser="${LMS_ADMIN_USER:-admin}" --adminpass="$LMS_ADMIN_PASSWORD" --adminemail="$LMS_ADMIN_EMAIL" \
        --fullname="${LMS_SITE_NAME:-insureTech Skills}" --shortname="${LMS_SITE_SHORTNAME:-insureTech}"
    php_cli admin/cli/cfg.php --name=theme --set=insuretech
    # Public landing page for visitors; learners still need to log in for course content.
    php_cli admin/cli/cfg.php --name=forcelogin --set=0
    php_cli admin/cli/cfg.php --name=enablemyhome --set=1
else
    echo "==> Upgrading database (if needed)"
    php_cli admin/cli/upgrade.php --non-interactive --allow-unstable
fi

php_cli admin/cli/purge_caches.php
docker image prune -f >/dev/null

echo "==> Health check"
host="${LMS_URL#*://}"; host="${host%%/*}"
code=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $host" "http://127.0.0.1:${LMS_PORT:-8090}/login/index.php")
echo "Login page HTTP $code"
[ "$code" = "200" ] || { dc logs --tail=50 web; exit 1; }

echo "==> Warming up the theme stylesheet"
# The first request after a cache purge compiles the CSS; do it now so no visitor gets an unstyled page.
css=$(curl -s -H "Host: $host" "http://127.0.0.1:${LMS_PORT:-8090}/" | grep -oE 'theme/styles\.php/[^"]+' | head -1)
[ -n "$css" ] && curl -s -o /dev/null -m 300 -w "Stylesheet HTTP %{http_code}\n" -H "Host: $host" "http://127.0.0.1:${LMS_PORT:-8090}/$css" || true
echo "==> Deployed"
