# AI Native Company Blueprint
## Plataforma de Operaciones Inteligentes para Argotes / Óscar Iván Argote

**Versión:** 1.0  
**Fecha:** 2026-07-08  
**Objetivo:** Convertir la operación actual de soporte, desarrollo y mantenimiento de aplicaciones en una empresa nativa en IA, donde los sistemas, procesos, documentación y decisiones sean asistidos por agentes inteligentes.

---

## 1. Visión general

La meta no es simplemente “usar IA” ni agregar un chatbot encima de sistemas existentes. La meta es rediseñar la forma de operar la empresa para que la IA sea parte central del flujo diario.

Actualmente, Óscar actúa como centro operativo de múltiples proyectos:

- Argodrive / Exclusivos en Colombia.
- Sistemas para clínicas odontológicas.
- Proyectos de artesanías u otros clientes.
- Soporte técnico diario por WhatsApp.
- Revisión de errores, cambios, solicitudes, capturas, mensajes de voz y problemas operativos.
- Correcciones en código y despliegues.
- Comunicación directa con usuarios y clientes.

El problema principal es que muchas decisiones y diagnósticos pasan por una sola persona. Esto convierte a Óscar en el cuello de botella.

La solución propuesta es construir una **plataforma interna de soporte asistido por IA**, que funcione primero como copiloto privado y luego pueda evolucionar hacia un producto reutilizable para otros clientes.

---

## 2. Definición de empresa nativa en IA

Una empresa nativa en IA es una empresa que diseña sus procesos desde el inicio considerando que la IA participa en la operación diaria.

No es:

- Usar ChatGPT de vez en cuando.
- Tener un bot que responde preguntas simples.
- Automatizar una tarea aislada.
- Crear scripts con IA y luego olvidarse de la IA.

Sí es:

- Capturar conocimiento operativo de forma permanente.
- Convertir cada incidencia en aprendizaje reutilizable.
- Automatizar el análisis inicial de problemas.
- Clasificar solicitudes de clientes.
- Generar diagnósticos antes de que el humano intervenga.
- Documentar automáticamente soluciones.
- Preparar respuestas, tareas, issues o parches de código.
- Reducir el trabajo manual repetitivo.
- Diseñar sistemas desacoplados, orientados a eventos y fáciles de ampliar.

La empresa nativa en IA no depende de que una persona recuerde todo. La empresa aprende, guarda contexto y mejora con cada interacción.

---

## 3. Situación actual

### 3.1 Canales de entrada actuales

Los clientes normalmente reportan problemas por WhatsApp. Los mensajes pueden llegar como:

- Texto.
- Nota de voz.
- Captura de pantalla.
- Video corto.
- Mensaje reenviado.
- Explicación informal de lo ocurrido.

El canal principal de entrada debe seguir siendo WhatsApp porque es donde los clientes ya están acostumbrados a escribir.

### 3.2 Forma actual de trabajo

El flujo actual suele ser:

1. Cliente escribe por WhatsApp.
2. Óscar lee o escucha el mensaje.
3. Óscar interpreta el problema.
4. Óscar identifica a qué sistema pertenece.
5. Óscar busca mentalmente si ha pasado antes.
6. Óscar revisa código, logs, base de datos o servidor.
7. Óscar responde al cliente.
8. Óscar corrige si hace falta.
9. Muchas veces la solución queda en la memoria personal, no en una base de conocimiento estructurada.

### 3.3 Problema operativo

Este modelo no escala porque:

- Todo depende de la disponibilidad de Óscar.
- Varias incidencias pueden llegar al mismo tiempo.
- Las notas de voz consumen tiempo.
- Las capturas requieren interpretación manual.
- Los problemas repetidos no siempre quedan documentados.
- El conocimiento queda disperso entre WhatsApp, código, memoria y conversaciones.
- No hay una bandeja inteligente que priorice.

---

## 4. Objetivo del sistema

Crear una plataforma interna que reciba mensajes de clientes por WhatsApp, los procese con IA y entregue a Óscar un resumen claro por Telegram, junto con diagnóstico, prioridad y posibles acciones.

