# ArgotesIA Ops

Sistema interno para Ivan/Oscar: intake de WhatsApp/manual, tickets operativos, asignacion, propuestas por modelos locales/Codex y aprobacion humana.

La administracion de tickets es idempotente: un intake produce un solo ticket y las
reasignaciones preservan el estado salvo la primera asignacion de un ticket `nuevo`.

## Empezar aqui

Contexto canonico para Ivan, Oscar y cualquier agente Codex:
[`docs/project-context.md`](docs/project-context.md). Este documento reemplaza estados
antiguos publicados durante etapas previas de la integracion.

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

Configuracion y proceso completo de Oscar: [`docs/local-agent-oscar.md`](docs/local-agent-oscar.md).

Inicio rapido de Oscar:

```bash
./scripts/setup-oscar-agent.sh
```

```bash
OPS_BASE_URL=http://127.0.0.1:8090 \
OPS_WORKER_KEY=ivan \
OPS_WORKER_TOKEN=<worker-token-local> \
php scripts/mac-agent.php
```

El agente valida primero proyecto, ruta, repositorio, contexto y evidencia. Si falta algo,
pregunta por Telegram y no crea una propuesta generica. Con un modelo local HTTP compatible
con Ollama, genera una propuesta valida, la sube al dashboard y envia el diagnostico a Telegram.

Para que Codex liste tickets sin generar una propuesta:

```bash
php scripts/mac-agent.php tasks
```

Para subir una propuesta escrita por Codex:

```bash
php scripts/mac-agent.php submit OPS-2026-00042 proposal.md
```

Para que Codex o el agente hagan una pregunta interna:

```bash
OPS_BASE_URL=https://ops.argotes.com \
OPS_WORKER_KEY=ivan \
TELEGRAM_AGENT_TOKEN=<token interno> \
php scripts/mac-agent.php ask OPS-2026-00042 "Necesito confirmar el alcance antes de proponer cambios." \
  --authorize
```

`--authorize` solicita permiso para modificar codigo y probar. Despues de implementar y validar,
usar `--deploy` para solicitar una segunda autorizacion explicita antes del despliegue.

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

Para instalaciones existentes, aplicar `database/migrate_project_context.sql`. Las fichas JSON de proyecto aceptan `aliases` y `client_phones` como listas; los telefonos permiten resolver el proyecto sin usar IA. Se registran sin exponer secretos con:

```bash
php scripts/register-project.php /ruta/proyecto.json
```

Aplicar tambien `database/migrate_project_ssh_targets.sql`. Cada ficha debe usar
`server_ssh_ivan` y `server_ssh_oscar`; se guarda solo un alias SSH o `usuario@host`,
nunca llaves privadas.
El campo legado `server_ssh` es ambiguo y no se asigna automaticamente a ningun operador.
