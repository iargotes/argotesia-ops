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

## Integracion WhatsApp AI

El backend FastAPI y el conector independiente de WhatsApp estan aislados en
[`integrations/ai-native-whatsapp`](integrations/ai-native-whatsapp/README.md).
Esta carpeta no reemplaza la aplicacion PHP ni su base de datos.

## Worker local

El worker local es el puente entre Ops, el modelo local y Codex. Se instala con el mismo codigo en las dos Macs; solo cambian `OPS_WORKER_KEY`, `OPS_WORKER_TOKEN`, las rutas de proyectos y el modelo local.

```bash
OPS_BASE_URL=http://127.0.0.1:8090 \
OPS_WORKER_KEY=ivan \
OPS_WORKER_TOKEN=269a9f3235d419f9d7fb3575f9bf92e0e9fe18d598be8f6a \
php scripts/mac-agent.php
```

El agente lee tickets asignados, genera una propuesta local inicial y la sube al dashboard. Si hay un modelo local HTTP compatible con Ollama, configurar `LOCAL_MODEL_URL`.

Para que Codex o el agente hagan una pregunta interna:

```bash
OPS_BASE_URL=https://ops.argotes.com \
OPS_WORKER_KEY=ivan \
TELEGRAM_AGENT_TOKEN=<token interno> \
php scripts/mac-agent.php ask OPS-2026-00042 "Necesito confirmar el alcance antes de proponer cambios." \
  --authorize
```

Para leer respuestas y autorizaciones nuevas:

```bash
OPS_BASE_URL=https://ops.argotes.com \
OPS_WORKER_KEY=ivan \
OPS_WORKER_TOKEN=<worker token> \
php scripts/mac-agent.php updates 0
```

El siguiente empaquetado será un `.app` con LaunchAgent para ejecutar este worker en segundo plano. Primero validamos uno o dos tickets con el CLI; el `.app` será la misma lógica con una bandeja, logs y configuración local.

### Configuracion local de Ivan

Esta Mac ya tiene PHP CLI y Ollama con `gemma4:12b`. Crear el archivo privado de configuracion:

```bash
cp .env.ivan.local.example .env.ivan.local
chmod +x scripts/ops-agent-ivan.sh
```

Completar solo en `.env.ivan.local`:

```env
OPS_WORKER_TOKEN=<token privado de Ivan>
TELEGRAM_AGENT_TOKEN=<token interno>
```

Validar primero sin cargar el agente en segundo plano:

```bash
./scripts/ops-agent-ivan.sh updates 0
./scripts/ops-agent-ivan.sh
```

Cuando el flujo esté probado, instalar el ejecutor cada 60 segundos:

```bash
mkdir -p "$HOME/Library/LaunchAgents"
cp launchd/com.argotesia.ops-agent.ivan.plist.example \
  "$HOME/Library/LaunchAgents/com.argotesia.ops-agent.ivan.plist"
launchctl bootstrap "gui/$(id -u)" \
  "$HOME/Library/LaunchAgents/com.argotesia.ops-agent.ivan.plist"
```

El archivo `.env.ivan.local` y el plist instalado quedan fuera de Git. Oscar usara la misma estructura con `OPS_WORKER_KEY=oscar` y su propio `OPS_WORKER_TOKEN`.

## Deploy

El deploy sube el codigo privado a `/home/argotes-ops/app` y publica solo el webroot en `/home/argotes-ops/htdocs/ops.argotes.com`.

```bash
REMOTE=argotes-ops@46.202.179.60 \
./deploy.sh
```

Crear `.env` de produccion directamente en `/home/argotes-ops/app/.env` y aplicar `/home/argotes-ops/app/database/schema.sql` en la base `argotesia_ops`.