El sistema debe funcionar primero como **copiloto interno**, no como bot automático que responde al cliente.

Esto permite mantener control humano mientras se reduce el trabajo manual.

---

## 5. Principio central de diseño

Antes de escribir código, cada nueva funcionalidad debe responder tres preguntas:

1. ¿Reduce trabajo manual?
2. ¿Aumenta el conocimiento permanente de la empresa?
3. ¿Es reutilizable en otros proyectos?

Si la respuesta a alguna de estas preguntas es “no”, la funcionalidad debe repensarse.

---

## 6. Arquitectura conceptual

La arquitectura propuesta separa claramente entrada, procesamiento, inteligencia, almacenamiento y salida.

### 6.1 Flujo principal

```text
Cliente
  ↓
WhatsApp
  ↓
Instancia WhatsApp en VPS / Docker
  ↓
Webhook hacia servicio Python
  ↓
FastAPI Ingestion Service
  ↓
Message Normalizer
  ↓
AI Processing Pipeline
  ↓
Knowledge Base / Logs / Git / Documentación
  ↓
Diagnosis Engine
  ↓
Telegram Internal Console
  ↓
Óscar revisa, decide y actúa
```

---

## 7. Canales

### 7.1 WhatsApp como canal de entrada

WhatsApp se mantiene como canal principal porque los clientes ya lo usan.

La idea no es reemplazar WhatsApp, sino capturar lo que entra y transformarlo en información estructurada.

La instancia actual de WhatsApp ya corre en un VPS, en Docker, estable 24/7. Esa base debe aprovecharse.

No se recomienda romper ni reemplazar esa instancia al inicio.

### 7.2 Telegram como consola interna

Telegram será usado como canal interno para Óscar.

Ventajas:

- Integración sencilla con bots.
- Mensajes estructurados.
- Botones interactivos.
- Respuestas rápidas.
- Separación entre clientes y operación interna.
- Menos riesgo de responder algo automático al cliente por error.

Ejemplo de mensaje interno en Telegram:

```text
🚨 Nueva incidencia detectada

Cliente: Clínica Dental Norte
Sistema: Argodental
Canal: WhatsApp
Tipo: Captura + texto
Prioridad: Media

Resumen:
La usuaria reporta que un pago aparece duplicado en caja después de cerrar el arqueo.

Posible causa:
Puede estar relacionado con doble envío del formulario o recarga de página.

Acciones sugeridas:
1. Revisar tabla payments por transaction_id.
2. Confirmar si hay doble registro con mismo paciente y monto.
3. Validar último despliegue del módulo de caja.

Botones:
[Ver detalle] [Marcar en proceso] [Crear issue] [Responder borrador]
```

---

## 8. Componentes principales

### 8.1 WhatsApp Connector

Responsabilidad:

- Recibir eventos desde la instancia actual de WhatsApp.
- Detectar mensajes entrantes.
- Extraer metadatos básicos.
- Enviar el evento al backend Python.

Datos mínimos esperados:

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

### 8.2 FastAPI Ingestion Service

Servicio principal de entrada.

Responsabilidades:

- Exponer endpoint para recibir webhooks.
- Validar payload.
- Guardar evento crudo.
- Crear registro normalizado.
- Enviar tarea a pipeline de procesamiento.

Endpoint sugerido:

```http
POST /webhooks/whatsapp
```

### 8.3 Message Normalizer

Convierte mensajes de distintos tipos a una estructura común.

Ejemplo de estructura normalizada:

```json
{
  "source_channel": "whatsapp",
  "client_id": "auto_or_unknown",
  "project": "argodrive|argodental|artesanias|unknown",
  "content_type": "text|audio|image|mixed",
  "normalized_text": "texto final procesado",
  "attachments": [],
  "received_at": "datetime",
  "status": "pending_ai_analysis"
}
```

### 8.4 Audio Transcription Service

Responsabilidad:

