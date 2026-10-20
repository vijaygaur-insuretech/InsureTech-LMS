# Deploying insureTech Skills LMS to a VPS

Every push to `main` runs **GitHub Actions → Check and deploy**:

1. **Check:** PHP and shell syntax of the custom code, plus a test build of the Docker image.
2. **Deploy:** builds the Docker image, pushes it to GitHub Container Registry (`ghcr.io/<owner>/insuretech-lms`), has the VPS pull it over SSH with a short-lived token, and runs `deploy/remote-deploy.sh`. That script:
   - installs the site on the first run, or upgrades the database on later runs;
   - restarts the web and cron containers;
   - checks that the login page answers.

The LMS runs as its own Docker Compose project, `insuretech-lms`, with its own network, its own volumes (`pgdata` for the database, `lmsdata` for files) and its own PostgreSQL. It publishes a single port on `127.0.0.1` (8090 by default), so other sites and containers on the server are untouched. The existing web server forwards the LMS domain to that port.

```
Internet ── existing nginx/Caddy/Traefik (80/443) ──► 127.0.0.1:8090 ── web (PHP 8.3 + Apache)
                                                                       cron (background tasks, every minute)
                                                                       db   (PostgreSQL 17)
```

## One-time setup

### 1. DNS
Add an `A` record for the LMS domain (for example `lms.insuretechskills.tech`) pointing to the VPS IP.

### 2. Deploy key (on your computer)
```bash
ssh-keygen -t ed25519 -N "" -C github-deploy -f ~/.ssh/insuretech_lms_deploy
```

### 3. Prepare the server
This only adds things: a `lmsdeploy` user, the folder `/opt/insuretech-lms` and its `.env` file. It needs Docker with the compose plugin to be installed already.
```bash
ssh root@SERVER_IP "bash -s -- https://lms.insuretechskills.tech you@insuretechskills.tech '$(cat ~/.ssh/insuretech_lms_deploy.pub)'" < deploy/server-setup.sh
```
It prints the first admin password, and shows what is using ports 80/443 today.

### 4. GitHub secrets
Add these under **Settings → Secrets and variables → Actions**:

| Secret | Value |
|---|---|
| `VPS_HOST` | server IP |
| `VPS_SSH_KEY` | contents of `~/.ssh/insuretech_lms_deploy` (the private key) |
| `VPS_PORT` | optional, default `22` |
| `VPS_KNOWN_HOSTS` | optional but recommended: output of `ssh-keyscan SERVER_IP` |

You can also set them from a terminal with `gh secret set` (see the main README).

### 5. Route the domain (existing web server)
For **nginx**, add a new file `/etc/nginx/sites-available/insuretech-lms` and enable it. No existing site is edited:
```nginx
server {
    listen 80;
    server_name lms.insuretechskills.tech;
    client_max_body_size 200M;
    location / {
        proxy_pass http://127.0.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
    }
}
```
```bash
ln -s /etc/nginx/sites-available/insuretech-lms /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d lms.insuretechskills.tech
```
For **Caddy**, add `lms.insuretechskills.tech { reverse_proxy 127.0.0.1:8090 }`; Caddy handles HTTPS itself.

For **Traefik**, add a router for the host that points to `http://<server IP>:8090`, or bind to the Docker gateway instead.

### 6. Deploy
Push to `main`, or open **Actions → Check and deploy → Run workflow**.

## Day-to-day
All commands below run in `/opt/insuretech-lms/app` on the server:
```bash
DC="docker compose --env-file ../.env -f deploy/docker-compose.yml"
$DC ps
$DC logs -f web
$DC exec -u www-data web php admin/cli/purge_caches.php
$DC exec -u www-data web php public/local/itskills/cli/create_multicloud_course.php
```

**Backup:** `$DC exec -T db pg_dump -U lms lms | gzip > lms-$(date +%F).sql.gz`, plus the `insuretech-lms_lmsdata` volume.

**Email:** set SMTP under Site administration → Server → Email → Outgoing mail configuration.
