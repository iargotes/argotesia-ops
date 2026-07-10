# AI Ops Center

Plataforma interna para convertir mensajes de clientes por WhatsApp en incidencias clasificadas, diagnósticos iniciales y acciones listas para revisar por Oscar.

Este proyecto nace del documento base `AI_NATIVE_COMPANY_BLUEPRINT.md`. La primera meta no es crear un bot que responda clientes, sino un copiloto operativo privado para reducir carga manual y capturar conocimiento reutilizable.

## Objetivo inicial

Construir un MVP que haga este flujo:

```text
WhatsApp -> Webhook FastAPI -> ArgotesIA Ops -> Dashboard interno
```

El sistema debe:

- Recibir mensajes entrantes desde WhatsApp.
- Guardar el payload original.
- Normalizar mensajes de texto y transcribir notas de voz permitidas.
- Clasificar proyecto, tipo y prioridad.
- Notificar a Oscar por Telegram.
- Crear el ticket interno en `https://ops.argotes.com` usando el contrato de intake.
- Mantener control humano antes de cualquier respuesta externa.

## Principios

- La IA propone; Oscar decide.
- No responder automaticamente al cliente en las primeras fases.
- Mantener WhatsApp, IA, base de datos y Telegram separados por responsabilidad.
- Guardar eventos y decisiones para crear conocimiento permanente.
- Construir primero algo pequeno, funcional y verificable.

## Documentacion

- [Blueprint original](AI_NATIVE_COMPANY_BLUEPRINT.md)
- [Referencia WhatsApp](WHATSAPP_REFERENCE_FOR_OTHER_PROJECTS.md)
- [Arquitectura](docs/architecture.md)
- [MVP](docs/mvp.md)
- [Roadmap](docs/roadmap.md)
- [Conector WhatsApp](docs/operations/whatsapp-connector.md)
- [Despliegue VPS](docs/operations/vps-deployment.md)
- [Registro de decisiones](docs/decisions/README.md)

## Estado actual

MVP tecnico inicial creado:

- FastAPI.
- `GET /health`.
- `POST /webhooks/whatsapp`.
- Normalizacion del payload WhatsApp de referencia.
- Persistencia con SQLAlchemy.
- Clasificacion mock.
- Transcripcion local opcional con Faster Whisper en CPU.
- Notificacion Telegram opcional.
- Docker Compose con PostgreSQL.
- Pruebas basicas.

## Ejecucion local

Crear entorno e instalar dependencias:

```bash
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

Levantar API local con SQLite por defecto:

```bash
.venv/bin/uvicorn app.main:app --reload
```

Probar salud:

```bash
curl http://127.0.0.1:8000/health
```

Probar webhook WhatsApp:

```bash
curl -X POST http://127.0.0.1:8000/webhooks/whatsapp \
  -H 'Content-Type: application/json' \
  -d '{
    "chatType": "direct",
    "from": "50700000000@c.us",
    "pushName": "Cliente de prueba",
    "body": "No me aparece el viaje asignado en el mapa",
    "type": "chat",
    "timestamp": 1783555200,
    "id": "test-001"
  }'
```

## Docker

El `docker-compose.yml` levanta FastAPI y PostgreSQL:

```bash
docker compose up --build
```

Luego:

```bash
curl http://127.0.0.1:8000/health
```

## WhatsApp sin hacer pesado el stack

El backend puede recibir WhatsApp sin construir el conector dentro de este proyecto. La ruta liviana recomendada es:

```text
Conector WhatsApp existente o separado
  -> http://127.0.0.1:8000/webhooks/whatsapp
