# ArgotesIA Ops

Sistema interno para Ivan/Oscar: intake de WhatsApp/manual, tickets operativos, asignacion, propuestas por modelos locales/Codex y aprobacion humana.

## Local

```bash
docker compose up -d
php -S 127.0.0.1:8090 -t public public/router.php
```

- App: `http://127.0.0.1:8090`
- phpMyAdmin: `http://127.0.0.1:8091`

## Usuarios dev

- `ivan@argotes.com` / `123456`
- `oscar@argotes.com` / `123456`

## Worker local

```bash
OPS_BASE_URL=http://127.0.0.1:8090 \
OPS_WORKER_KEY=ivan \
OPS_WORKER_TOKEN=269a9f3235d419f9d7fb3575f9bf92e0e9fe18d598be8f6a \
php scripts/mac-agent.php
```

El agente lee tickets asignados, genera una propuesta local inicial y la sube al dashboard. Si hay un modelo local HTTP compatible con Ollama, configurar `LOCAL_MODEL_URL`.

## Deploy

El DocumentRoot puede apuntar a la raiz del repo. `.htaccess` reenvia todo a `public/` y bloquea carpetas sensibles.

```bash
REMOTE=argotes-ops@46.202.179.60 \
REMOTE_DIR=/home/argotes-ops/htdocs/ops.argotes.com \
./deploy.sh
```

Crear `.env` de produccion directamente en el servidor y aplicar `database/schema.sql` en la base `argotesia_ops`.