- Descargar nota de voz.
- Convertir formato si es necesario.
- Transcribir a texto.
- Guardar texto transcrito.

Debe soportar:

- Notas de voz de WhatsApp.
- Audios cortos.
- Audios con ruido moderado.
- Español latinoamericano.

### 8.5 Image Understanding Service

Responsabilidad:

- Analizar capturas de pantalla.
- Extraer texto visible.
- Identificar errores, pantallas, botones o módulos.
- Combinar imagen + texto del cliente.

Casos típicos:

- Pantalla de error.
- Reporte de pago duplicado.
- Problema en agenda.
- Error en mapa.
- Mensaje del navegador.
- Pantalla de app móvil.

### 8.6 AI Classification Agent

Primer agente de IA.

Responsabilidades:

- Identificar cliente.
- Identificar proyecto.
- Clasificar tipo de solicitud.
- Estimar prioridad.
- Detectar si es bug, soporte, duda, solicitud nueva o emergencia.

Categorías sugeridas:

```text
BUG
SOPORTE_OPERATIVO
SOLICITUD_CAMBIO
ERROR_USUARIO
ERROR_DATOS
INCIDENTE_CRITICO
CONSULTA_GENERAL
MEJORA_PRODUCTO
FACTURACION
DESCONOCIDO
```

Prioridades sugeridas:

```text
P0 - Sistema caído / operación detenida
P1 - Problema crítico de producción
P2 - Problema importante pero con alternativa manual
P3 - Consulta o ajuste menor
P4 - Mejora futura
```

### 8.7 Knowledge Retrieval Agent

Responsabilidad:

- Consultar documentación interna.
- Buscar incidencias anteriores.
- Revisar notas de solución.
- Buscar cambios recientes.
- Relacionar el problema actual con casos previos.

Fuentes iniciales:

- Markdown docs del proyecto.
- Historial de incidencias.
- Commits de Git.
- Logs seleccionados.
- Base de datos de conocimiento.
- Conversaciones anteriores procesadas.

### 8.8 Diagnosis Agent

Responsabilidad:

- Generar diagnóstico inicial.
- Explicar causa probable.
- Sugerir pasos de verificación.
- Proponer respuesta al cliente.
- Indicar si requiere intervención humana.

Formato esperado:

```json
{
  "summary": "resumen claro",
  "probable_cause": "causa probable",
  "confidence": 0.0,
  "recommended_steps": [],
  "suggested_reply": "borrador para cliente",
  "needs_human_review": true
}
```

### 8.9 Telegram Notification Service

Responsabilidad:

- Enviar resumen a Óscar.
- Permitir botones.
- Recibir comandos.
- Mantener conversación interna sobre la incidencia.

Comandos sugeridos:

```text
/detalle <ticket_id>
/logs <ticket_id>
/responder <ticket_id>
/issue <ticket_id>
/cerrar <ticket_id>
/reabrir <ticket_id>
```

---

## 9. Diseño orientado a eventos

El sistema debe ser orientado a eventos.

Cada mensaje entrante genera un evento. Los componentes reaccionan a eventos, no se llaman directamente de forma rígida.

Ejemplos de eventos:

```text
message.received
message.normalized
audio.transcribed
image.analyzed
incident.classified
knowledge.retrieved
diagnosis.generated
telegram.notification.sent
incident.closed
knowledge.article.created
```

Esto permite crecer sin reescribir todo.

---

## 10. Principios técnicos

### 10.1 Desacoplamiento

Cada módulo debe tener una responsabilidad clara.

No debe existir lógica de IA mezclada directamente con lógica de WhatsApp.

No debe existir lógica de Telegram mezclada con procesamiento de audio.

### 10.2 Interfaces claras

Cada servicio debe comunicarse mediante contratos definidos.

Preferir JSON con esquemas claros.

### 10.3 Reemplazabilidad

El sistema debe permitir cambiar:

- Modelo de IA.
- Proveedor de transcripción.
- Canal de entrada.
- Canal de salida.
- Base de datos vectorial.
- Motor de búsqueda.

Sin reescribir el núcleo.

