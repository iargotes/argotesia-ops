# Conector WhatsApp

## Referencia

Este proyecto debe usar como referencia el archivo:

- `WHATSAPP_REFERENCE_FOR_OTHER_PROJECTS.md`

La carpeta externa documentada ahi es:

- `/Users/oscarargote/coopap-whatsapp`

Esa carpeta contiene una implementacion de referencia con Node.js, Express, `whatsapp-web.js`, Puppeteer, Axios, `qrcode-terminal` y `LocalAuth`.

## Rol en AI Ops Center

El conector WhatsApp no debe contener logica de IA ni reglas de negocio del sistema.

Su responsabilidad es:

- Mantener la sesion WhatsApp activa.
- Recibir mensajes entrantes.
- Filtrar mensajes que no aplican.
- Enviar eventos al backend FastAPI.
- Exponer endpoints de diagnostico.
- Permitir envio controlado en fases futuras.

## Enfoque liviano recomendado

`whatsapp-web.js` usa Puppeteer/Chromium. Si se construye dentro de Docker, la imagen se vuelve pesada porque necesita navegador y librerias graficas.

Para la primera integracion conviene:

1. Mantener FastAPI + PostgreSQL en este proyecto.
2. Usar un conector WhatsApp externo o ya existente.
3. Apuntar ese conector a `POST /webhooks/whatsapp`.
4. Dejar el servicio Docker `whatsapp` solo como opcion futura.

El perfil Docker opcional es:

```bash
docker compose --profile whatsapp up -d whatsapp
```

No usar ese perfil si se quiere mantener el stack liviano.

Opciones si se necesita algo mas liviano que `whatsapp-web.js` en Docker:

- Reutilizar el contenedor WhatsApp ya existente y cambiar solo el webhook.
- Ejecutar el conector Node fuera de Docker usando Chrome local.
- Usar WhatsApp Cloud API oficial, si se acepta configurar Meta Business y un numero compatible.
- Evaluar otra libreria sin navegador, con mas cuidado por estabilidad y soporte.

## Separacion con FastAPI

```text
WhatsApp Connector Node/Express
  -> POST /webhooks/whatsapp
FastAPI AI Ops Center
  -> guarda, normaliza, clasifica y notifica por Telegram
```

FastAPI es el sistema operativo de incidencias. El conector es solo el adaptador del canal WhatsApp.

## Configuracion obligatoria por proyecto

- `clientId` unico para cada numero o proyecto.
- Webhook backend apuntando al servicio FastAPI correcto.
- Sesion `.wwebjs_auth` separada por numero.
- Endpoints de diagnostico activos.
- Soporte para JIDs `@c.us`, `@lid`, `@g.us` y `@s.whatsapp.net`.

## Filtros iniciales

El flujo inicial debe procesar solo mensajes directos.

Ignorar:

- `status@broadcast`
- grupos `@g.us`
- broadcast `@broadcast`
- newsletters `@newsletter`
- mensajes propios en el evento inbound principal
- tipos que no sean texto `chat`

## Filtro de clientes autorizados

Ademas del filtro del conector, FastAPI debe tener una segunda defensa con `WHATSAPP_ALLOWED_SENDERS`.

Ejemplo:

```env
WHATSAPP_ALLOWED_SENDERS=50760000001,50760000002@c.us,cliente-especial@lid
```

Comportamiento:

- Si la lista esta vacia, se acepta todo. Usar solo en desarrollo o pruebas controladas.
- Si la lista tiene valores, solo esos remitentes crean incidencia.
- Los remitentes no autorizados devuelven `status: ignored`.
- Los mensajes ignorados no se guardan como incidencia y no notifican Telegram.
- Para numeros normales, el filtro compara digitos aunque el remitente llegue como `@c.us`.
- Para `@lid`, usar el identificador exacto cuando no exista telefono resoluble.

Lista que falta definir con Oscar:

- Numeros de WhatsApp que realmente deben entrar como soporte.
- Si algun cliente usa varios numeros por sede, operador o administrador.
- Si se debe aceptar algun `@lid` exacto observado en logs.

## Payload inbound esperado

```json
{
  "chatType": "direct",
  "from": "50700000000@c.us",
  "pushName": "Cliente",
  "body": "Texto recibido",
  "type": "chat",
  "timestamp": 1783555200,
  "id": "message-id"
}
```

## Respuesta del backend

La referencia permite que el backend responda:

```json
{
  "message": "texto a responder",
  "to": "destino opcional"
}
```

Para el MVP de AI Ops Center, el backend no debe enviar respuesta automatica. La respuesta normal debe venir vacia o sin `message`.

## Diagnostico minimo

Comandos utiles en VPS:

```bash
docker ps -a --filter "name=^/whatsapp-prod$"
docker logs --since=30m whatsapp-prod | tail -n 200
curl -s http://127.0.0.1:3000/status
curl -s http://127.0.0.1:3000/health
curl -s http://127.0.0.1:3000/me
curl -s http://127.0.0.1:3000/diag
```

Validaciones esperadas:

- `/health` muestra `ready: true`.
- `/me` muestra el `wid` conectado.
- Logs muestran eventos `READY`, `OUT` y `ACK`.
- Si aparece `UNPAIRED`, `CONFLICT` o no hay `wid`, revisar autenticacion QR.

## Pendiente para implementacion

Cuando se cree el codigo del MVP, agregar un adaptador Pydantic para este payload y pruebas con:

- `from` terminado en `@c.us`.
- `from` terminado en `@lid`.
- `type` igual a `chat`.
- payloads ignorados por venir de grupo o broadcast.
