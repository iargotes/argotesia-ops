# MVP

## Objetivo

Validar si el sistema reduce el tiempo de analisis inicial de soporte sin automatizar respuestas al cliente.

## Alcance incluido

- Servicio FastAPI.
- Endpoint `GET /health`.
- Endpoint `POST /webhooks/whatsapp`.
- Compatibilidad inicial con el payload del conector WhatsApp documentado en `WHATSAPP_REFERENCE_FOR_OTHER_PROJECTS.md`.
- Persistencia del payload original.
- Normalizacion basica de mensajes de texto.
- Clasificacion inicial mediante proveedor mock.
- Notificacion interna por Telegram.
- Filtro configurable de remitentes autorizados por `WHATSAPP_ALLOWED_SENDERS`.
- Configuracion mediante variables de entorno.
- Ejecucion con Docker.
- Pruebas basicas.

## Fuera de alcance por ahora

- Responder automaticamente al cliente.
- Crear o modificar todavia el contenedor Node/Express de WhatsApp.
- Panel web.
- Audio.
- Analisis de imagenes.
- Busqueda vectorial.
- Integracion directa con GitHub o Codex.
- Automatizaciones destructivas.

## Criterios de aceptacion

1. El servicio levanta localmente.
2. `/health` responde correctamente.
3. `/webhooks/whatsapp` acepta un mensaje de prueba.
4. El payload original queda guardado.
5. El mensaje queda normalizado.
6. Se genera una clasificacion mock.
7. Telegram recibe una notificacion interna cuando sus variables estan configuradas.
8. No existe ningun flujo que responda automaticamente al cliente.
9. El proveedor de IA se puede reemplazar sin tocar el webhook.
10. La estructura permite agregar audio, imagenes y base de conocimiento despues.
11. Un remitente fuera de `WHATSAPP_ALLOWED_SENDERS` devuelve `status: ignored`.

Pendiente de verificacion para cerrar el MVP completo:

- Levantar `docker compose up --build` contra PostgreSQL.
- Conectar un bot real de Telegram.
- Conectar el conector WhatsApp real del VPS.

## Payload de prueba inicial

```json
{
  "message_id": "test-001",
  "from": "+50700000000",
  "contact_name": "Cliente de prueba",
  "timestamp": "2026-07-08T23:59:00-05:00",
  "message_type": "text",
  "text": "No me aparece el viaje asignado en el mapa",
  "media_url": null,
  "raw_payload": {}
}
```

## Payload compatible con la referencia WhatsApp

```json
{
  "chatType": "direct",
  "from": "50700000000@c.us",
  "pushName": "Cliente de prueba",
  "body": "No me aparece el viaje asignado en el mapa",
  "type": "chat",
  "timestamp": 1783555200,
  "id": "test-001"
}
```

El MVP debe guardar este payload completo y convertirlo internamente a un mensaje normalizado.
