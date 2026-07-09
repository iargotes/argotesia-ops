# Roadmap

## Fase 0: Orden base

Estado: en progreso.

Objetivo:
Dejar el proyecto entendible, documentado y listo para iniciar codigo.

Entregables:

- README.
- Arquitectura inicial.
- Documento MVP.
- Guia de despliegue VPS.
- Registro de decisiones.

## Fase 1: Servicio base

Estado: implementado inicialmente.

Objetivo:
Crear el servicio FastAPI minimo.

Entregables:

- `GET /health`.
- Configuracion por variables de entorno.
- Dockerfile.
- `docker-compose.yml`.
- Pruebas basicas.

## Fase 2: Ingesta WhatsApp

Estado: implementado inicialmente para payload de referencia.

Objetivo:
Recibir mensajes reales o simulados por webhook.

Entregables:

- `POST /webhooks/whatsapp`.
- Validacion con Pydantic.
- Adaptador para payload del conector `whatsapp-web.js`.
- Guardado de payload original.
- Normalizacion de texto.
- Pruebas con remitentes `@c.us` y `@lid`.

## Fase 3: Telegram interno

Estado: servicio base implementado; pendiente configurar token real.

Objetivo:
Enviar a Oscar una notificacion basica por cada mensaje recibido.

Entregables:

- Configuracion de bot.
- Servicio de notificacion.
- Plantilla de mensaje interno.
- Manejo de error si Telegram falla.

## Fase 4: Clasificacion IA

Estado: clasificador mock implementado.

Objetivo:
Clasificar proyecto, categoria, prioridad y resumen.

Entregables:

- Interfaz de proveedor IA.
- Implementacion mock.
- Implementacion real posterior.
- Pruebas del contrato de clasificacion.

## Fase 5: Base de conocimiento

Objetivo:
Empezar a consultar documentacion e incidencias previas.

Entregables:

- Carpeta `docs/knowledge`.
- Formato de incidencias resueltas.
- Busqueda inicial por texto.
- Coincidencias mostradas en Telegram.

## Fase 6: Audio e imagenes

Objetivo:
Reducir trabajo manual con notas de voz y capturas.

Entregables:

- Descarga segura de media.
- Transcripcion de audio.
- Analisis de capturas.
- Texto final unificado para clasificacion.

## Fase 7: Issues para Codex

Objetivo:
Convertir incidencias tecnicas en tareas listas para Codex.

Entregables:

- Generador Markdown de issue.
- Evidencia adjunta.
- Hipotesis.
- Criterios de aceptacion.