### 10.4 Observabilidad

Todo evento importante debe quedar registrado.

Se deben guardar:

- Payload original.
- Resultado normalizado.
- Clasificación.
- Diagnóstico.
- Respuesta sugerida.
- Decisión humana.
- Resultado final.

### 10.5 Seguridad

Al inicio, la IA no debe responder automáticamente a clientes.

La IA propone. Óscar decide.

Automatizaciones externas solo deben activarse después de pruebas reales.

---

## 11. Base de conocimiento

La base de conocimiento es el corazón de la empresa nativa en IA.

Cada solución debe quedar registrada para que el sistema aprenda.

### 11.1 Tipos de conocimiento

- Manuales de sistemas.
- Errores frecuentes.
- Soluciones aplicadas.
- Procedimientos de soporte.
- Cambios técnicos.
- Decisiones de arquitectura.
- Reglas de negocio.
- Preguntas frecuentes.

### 11.2 Estructura sugerida de documentos

```text
docs/
  argodrive/
    overview.md
    architecture.md
    common-issues.md
    deployment.md
    database.md
    mobile-app.md
  argodental/
    overview.md
    agenda.md
    caja.md
    proveedores.md
    common-issues.md
  artesanias/
    overview.md
    common-issues.md
  operations/
    support-process.md
    incident-priorities.md
    telegram-commands.md
  ai-company/
    principles.md
    roadmap.md
    metrics.md
```

### 11.3 Formato para documentar incidencias

```md
# Incidencia: Pago duplicado en caja

## Proyecto
Argodental

## Fecha
2026-07-08

## Reportado por
Clínica X

## Síntomas
El pago aparece dos veces después de cerrar caja.

## Causa raíz
Doble envío del formulario por recarga de página.

## Solución aplicada
Se agregó control idempotente con transaction_id.

## Prevención
Validar botón deshabilitado después del primer submit.

## Archivos relacionados
- app/payments/service.py
- frontend/caja/PaymentForm.tsx

## Tags
caja, pagos, duplicado, idempotencia
```

---

## 12. Repositorio sugerido

Nombre sugerido:

```text
ai-ops-center
```

Estructura inicial:

```text
ai-ops-center/
  README.md
  docker-compose.yml
  .env.example
  app/
    main.py
    config.py
    api/
      webhooks.py
      telegram.py
    core/
      events.py
      schemas.py
      security.py
    services/
      whatsapp_ingestion.py
      audio_transcription.py
      image_analysis.py
      ai_classifier.py
      knowledge_retrieval.py
      diagnosis.py
      telegram_notify.py
    repositories/
      incidents_repository.py
      messages_repository.py
      knowledge_repository.py
    workers/
      process_message_worker.py
    models/
      incident.py
      message.py
      knowledge_item.py
  docs/
    ai-native-company-blueprint.md
    architecture.md
    roadmap.md
  tests/
    test_webhook.py
    test_classifier.py
    test_diagnosis.py
```

---

## 13. Stack técnico recomendado

### Backend

- Python.
- FastAPI.
- Pydantic.
- SQLAlchemy.
- PostgreSQL.
- Alembic.
- Docker.

### Procesamiento asíncrono

Opciones:

- Celery + Redis.
- RQ + Redis.
- Dramatiq.
- Arq.

Para una primera versión simple, se puede iniciar con tareas background de FastAPI y luego migrar a cola formal.

### IA

Debe usarse una capa abstracta para modelos.

Ejemplo:

```python
class LLMProvider:
    def classify_incident(self, input_data):
        raise NotImplementedError

    def generate_diagnosis(self, input_data):
        raise NotImplementedError
```

Esto permite cambiar de proveedor sin reescribir el sistema.

### Base de conocimiento / búsqueda

Fase inicial:

- PostgreSQL.
- Búsqueda por texto.
- Archivos Markdown.

Fase posterior:

- pgvector.
- Qdrant.
- Chroma.
- Weaviate.

---

## 14. Flujo funcional detallado

### 14.1 Entrada de texto

