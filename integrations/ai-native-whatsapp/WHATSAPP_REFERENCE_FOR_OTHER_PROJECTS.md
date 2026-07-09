# Referencia WhatsApp para otros proyectos

Este archivo resume el WhatsApp que se esta usando como referencia en este directorio local:

`/Users/oscarargote/coopap-whatsapp`

No es una aplicacion completa versionada con Git. Es una carpeta local con una guia de recuperacion, un script de fix y una copia de referencia del servicio WhatsApp.

## Archivos importantes

- Guia original: `/Users/oscarargote/coopap-whatsapp/WHATSAPP_MCP_RECOVERY_GUIDE.md`
- Script fix pack: `/Users/oscarargote/coopap-whatsapp/scripts/whatsapp_fix_pack.sh`
- Servicio de referencia: `/Users/oscarargote/coopap-whatsapp/tmp/whatsapp3/whatsapp.js`
- Checklist de dependencias: `/Users/oscarargote/coopap-whatsapp/DEPENDENCY_SECURITY_CHECKLIST.md`

## Stack usado

- Node.js
- Docker
- Express
- `whatsapp-web.js`
- Puppeteer
- Axios
- `qrcode-terminal`
- `LocalAuth` para mantener sesion de WhatsApp

El fix pack instala:

```bash
npm i --production --foreground-scripts \
  'whatsapp-web.js@github:pedroslopez/whatsapp-web.js' \
  'puppeteer@24.10.2' \
  'axios@^1' 'express@^4' 'multer@^1' 'dotenv@^16' 'qrcode-terminal@0.12'
```

## Contenedor de referencia

El contenedor documentado se llama:

```bash
whatsapp-prod
```

La app dentro del contenedor vive en:

```bash
/usr/src/app
```

El script de recuperacion se ejecuta asi:

```bash
cd /Users/oscarargote/coopap-whatsapp
chmod +x scripts/whatsapp_fix_pack.sh
./scripts/whatsapp_fix_pack.sh whatsapp-prod
```

## Sesion WhatsApp

El servicio usa `LocalAuth`.

En el archivo de referencia aparece:

```js
authStrategy: new LocalAuth({ clientId: 'mcp-whatsapp3-50769250631' })
```

Para otro proyecto o numero, cambiar siempre el `clientId`.

Ejemplo:

```js
authStrategy: new LocalAuth({ clientId: 'mcp-whatsapp-<numero>' })
```

No reutilizar la carpeta `.wwebjs_auth` entre proyectos o numeros distintos.

## Webhook backend

El servicio de referencia manda los mensajes entrantes a:

```txt
https://back-artesanias.argotes.com/api/whatsapp/inbound
```

En otro proyecto hay que cambiar esa URL por el backend correspondiente.

El payload inbound tiene esta forma general:

```js
{
  chatType: 'direct',
  from: msg.from,
  pushName: msg.pushName,
  body: sanitizedBody,
  type: msg.type,
  timestamp: msg.timestamp,
  id: msg.id?._serialized
}
```

El backend puede responder:

```js
{
  message: 'texto a responder',
  to: 'destino opcional'
}
```

Si `message` viene vacio, el bot no responde.

## Filtros de mensajes

El servicio procesa solo mensajes directos.

Ignora:

- `status@broadcast`
- grupos `@g.us`
- broadcast `@broadcast`
- newsletters `@newsletter`
- mensajes enviados por el mismo numero en el evento inbound principal
- tipos que no sean texto `chat`

Tambien tiene un evento `message_create` para reenviar al backend los mensajes propios como echo.

## Endpoints recomendados

El fix pack agrega endpoints de diagnostico:

```txt
GET /status
GET /health
GET /me
GET /diag
```

Tambien agrega el endpoint robusto:

```txt
POST /send-text2
```

Uso esperado desde n8n:

```txt
http://whatsapp-prod:3000/send-text2
```

Body:

```json
{
  "to": "{{$json.from}}",
  "message": "texto de respuesta"
}
```

## Importante: soporte para @lid

WhatsApp a veces envia remitentes como `@lid` y no como `@c.us`.

El envio debe aceptar JIDs directos con estos sufijos:

- `@c.us`
- `@lid`
- `@g.us`
- `@s.whatsapp.net`

La validacion recomendada:

```js
const isDirectJid = /@(c\.us|lid|g\.us|s\.whatsapp\.net)$/i.test(jid);
```

Si no se soporta `@lid`, el bot puede fallar intentando convertir ese identificador a solo digitos.

## Endpoint robusto de envio

La idea de `/send-text2` es:

1. Recibir `to` y `message`.
2. Si `to` ya es JID directo, usarlo tal cual.
3. Si `to` es un numero, limpiar digitos.
4. Resolver el numero con `client.getNumberId(digits)`.
5. Enviar con `client.sendMessage(jid, message)`.
6. Responder con `status`, `id` y `to`.

Respuesta exitosa:

```json
{
  "status": "ok",
  "id": "message-id",
  "to": "destino-resuelto"
}
```

Errores esperados:

- `missing-to-or-message`
- `invalid-recipient`
- `number-not-on-whatsapp`

## Diagnostico rapido

Comandos utiles:

```bash
docker ps -a --filter "name=^/whatsapp-prod$"
docker logs --since=30m whatsapp-prod | tail -n 200
curl -s http://127.0.0.1:3000/status
curl -s http://127.0.0.1:3000/health
curl -s http://127.0.0.1:3000/me
curl -s http://127.0.0.1:3000/diag
```

Validaciones esperadas:

- `/health` debe mostrar `ready: true`.
- `/me` debe mostrar el `wid` del numero conectado.
- Los logs deben mostrar eventos `READY`, `OUT` y `ACK`.
- Un envio real por `/send-text2` debe devolver `status: ok`.

Si el estado queda en `UNPAIRED`, `CONFLICT` o no aparece `wid`, normalmente toca reautenticar WhatsApp con QR.

## Checklist para adaptarlo a otro proyecto

1. Crear nuevo proyecto Node/Express o adaptar el existente.
2. Instalar dependencias con versiones revisadas.
3. Definir un `clientId` unico para ese numero/proyecto.
4. Cambiar el webhook backend.
5. Mantener filtros para no procesar grupos si el flujo sera solo directo.
6. Copiar la logica robusta de `/send-text2`.
7. Mantener soporte para `@lid`.
8. Agregar endpoints `/health`, `/me`, `/diag` desde el inicio.
9. Probar QR, estado, inbound, respuesta y logs.
10. No reutilizar auth/session de otro proyecto.

