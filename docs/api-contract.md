# ArgotesIA Ops API Contract

Contrato para integraciones externas, especialmente lector/transcriptor de WhatsApp.

## Autenticacion

Enviar el token de integracion en cada request:

```http
Authorization: Bearer <OPS_API_TOKEN>
```

Alternativa aceptada:

```http
X-Ops-Api-Key: <OPS_API_TOKEN>
```

El token se configura en produccion en `/home/argotes-ops/app/.env` como `OPS_API_TOKEN`. No usar tokens de usuario ni `worker_token` para estas integraciones.

## URLs base

Produccion actual sin rewrite:

```text
https://ops.argotes.com/index.php?r=%2Fapi%2Fintake%2Fmessages
https://ops.argotes.com/index.php?r=%2Fapi%2Foutbox%2Fclient-replies
https://ops.argotes.com/index.php?r=%2Fapi%2Foutbox%2Fclient-replies%2Fack
```

Si el servidor queda con rewrite habilitado en el futuro, las rutas equivalentes son:

```text
/api/intake/messages
/api/outbox/client-replies
/api/outbox/client-replies/ack
```

## Entrada: crear intake/ticket desde WhatsApp

`POST /api/intake/messages`

Crea una entrada de intake clasificada y, por defecto, crea el ticket operativo. Es idempotente si se envia `external_ref`: si el mismo mensaje/audio se reenvia, devuelve el intake/ticket existente en vez de duplicarlo.

### Request recomendado

```json
{
  "source_channel": "whatsapp",
  "external_ref": "whatsapp:+50760000000:wamid.HBgM...",
  "client_name": "Cliente o empresa si se conoce",
  "client_contact": "+50760000000",
  "title": "No puede entrar al sistema",
  "transcript": "Audio transcrito completo. El cliente dice que no puede entrar al sistema desde esta manana y le sale error 500.",
  "raw_notes": "Opcional. Texto tecnico del transcriptor o notas internas.",
  "received_at": "2026-07-09T10:20:30-05:00",
  "project_key": "argotesia-ops",
  "assigned_worker_key": "ivan",
  "create_ticket": true,
  "audio": {
    "url": "https://.../audio.ogg",
    "mime_type": "audio/ogg",
    "duration_seconds": 42
  },
  "sender": {
    "name": "Nombre WhatsApp",
    "phone": "+50760000000"
  },
  "raw_payload": {
    "provider": "whatsapp-reader",
    "message_id": "wamid.HBgM..."
  }
}
```

### Campos

| Campo | Tipo | Requerido | Nota |
|---|---:|---:|---|
| `source_channel` | string | No | Usar `whatsapp`. Valores: `whatsapp`, `audio`, `email`, `phone`, `manual`, `web`, `other`. |
| `external_ref` | string | Recomendado | Identificador unico del mensaje/audio. Necesario para evitar duplicados. |
| `client_name` | string | No | Nombre del cliente o empresa si se conoce. |
| `client_contact` | string | No | Telefono, email o contacto de origen. |
| `title` | string | No | Si falta, el sistema usa el inicio de la transcripcion. |
| `transcript` | string | Si | Texto transcrito del audio o mensaje. |
| `raw_notes` | string | No | Notas internas o diagnostico del transcriptor. |
| `received_at` | string | No | ISO 8601 recomendado. |
| `project_key` | string | No | Si se conoce, fuerza el proyecto. Ejemplo: `argotesia-ops`. |
| `assigned_worker_key` | string | No | Si se conoce, fuerza asignacion. Ejemplo: `ivan` u `oscar`. |
| `create_ticket` | boolean | No | Default `true`. Si `false`, queda solo como intake clasificado. |
| `audio` | object | No | Metadata del audio. Se guarda en notas internas. |
| `sender` | object | No | Metadata del remitente. |
| `raw_payload` | object | No | Payload original reducido. Evitar enviar secretos. |

### Response nuevo

```json
{
  "ok": true,
  "duplicate": false,
  "intake_id": 12,
  "intake_status": "ticket_creado",
  "ticket_id": 8,
  "ticket_code": "OPS-2026-00008",
  "ticket_status": "asignado",
  "classification": {
    "intent": "incident",
    "urgency": "alta",
    "confidence": 0.8,
    "assigned_user_id": 1,
    "project_id": 1
  },
  "client_reply_draft": "Recibimos el reporte y lo estamos priorizando por impacto operativo. Caso: \"No puede entrar al sistema\". Te confirmamos avances antes de aplicar cambios."
}
```

