# AI Ops Center

Plataforma interna para convertir mensajes de clientes por WhatsApp en incidencias clasificadas, diagnósticos iniciales y acciones listas para revisar por Oscar.

Este proyecto nace del documento base `AI_NATIVE_COMPANY_BLUEPRINT.md`. La primera meta no es crear un bot que responda clientes, sino un copiloto operativo privado para reducir carga manual y capturar conocimiento reutilizable.

## Objetivo inicial

Construir un MVP que haga este flujo:

```text
WhatsApp -> Webhook FastAPI -> Base de datos -> Clasificacion IA -> Telegram interno
```

El sistema debe:

- Recibir mensajes entrantes desde WhatsApp.
- Guardar el payload original.
- Normalizar mensajes de texto.
- Clasificar proyecto, tipo y prioridad.
- Notificar a Oscar por Telegram.
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

## Pruebas

```bash
.venv/bin/python -m pytest
```

## Siguiente paso recomendado

Agregar Alembic formal para migraciones y preparar el conector WhatsApp Node/Express dentro del `docker-compose.yml` cuando se vaya a conectar el numero real.