1. Cliente escribe por WhatsApp.
2. Instancia WhatsApp envía webhook.
3. FastAPI recibe evento.
4. Se normaliza mensaje.
5. IA clasifica.
6. IA consulta conocimiento.
7. IA genera diagnóstico.
8. Telegram envía resumen a Óscar.

### 14.2 Entrada de audio

1. Cliente envía nota de voz.
2. Sistema descarga audio.
3. Transcribe audio.
4. Guarda texto transcrito.
5. Ejecuta clasificación.
6. Genera diagnóstico.
7. Notifica por Telegram.

### 14.3 Entrada de captura de pantalla

1. Cliente envía imagen.
2. Sistema descarga imagen.
3. Extrae texto visible.
4. Analiza pantalla.
5. Combina imagen + mensaje del cliente.
6. Clasifica incidencia.
7. Notifica a Telegram.

---

## 15. Telegram como centro de mando

El bot de Telegram debe permitir que Óscar opere sin abrir paneles complejos al inicio.

### 15.1 Mensaje inicial sugerido

```text
🧠 AI Ops Center

Nueva solicitud recibida

ID: INC-20260708-001
Cliente: Exclusivos Fusa
Proyecto: Argodrive
Prioridad: P1
Tipo: BUG

Resumen:
El operador reporta que algunos viajes no aparecen en el mapa después de asignarlos.

Diagnóstico inicial:
Puede existir retraso en el worker de actualización o problema con el estado del conductor.

Confianza: 72%

Acciones sugeridas:
1. Revisar logs del worker de viajes.
2. Validar estado trip_status en base de datos.
3. Confirmar último deploy.

[Ver detalle] [Crear issue] [Responder] [Cerrar]
```

### 15.2 Comandos conversacionales

Óscar debería poder responder:

```text
¿Qué cambió hoy en Argodrive?
```

```text
Busca errores parecidos en los últimos 30 días.
```

```text
Prepárame una respuesta para el cliente.
```

```text
Crea un issue para Codex con esta incidencia.
```

```text
Resume todo lo que pasó hoy en soporte.
```

---

## 16. Integración con Codex

El sistema debe generar instrucciones claras para Codex cuando una incidencia requiera código.

### 16.1 Formato de issue para Codex

```md
# Issue: Viajes asignados no aparecen en mapa

## Proyecto
Argodrive

## Prioridad
P1

## Descripción
El operador reporta que algunos viajes asignados no aparecen visualmente en el dashboard.

## Evidencia
- Mensaje original del cliente.
- Captura adjunta.
- Logs relacionados.

## Hipótesis
Puede existir inconsistencia entre trip_status y el evento enviado al frontend.

## Archivos candidatos
- backend/trips/service.py
- backend/workers/trip_events.py
- frontend/dashboard/map.tsx

## Tareas
1. Revisar flujo de asignación.
2. Validar emisión de evento websocket.
3. Agregar prueba que reproduzca el caso.
4. Proponer fix mínimo.
5. Actualizar documentación.

## Criterios de aceptación
- El viaje asignado aparece en mapa en menos de X segundos.
- No se duplican eventos.
- Se registra log claro si falla la emisión.
```

---

## 17. Roadmap por fases

## Fase 0: Preparación

Objetivo:
Organizar el repositorio y los contratos básicos.

Tareas:

- Crear repositorio `ai-ops-center`.
- Crear estructura base.
- Definir `.env.example`.
- Crear documentación inicial.
- Definir modelos de datos.
- Crear endpoint de prueba `/health`.

Resultado esperado:
Servicio base funcionando en Docker.

---

## Fase 1: Ingesta WhatsApp → FastAPI

Objetivo:
Recibir mensajes reales desde la instancia WhatsApp actual.

Tareas:

- Identificar cómo la instancia actual permite webhooks.
- Crear endpoint `/webhooks/whatsapp`.
- Guardar payload original.
- Normalizar mensajes de texto.
- Registrar mensajes en base de datos.

Resultado esperado:
Cada mensaje entrante queda guardado y visible en logs.

