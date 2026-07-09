# AGENTS.md - ArgotesIA Ops

## Producto
- Sistema interno para operaciones de soporte de Ivan/Oscar.
- No es el CRM comercial. Lo que funcione aqui puede replicarse luego.
- Prioridad: resolver la carga de WhatsApp y distribuir trabajo entre Ivan, Oscar y sus Macs/Codex.

## Reglas
- PHP + MySQL sin framework para velocidad de despliegue.
- PDO y prepared statements.
- Codex/modelos locales proponen; no implementan sin autorizacion humana.
- Mantener el flujo auditable: intake -> ticket -> propuesta -> aprobacion -> respuesta.

## MVP
- Intake manual/WhatsApp transcrito.
- Tickets asignados a Ivan u Oscar.
- Worker API para agentes locales en Mac.
- Propuestas tecnicas y respuestas sugeridas al cliente.

