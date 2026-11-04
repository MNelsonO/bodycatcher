#!/usr/bin/env bash
# Installs the self-hosted WhatsApp gateway (Evolution API v2.3.7) on this
# server and connects it to the Kemet desk in /var/www/sales.
#
# What it does:
#   1. Creates a Postgres user + database "evolution" (password generated here).
#   2. Writes /opt/evolution/.env (root-only) with a generated API key.
#   3. Starts the gateway in Docker on 127.0.0.1:8080 (firewall keeps it private).
#   4. Adds EVOLUTION_API_KEY / EVOLUTION_WEBHOOK_TOKEN to /var/www/sales/.env.
# Safe to run twice: existing secrets and database are kept.
# Run as root:  bash install.sh
set -euo pipefail

DESK=/var/www/sales
DIR=/opt/evolution
mkdir -p "$DIR" && chmod 700 "$DIR"
cd "$DIR"

if [ ! -f .env ]; then
  DBPW=$(openssl rand -hex 24)
  APIKEY=$(openssl rand -hex 32)

  sudo -u postgres psql -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'evolution') THEN
    CREATE ROLE evolution LOGIN PASSWORD '$DBPW';
  ELSE
    ALTER ROLE evolution PASSWORD '$DBPW';
  END IF;
END \$\$;
SQL
  sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='evolution'" | grep -q 1 \
    || sudo -u postgres createdb -O evolution evolution

  cat > .env <<ENV
SERVER_NAME=evolution
SERVER_TYPE=http
SERVER_PORT=8080
SERVER_URL=http://127.0.0.1:8080
AUTHENTICATION_API_KEY=$APIKEY
AUTHENTICATION_EXPOSE_IN_FETCH_INSTANCES=true
DATABASE_PROVIDER=postgresql
DATABASE_CONNECTION_URI=postgresql://evolution:$DBPW@127.0.0.1:5432/evolution?schema=evolution_api
DATABASE_CONNECTION_CLIENT_NAME=evolution_kemet
DATABASE_SAVE_DATA_INSTANCE=true
DATABASE_SAVE_DATA_NEW_MESSAGE=true
DATABASE_SAVE_MESSAGE_UPDATE=true
DATABASE_SAVE_DATA_CONTACTS=true
DATABASE_SAVE_DATA_CHATS=true
DATABASE_SAVE_DATA_LABELS=false
DATABASE_SAVE_DATA_HISTORIC=true
CACHE_REDIS_ENABLED=false
CACHE_LOCAL_ENABLED=true
CONFIG_SESSION_PHONE_CLIENT=Kemet Desk
CONFIG_SESSION_PHONE_NAME=Chrome
QRCODE_LIMIT=30
DEL_INSTANCE=false
LOG_LEVEL=ERROR,WARN
LOG_BAILEYS=error
WEBHOOK_GLOBAL_ENABLED=false
TELEMETRY_ENABLED=false
ENV
  chmod 600 .env
fi

cat > docker-compose.yml <<'YML'
services:
  evolution:
    image: evoapicloud/evolution-api:v2.3.7
    container_name: evolution_api
    restart: unless-stopped
    network_mode: host
    env_file: .env
    volumes:
      - evolution_instances:/evolution/instances
    mem_limit: 600m
volumes:
  evolution_instances:
YML

docker compose pull -q
docker compose up -d

echo "Waiting for the gateway to start..."
for i in $(seq 1 30); do
  if curl -fsS -o /dev/null http://127.0.0.1:8080/; then break; fi
  sleep 2
done
curl -fsS -o /dev/null http://127.0.0.1:8080/ && echo "Gateway is up on 127.0.0.1:8080" || { echo "Gateway did not start; see: docker logs evolution_api"; exit 1; }

# Connect the desk (keeps existing values if already set).
APIKEY=$(grep '^AUTHENTICATION_API_KEY=' .env | cut -d= -f2-)
grep -q '^EVOLUTION_API_KEY=' "$DESK/.env" || {
  HOOK=$(openssl rand -hex 24)
  {
    echo ""
    echo "EVOLUTION_BASE_URL=http://127.0.0.1:8080"
    echo "EVOLUTION_API_KEY=$APIKEY"
    echo "EVOLUTION_INSTANCE=kemet"
    echo "EVOLUTION_WEBHOOK_TOKEN=$HOOK"
  } >> "$DESK/.env"
}
cd "$DESK"
sudo -u www-data php artisan optimize:clear >/dev/null
sudo -u www-data php artisan queue:restart >/dev/null
echo "Done. Open https://sales.kemettech.co.ke/whatsapp and click 'Show QR code'."
