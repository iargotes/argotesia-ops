# ADR-0001: Separar conector WhatsApp del backend FastAPI

## Fecha

2026-07-09

## Contexto

El proyecto necesita usar WhatsApp como canal principal de entrada, pero ya existe una referencia local basada en Node.js, Express, `whatsapp-web.js`, Puppeteer y `LocalAuth`.

El backend principal del AI Ops Center sera FastAPI y debe concentrarse en incidencias, normalizacion, clasificacion, diagnostico, persistencia y notificacion interna.

## Decision

Mantener el conector WhatsApp como servicio separado del backend FastAPI.

El conector se encarga del canal WhatsApp y envia eventos al endpoint `POST /webhooks/whatsapp`. FastAPI recibe el payload, lo guarda, lo normaliza y ejecuta el flujo interno.

## Consecuencias

Ventajas:

- Evita mezclar logica de WhatsApp con logica de IA.
- Permite reutilizar la referencia existente.
- Facilita diagnostico del canal mediante `/health`, `/status`, `/me` y `/diag`.
- Permite cambiar el conector en el futuro sin reescribir el nucleo.

Riesgos:

- Hay dos servicios que operar en VPS.
- Se necesita buena configuracion de red interna y variables de entorno.
- La sesion WhatsApp debe manejarse con cuidado para no mezclar numeros o proyectos.

Trabajo futuro:

- Crear adaptador Pydantic para el payload inbound real.
- Agregar pruebas de normalizacion con `@c.us` y `@lid`.
- Documentar el `docker-compose.yml` final cuando exista codigo.
