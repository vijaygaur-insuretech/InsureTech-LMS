#!/usr/bin/env bash
# One-time VPS preparation for the insureTech Skills LMS. Run as root:
#   ssh root@SERVER "bash -s -- https://lms.example.com admin@example.com 'ssh-ed25519 AAAA... github-deploy'" < deploy/server-setup.sh
#
# It only ADDS things: a "lmsdeploy" user, the folder /opt/insuretech-lms and its .env file.
# It does not install packages, stop services or edit any existing web-server, firewall or site configuration.
set -euo pipefail

LMS_URL="${1:?usage: server-setup.sh LMS_URL ADMIN_EMAIL 'DEPLOY_PUBLIC_KEY'}"
ADMIN_EMAIL="${2:?admin email missing}"
DEPLOY_KEY="${3:?deploy public key missing}"
LMS_HOME=/opt/insuretech-lms
LMS_PORT="${LMS_PORT:-8090}"

echo "== Checks =="
command -v docker >/dev/null || { echo "Docker is not installed. Install it first (https://docs.docker.com/engine/install/), then re-run."; exit 1; }
docker compose version >/dev/null || { echo "The docker compose plugin is missing. Install docker-compose-plugin, then re-run."; exit 1; }
if ss -ltn "( sport = :$LMS_PORT )" | grep -q LISTEN && [ ! -f "$LMS_HOME/.env" ]; then
    echo "Port $LMS_PORT is already used by something else. Re-run with LMS_PORT=<free port> in front of the command."; exit 1
fi

echo "== Deploy user =="
id lmsdeploy >/dev/null 2>&1 || useradd --create-home --shell /bin/bash lmsdeploy
usermod -aG docker lmsdeploy
install -d -m 700 -o lmsdeploy -g lmsdeploy /home/lmsdeploy/.ssh
touch /home/lmsdeploy/.ssh/authorized_keys
grep -qxF "$DEPLOY_KEY" /home/lmsdeploy/.ssh/authorized_keys || echo "$DEPLOY_KEY" >> /home/lmsdeploy/.ssh/authorized_keys
chown lmsdeploy:lmsdeploy /home/lmsdeploy/.ssh/authorized_keys; chmod 600 /home/lmsdeploy/.ssh/authorized_keys

echo "== App folder =="
install -d -o lmsdeploy -g lmsdeploy "$LMS_HOME" "$LMS_HOME/app"
if [ ! -f "$LMS_HOME/.env" ]; then
    rand() { tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$1"; }
    ADMIN_PASS="$(rand 16)Aa1!"
    cat > "$LMS_HOME/.env" <<ENV
LMS_URL=$LMS_URL
LMS_PORT=$LMS_PORT
LMS_DB_HOST=db
LMS_DB_NAME=lms
LMS_DB_USER=lms
LMS_DB_PASSWORD=$(rand 32)
LMS_ADMIN_USER=admin
LMS_ADMIN_PASSWORD=$ADMIN_PASS
LMS_ADMIN_EMAIL=$ADMIN_EMAIL
ENV
    chown lmsdeploy:lmsdeploy "$LMS_HOME/.env"; chmod 600 "$LMS_HOME/.env"
    echo "Created $LMS_HOME/.env. First admin login: admin / $ADMIN_PASS  (change it after logging in)"
else
    echo "$LMS_HOME/.env already exists - left unchanged."
fi

echo
echo "== What is serving ports 80/443 today (not changed) =="
ss -ltnp '( sport = :80 or sport = :443 )' || true
echo
echo "Done. The LMS will listen on 127.0.0.1:$LMS_PORT after the first GitHub Actions deploy."
echo "Point your existing web server at it for $(echo "$LMS_URL" | sed 's#.*://##') (see deploy/README.md)."
