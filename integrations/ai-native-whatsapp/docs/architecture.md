# Arquitectura

## Vision

AI Ops Center debe operar como una plataforma interna de soporte asistido por IA. Su trabajo principal es recibir mensajes de clientes, estructurarlos, analizarlos y presentarlos a Oscar de forma clara.

## Flujo principal

```text
Cliente
  -> WhatsApp
  -> Instancia WhatsApp en VPS
  -> Webhook HTTP
  -> FastAPI Ingestion Service
  -> Normalizador de mensajes
  -> Clasificador IA
  -> Repositorios de datos
  -> Notificador Telegram
  -> Oscar revisa y decide
```

## Modulos iniciales

### WhatsApp Connector

Responsable de recibir eventos desde la instancia actual de WhatsApp y enviarlos al backend mediante webhook.

Referencia local:

- `WHATSAPP_REFERENCE_FOR_OTHER_PROJECTS.md`
- Carpeta externa usada como referencia: `/Users/oscarargote/coopap-whatsapp`

Este conector debe vivir separado del backend FastAPI. Su stack recomendado para reutilizar la experiencia existente es Node.js, Express, `whatsapp-web.js`, Puppeteer y `LocalAuth`.

Reglas importantes del conector:

- Usar un `clientId` unico por proyecto o numero.
- No reutilizar `.wwebjs_auth` entre proyectos o numeros distintos.
- Procesar solo mensajes directos en el flujo inicial.
- Ignorar grupos, newsletters, broadcasts y mensajes de estado.
- Mantener soporte para remitentes `@lid`.
- Exponer endpoints de diagnostico: `/status`, `/health`, `/me`, `/diag`.
- Enviar inbound hacia el backend FastAPI.
- No responder automaticamente si el backend no devuelve una respuesta explicita.

### FastAPI Ingestion Service

Responsable de exponer endpoints HTTP, validar entradas, guardar eventos crudos y disparar el procesamiento.

### Message Normalizer

Responsable de convertir distintos formatos de mensaje en una estructura comun.

### AI Classifier

Responsable de clasificar proyecto, categoria, prioridad y resumen. Debe existir detras de una interfaz para poder cambiar proveedor de IA.

### Telegram Notification Service

Responsable de enviar a Oscar un resumen interno de la incidencia.

### Repositories

Responsables de guardar mensajes, incidencias, clasificaciones y eventos relevantes.

## Reglas de separacion

- El webhook no debe contener logica de IA.
- Telegram no debe conocer detalles internos de WhatsApp.
- La clasificacion IA no debe escribir directamente en la base de datos.
- Los modelos de datos deben ser claros y versionables.
- La respuesta automatica al cliente queda fuera del MVP.

## Datos minimos del evento entrante

```json
{
  "message_id": "string",
  "from": "string",
  "contact_name": "string",
  "timestamp": "datetime",
  "message_type": "text|audio|image|video|document",
  "text": "string|null",
  "media_url": "string|null",
  "raw_payload": {}
}
```

## Payload realista desde WhatsApp Connector

La referencia actual envia un payload inbound con esta forma general:

```json
{
  "chatType": "direct",
  "from": "50700000000@c.us",
  "pushName": "Cliente",
  "body": "Mensaje del cliente",
  "type": "chat",
  "timestamp": 1783555200,
  "id": "message-id"
}
```

El backend FastAPI debe aceptar este contrato inicial y normalizarlo al modelo interno del sistema.

Mapeo inicial:

- `id` -> `message_id`
- `from` -> `from`
- `pushName` -> `contact_name`
- `body` -> `text`
- `type` -> `message_type`
- `timestamp` -> `timestamp`
- payload completo -> `raw_payload`

## Estados iniciales de incidencia

- `new`
- `in_review`
- `resolved`
- `ignored`