---

## Fase 2: Notificación Telegram simple

Objetivo:
Enviar a Telegram cada mensaje entrante con formato básico.

Tareas:

- Crear bot de Telegram.
- Configurar token.
- Crear servicio `telegram_notify.py`.
- Enviar resumen simple.

Resultado esperado:
Cuando llega un WhatsApp, Óscar recibe una notificación en Telegram.

---

## Fase 3: Clasificación IA

Objetivo:
Clasificar mensajes automáticamente.

Tareas:

- Crear `ai_classifier.py`.
- Definir prompt de clasificación.
- Clasificar proyecto, tipo y prioridad.
- Agregar resultado al mensaje de Telegram.

Resultado esperado:
Cada mensaje llega clasificado por proyecto y prioridad.

---

## Fase 4: Audio y capturas

Objetivo:
Procesar notas de voz e imágenes.

Tareas:

- Descargar media.
- Transcribir audio.
- Analizar imágenes.
- Unificar texto final.
- Enviar resumen enriquecido.

Resultado esperado:
Las notas de voz y capturas dejan de ser carga manual inicial.

---

## Fase 5: Base de conocimiento

Objetivo:
Consultar documentación e incidencias anteriores.

Tareas:

- Crear carpeta `docs/`.
- Crear esquema de knowledge items.
- Indexar documentos Markdown.
- Buscar casos similares.
- Mostrar coincidencias en Telegram.

Resultado esperado:
El sistema sugiere soluciones basadas en historial.

---

## Fase 6: Diagnóstico avanzado

Objetivo:
Generar diagnóstico estructurado.

Tareas:

- Crear `diagnosis.py`.
- Combinar mensaje + clasificación + conocimiento.
- Generar hipótesis.
- Generar pasos de verificación.
- Generar respuesta sugerida al cliente.

Resultado esperado:
Óscar recibe una explicación clara y accionable.

---

## Fase 7: Creación de issues para Codex

Objetivo:
Convertir incidencias técnicas en instrucciones listas para Codex.

Tareas:

- Crear comando Telegram `/issue`.
- Generar Markdown de issue.
- Incluir contexto, evidencia y criterios de aceptación.
- Guardar issue en carpeta o GitHub.

Resultado esperado:
El salto de soporte a desarrollo se vuelve más rápido y ordenado.

---

## Fase 8: Panel web liviano

Objetivo:
Crear dashboard para revisar incidencias.

Tareas:

- Lista de incidencias.
- Filtro por cliente/proyecto/prioridad.
- Estado: nuevo, en proceso, resuelto.
- Historial de mensajes.
- Diagnóstico IA.

Resultado esperado:
Telegram sigue siendo rápido, pero existe una consola visual.

---

## Fase 9: Automatización controlada

Objetivo:
Permitir respuestas automáticas solo en casos seguros.

Tareas:

- Identificar casos repetidos y de bajo riesgo.
- Crear plantillas aprobadas.
- Activar respuesta automática solo con alta confianza.
- Mantener auditoría.

Resultado esperado:
La IA empieza a resolver tareas simples sin intervención humana.

---

## 18. Métricas de éxito

Para saber si el sistema realmente ayuda, se deben medir resultados.

Métricas sugeridas:

- Reducir en 70% el tiempo de análisis inicial de una incidencia.
- Clasificar automáticamente 95% de mensajes entrantes.
- Transcribir 90% de notas de voz correctamente.
- Detectar proyecto correcto en 90% de los casos.
- Generar diagnóstico útil en 70% de incidencias reales.
- Documentar automáticamente 80% de soluciones cerradas.
- Reducir interrupciones manuales repetitivas.
- Crear al menos 10 artículos de conocimiento reutilizables por mes.

---

## 19. Reglas de seguridad operativa