AI Ops Center
```

Esto evita meter Chromium/Puppeteer dentro del stack principal. El servicio `whatsapp` existe en Docker Compose bajo el perfil opcional `whatsapp`, pero no se levanta con `docker compose up` normal.

Solo usarlo cuando de verdad se quiera correr WhatsApp desde este repo:

```bash
docker compose --profile whatsapp up -d whatsapp
```

## Filtro de WhatsApp

Para canalizar solo los mensajes de soporte, configurar `WHATSAPP_ALLOWED_SENDERS` con los remitentes autorizados separados por coma.

Ejemplo:

```env
WHATSAPP_ALLOWED_SENDERS=50760000001,50760000002@c.us,cliente-especial@lid
```

Reglas:

- Si la variable esta vacia, el backend acepta todos los remitentes. Esto sirve para desarrollo.
- Si tiene valores, solo esos remitentes generan incidencia y notificacion.
- Para `@c.us`, se puede usar el numero o el JID completo.
- Para `@lid`, conviene usar el identificador exacto que llegue desde WhatsApp.
- Los mensajes ignorados no crean incidencia y no responden al cliente.

## Transcripcion local de audios

El conector acepta `ptt` y `audio` de remitentes autorizados. FastAPI transcribe cada
archivo localmente y de forma secuencial; el binario temporal se elimina al terminar y
solo se envia texto y metadata a ArgotesIA Ops.

Configuracion ligera recomendada para el VPS:

```env
WHISPER_ENABLED=true
WHISPER_MODEL=tiny
WHISPER_LANGUAGE=es
WHISPER_COMPUTE_TYPE=int8
WHISPER_MAX_AUDIO_BYTES=10485760
WHISPER_MAX_AUDIO_SECONDS=300
WHATSAPP_MAX_AUDIO_BYTES=10485760
WHATSAPP_AUDIO_TIMEOUT_MS=180000
```

El host necesita `ffprobe` para rechazar notas de voz demasiado largas antes de cargar
Whisper. La primera carga descarga el modelo configurado al cache local del usuario.

## Pruebas

```bash
.venv/bin/python -m pytest
```

## Integracion con ArgotesIA Ops

El webhook envia cada mensaje aceptado al dashboard central cuando estan configuradas
`OPS_INTAKE_URL` y `OPS_API_TOKEN`. `OPS_CREATE_TICKET=true` crea el ticket interno;
el `external_ref` usa `whatsapp:<sender>:<message_id>` para que los reintentos no
dupliquen tickets.

Configurar estas variables en el entorno del servicio:

```env
OPS_INTAKE_URL=https://ops.argotes.com/index.php?r=%2Fapi%2Fintake%2Fmessages
OPS_API_TOKEN=<token entregado por Ivan por canal seguro>
OPS_TICKET_ACTIONS_URL=https://ops.argotes.com/index.php?r=%2Fapi%2Ftickets%2Ftelegram-actions
OPS_CREATE_TICKET=true
WHATSAPP_AUTO_REPLY=false
TELEGRAM_BOT_TOKEN=<token del bot interno>
TELEGRAM_ADMIN_CHAT_ID=<chat interno>
TELEGRAM_WEBHOOK_SECRET=<secreto del webhook>
TELEGRAM_USER_WORKER_MAP=<telegram_user_id>:ivan,<telegram_user_id>:oscar
TELEGRAM_AGENT_TOKEN=<token para preguntas del agente>
```

El flujo no consume el outbox ni envia respuestas a clientes. Para control interno:

```text
/aprobar OPS-2026-00042
/rechazar OPS-2026-00042 motivo
/responder OPS-2026-00042 texto para el agente
```

Los botones de autorizar/rechazar solo funcionan para propuestas listas y para usuarios
incluidos en `TELEGRAM_USER_WORKER_MAP`.

El agente puede abrir una pregunta interna con:

```bash
curl -sS -X POST https://ainative.argotes.com/internal/telegram/questions \
  -H 'Authorization: Bearer <TELEGRAM_AGENT_TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{
    "ticket_code": "OPS-2026-00042",
    "question": "Necesito autorizacion para aplicar la propuesta revisada.",
    "worker_key": "ivan",
    "authorization_required": true
  }'
```

## Siguiente paso recomendado

Agregar Alembic formal para migraciones y preparar el conector WhatsApp Node/Express dentro del `docker-compose.yml` cuando se vaya a conectar el numero real.