### Response duplicado

```json
{
  "ok": true,
  "duplicate": true,
  "intake_id": 12,
  "intake_status": "ticket_creado",
  "ticket_id": 8,
  "ticket_code": "OPS-2026-00008",
  "ticket_status": "asignado",
  "classification": {
    "intent": "incident",
    "urgency": "alta",
    "confidence": 0.8
  }
}
```

### Curl de prueba

```bash
curl -sS -X POST 'https://ops.argotes.com/index.php?r=%2Fapi%2Fintake%2Fmessages' \
  -H 'Authorization: Bearer <OPS_API_TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{
    "source_channel": "whatsapp",
    "external_ref": "whatsapp:test:001",
    "client_contact": "+50760000000",
    "title": "Error entrando al sistema",
    "transcript": "El cliente reporta que no puede entrar al sistema y le sale error 500.",
    "assigned_worker_key": "ivan",
    "create_ticket": true
  }'
```

## Salida: consultar respuestas aprobadas para cliente

`GET /api/outbox/client-replies?status=aprobado&limit=50`

Devuelve borradores/respuestas para enviar al cliente. Por defecto solo devuelve tickets en estado `aprobado`, para respetar aprobacion humana. Para pruebas internas se puede usar `status=en_revision`.

### Response

```json
{
  "ok": true,
  "status": "aprobado",
  "items": [
    {
      "id": 8,
      "code": "OPS-2026-00008",
      "title": "Error entrando al sistema",
      "client_name": null,
      "client_contact": "+50760000000",
      "source_channel": "whatsapp",
      "status": "aprobado",
      "client_reply_draft": "Texto aprobado para enviar al cliente.",
      "updated_at": "2026-07-09 10:40:00",
      "created_at": "2026-07-09 10:20:00",
      "intake_external_ref": "whatsapp:+50760000000:wamid.HBgM..."
    }
  ]
}
```

### Curl

```bash
curl -sS 'https://ops.argotes.com/index.php?r=%2Fapi%2Foutbox%2Fclient-replies&status=aprobado&limit=20' \
  -H 'Authorization: Bearer <OPS_API_TOKEN>'
```

## Salida: confirmar envio por WhatsApp

`POST /api/outbox/client-replies/ack`

Registra un evento en el ticket indicando que la respuesta fue enviada por la integracion externa. No cierra el ticket automaticamente.

### Request

```json
{
  "ticket_id": 8,
  "external_ref": "whatsapp-sent:wamid.HBgM...",
  "sent_text": "Texto que se envio al cliente.",
  "sent_at": "2026-07-09T10:45:00-05:00"
}
```

### Response

```json
{
  "ok": true,
  "ticket_id": 8
}
```

## Codigos de error

| HTTP | Caso |
|---:|---|
| 400 | JSON invalido. |
| 403 | Token invalido. |
| 422 | Faltan campos requeridos o valor invalido. |
| 503 | `OPS_API_TOKEN` no esta configurado en el servidor. |
| 500 | Error guardando intake/ticket. |

## Control interno desde Telegram

`POST /api/tickets/telegram-actions`

Endpoint interno usado por el bot de Telegram. Requiere el mismo `OPS_API_TOKEN` y
registra todas las acciones en `ticket_events`. No envia mensajes al cliente.

Request:

```json
{
  "ticket_code": "OPS-2026-00042",
  "action": "approve",
  "worker_key": "ivan",
  "body": "Autorizo implementar la propuesta revisada.",
  "telegram_user_id": "123456789",
  "telegram_username": "ivan"
}
```

Acciones permitidas:

- `question`: pregunta del agente o Codex.
- `answer`: respuesta interna de Ivan/Oscar.
- `approve`: marca la ultima propuesta `ready` como `approved` y el ticket como `en_progreso`.
- `reject`: marca la ultima propuesta `ready` como `rejected` y el ticket como `en_revision`.

`approve` exige una propuesta lista; no autoriza una implementacion inexistente.

## Reglas operativas

- `external_ref` debe ser unico por mensaje/audio. Ejemplo recomendado: `whatsapp:<phone>:<message_id>`.
- No enviar audios binarios al API. Enviar URL o metadata del audio y la transcripcion en texto.
- No enviar secretos dentro de `raw_payload`.
- Si `assigned_worker_key` no se envia, el clasificador sugiere Ivan para incidentes urgentes y Oscar para el resto.
- El canal de salida debe enviar mensajes solo cuando el ticket este `aprobado`, salvo pruebas internas controladas.