1. La IA no responde directamente al cliente durante las primeras fases.
2. Toda respuesta externa debe ser aprobada por Óscar.
3. Las acciones destructivas deben requerir confirmación humana.
4. No se deben exponer tokens ni credenciales en logs.
5. Los archivos multimedia deben almacenarse de forma controlada.
6. Los datos sensibles de clientes deben protegerse.
7. Cada decisión de IA debe quedar trazable.
8. Se debe poder desactivar cualquier automatización rápidamente.

---

## 20. Variables de entorno sugeridas

```env
APP_ENV=development
APP_PORT=8000
DATABASE_URL=postgresql://user:password@localhost:5432/ai_ops_center
REDIS_URL=redis://localhost:6379/0
TELEGRAM_BOT_TOKEN=
TELEGRAM_ADMIN_CHAT_ID=
WHATSAPP_WEBHOOK_SECRET=
LLM_PROVIDER=openai
LLM_API_KEY=
MEDIA_STORAGE_PATH=/data/media
LOG_LEVEL=INFO
```

---

## 21. Primer MVP recomendado

El MVP no debe intentar hacerlo todo.

Debe hacer solo esto:

1. Recibir mensaje de WhatsApp.
2. Guardarlo.
3. Clasificarlo con IA.
4. Enviar resumen por Telegram.
5. Permitir marcarlo como resuelto.

### MVP mínimo

```text
WhatsApp → Webhook → FastAPI → IA clasifica → Telegram avisa
```

Sin panel web.
Sin respuesta automática.
Sin integración compleja con Git.
Sin automatización peligrosa.

Primero se valida si el sistema reduce carga diaria.

---

## 22. Prompt inicial para clasificación

```text
Eres un asistente interno de soporte técnico para una empresa de software.

Tu tarea es analizar un mensaje recibido por WhatsApp y clasificarlo.

Debes devolver JSON válido con esta estructura:

{
  "project": "argodrive|argodental|artesanias|unknown",
  "category": "BUG|SOPORTE_OPERATIVO|SOLICITUD_CAMBIO|ERROR_USUARIO|ERROR_DATOS|INCIDENTE_CRITICO|CONSULTA_GENERAL|MEJORA_PRODUCTO|FACTURACION|DESCONOCIDO",
  "priority": "P0|P1|P2|P3|P4",
  "summary": "resumen breve",
  "reason": "por qué clasificaste así",
  "needs_human_review": true
}

Reglas:
- Si no estás seguro del proyecto, usa unknown.
- Si el sistema parece caído o impide operar, usa P0 o P1.
- Si es una duda simple, usa P3.
- No inventes datos.
- Mantén el resumen claro y corto.
```

---

## 23. Prompt inicial para diagnóstico

```text
Eres un copiloto técnico interno para Óscar, arquitecto de software.

Analiza la incidencia recibida y genera un diagnóstico inicial.

Debes considerar:
- Mensaje del cliente.
- Proyecto detectado.
- Tipo de incidencia.
- Prioridad.
- Historial o conocimiento relacionado si existe.

Devuelve JSON válido:

{
  "summary": "qué está pasando",
  "probable_cause": "causa probable o desconocida",
  "confidence": 0.0,
  "recommended_steps": ["paso 1", "paso 2"],
  "suggested_reply": "respuesta sugerida para el cliente",
  "risks": ["riesgo 1"],
  "needs_human_review": true
}

Reglas:
- No digas que algo está confirmado si solo es hipótesis.
- Separa claramente diagnóstico de suposición.
- La respuesta al cliente debe ser corta, profesional y tranquila.
- No prometas tiempos exactos.
```

---

## 24. Futuro producto comercial

Después de usarlo internamente, esta plataforma puede convertirse en producto.

Nombre posible:

```text
AI Ops Center
```

Propuesta de valor:

> Plataforma que convierte WhatsApp, audios, capturas y mensajes de clientes en incidencias clasificadas, diagnósticos y acciones listas para equipos técnicos.

Clientes potenciales:

- Clínicas.
- Empresas de transporte.
- Equipos de soporte técnico.
- Agencias digitales.
- Empresas con operación por WhatsApp.
- Negocios que dependen de un técnico o dueño para resolver todo.

Modelo comercial posible:

- Setup inicial.
- Mensualidad por negocio.
- Costo adicional por volumen de mensajes.
- Plan premium con automatizaciones.

---

## 25. Diferencia entre script con IA y empresa nativa en IA

Un script creado con ayuda de IA no convierte a una empresa en nativa en IA.

Ejemplo actual:

- La IA ayudó a crear scripts para detectar anomalías o medir distancias.
- Ese código toma decisiones con reglas.
- Pero después de creado, no hay IA operando dentro del flujo.

Eso es válido, pero no es todavía empresa nativa en IA.

Una empresa nativa en IA tendría:

- Ingesta constante de datos.
- Agentes que interpretan eventos.
- Memoria operativa.
- Aprendizaje a partir de incidencias.
- Diagnósticos automáticos.
- Documentación automática.
- Integración con decisiones humanas.
- Mejora continua del sistema.

---

## 26. Reglas para Codex

Codex debe trabajar siguiendo estas reglas:

1. Leer este documento antes de proponer código.
2. Mantener arquitectura desacoplada.
3. No mezclar lógica de canal con lógica de IA.
4. Crear interfaces claras.
5. Escribir código simple y mantenible.
6. Crear pruebas para módulos críticos.
7. Usar `.env.example`, nunca credenciales reales.
8. Documentar decisiones técnicas.
9. Después de cada cambio importante, actualizar README o docs.
10. Priorizar MVP funcional antes de optimizaciones.

---

## 27. Primera instrucción sugerida para Codex

```text
Lee el archivo AI_NATIVE_COMPANY_BLUEPRINT.md completo.

Necesito iniciar el MVP de una plataforma llamada ai-ops-center.

Objetivo del MVP:
- Recibir mensajes entrantes de WhatsApp mediante webhook.
- Guardar el payload original.
- Normalizar mensajes de texto.
- Clasificar el mensaje con una capa abstracta de IA.
- Enviar una notificación resumida por Telegram.

Stack:
- Python
- FastAPI
- PostgreSQL
- SQLAlchemy
- Alembic
- Docker
- Pydantic

Reglas:
- Arquitectura desacoplada.
- Servicios separados por responsabilidad.
- No responder automáticamente al cliente.
- Crear estructura inicial del repositorio.
- Crear README.md.
- Crear .env.example.
- Crear pruebas básicas.

Entrega esperada:
- Estructura de carpetas.
- Código base ejecutable.
- docker-compose.yml.
- Endpoint /health.
- Endpoint POST /webhooks/whatsapp.
- Servicio básico de Telegram.
- Servicio abstracto de clasificación IA con implementación mock inicial.
```

---

## 28. Criterios de aceptación del MVP

El MVP se considera funcional cuando:

1. El servicio levanta con Docker.
2. `/health` responde correctamente.
3. `/webhooks/whatsapp` recibe un mensaje de prueba.
4. El mensaje queda guardado en base de datos.
5. El sistema genera clasificación mock o real.
6. Telegram recibe una notificación.
7. El código está documentado.
8. No hay respuesta automática al cliente.
9. Se puede cambiar el proveedor de IA sin tocar el webhook.
10. Se puede agregar otro canal en el futuro sin reescribir el núcleo.

---

## 29. Conclusión

Este proyecto no debe verse como un bot, sino como el primer paso para transformar la operación de Argotes en una empresa nativa en IA.

El objetivo inmediato es reducir la carga diaria de soporte.

El objetivo estratégico es construir una plataforma reutilizable que capture conocimiento, clasifique problemas, genere diagnósticos y permita operar varios clientes sin que todo dependa de una sola persona.

La dirección correcta es:

```text
Menos reacción manual.
Más conocimiento permanente.
Más automatización supervisada.
Más capacidad de escalar.
```

La IA no reemplaza el criterio de Óscar. Lo amplifica.

---

## 30. Frase guía del proyecto

> Cada mensaje de cliente debe convertirse en conocimiento, cada incidencia debe convertirse en aprendizaje, y cada solución debe fortalecer la empresa.

