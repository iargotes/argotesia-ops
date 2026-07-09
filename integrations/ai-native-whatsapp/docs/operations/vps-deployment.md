# Despliegue VPS

## Objetivo

Preparar el proyecto para correr en un VPS despues de validar el MVP localmente.

## Enfoque recomendado

Usar Docker Compose en el VPS para mantener el servicio, base de datos y dependencias operativas de forma reproducible.

## Servicios esperados

- `app`: FastAPI.
- `whatsapp`: Node/Express con `whatsapp-web.js`.
- `db`: PostgreSQL.
- `redis`: opcional en fases posteriores.
- `nginx`: opcional para proxy HTTPS.

## Variables sensibles

Nunca deben quedar en git:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_ADMIN_CHAT_ID`
- `WHATSAPP_WEBHOOK_SECRET`
- `WHATSAPP_ALLOWED_SENDERS` si contiene numeros reales de clientes
- `LLM_API_KEY`
- `DATABASE_URL` real de produccion

## Flujo recomendado de despliegue

1. Construir y probar localmente.
2. Subir repositorio al VPS.
3. Crear archivo `.env` directamente en el VPS.
4. Configurar el conector WhatsApp con `clientId` unico.
5. Configurar `WHATSAPP_ALLOWED_SENDERS` con los remitentes autorizados.
6. Configurar el webhook del conector hacia `app`.
7. Levantar con `docker compose up -d`.
8. Verificar `/health` en FastAPI.
9. Verificar `/health`, `/status`, `/me` y `/diag` en WhatsApp.
10. Enviar payload de prueba autorizado a `/webhooks/whatsapp`.
11. Enviar payload de prueba no autorizado y confirmar `status: ignored`.
12. Confirmar notificacion en Telegram solo para el autorizado.
13. Revisar logs.

## Verificaciones minimas

```bash
docker compose ps
curl http://localhost:8000/health
curl -s http://127.0.0.1:3000/status
curl -s http://127.0.0.1:3000/me
curl -s http://127.0.0.1:3000/diag
docker compose logs -f app
```

## Seguridad minima

- Usar secreto en el webhook de WhatsApp.
- No registrar tokens en logs.
- Restringir puertos publicos.
- Usar HTTPS cuando el webhook sea publico.
- Mantener una forma rapida de apagar notificaciones o automatizaciones.
- No reutilizar sesiones `.wwebjs_auth` entre numeros o proyectos.
- Soportar remitentes `@lid` para evitar fallos de envio.

## Nota operativa

El primer despliegue al VPS debe seguir siendo supervisado. La IA no debe tener permisos para responder clientes ni ejecutar acciones destructivas.
