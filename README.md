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

## API de integraciones

Contrato para WhatsApp/transcripcion y salida hacia cliente: [`docs/api-contract.md`](docs/api-contract.md).

## Worker local

```bash
OPS_BASE_URL=http://127.0.0.1:8090 \
OPS_WORKER_KEY=ivan \
OPS_WORKER_TOKEN=269a9f3235d419f9d7fb3575f9bf92e0e9fe18d598be8f6a \
php scripts/mac-agent.php
```

El agente lee tickets asignados, genera una propuesta local inicial y la sube al dashboard. Si hay un modelo local HTTP compatible con Ollama, configurar `LOCAL_MODEL_URL`.

## Deploy

El deploy sube el codigo privado a `/home/argotes-ops/app` y publica solo el webroot en `/home/argotes-ops/htdocs/ops.argotes.com`.

```bash
REMOTE=argotes-ops@46.202.179.60 \
./deploy.sh
```

Crear `.env` de produccion directamente en `/home/argotes-ops/app/.env` y aplicar `/home/argotes-ops/app/database/schema.sql` en la base `argotesia_ops`.
