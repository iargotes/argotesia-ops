# AGENTS.md - ArgotesIA Ops

## Lectura obligatoria
- Antes de explorar o cambiar codigo, leer `docs/project-context.md` y `docs/api-contract.md`.
- Verificar `git status`, `origin/main` y la ubicacion real del repo.
- Mensajes antiguos de Slack no son fuente de verdad si contradicen el codigo o `docs/project-context.md`.

## Producto
- Sistema interno para operaciones de soporte de Ivan/Oscar.
- No es el CRM comercial. Lo que funcione aqui puede replicarse luego.
- Prioridad: resolver la carga de WhatsApp y distribuir trabajo entre Ivan, Oscar y sus Macs/Codex.

## Reglas
- PHP + MySQL sin framework para velocidad de despliegue.
- PDO y prepared statements.
- Codex/modelos locales proponen; no implementan sin autorizacion humana.
- Mantener el flujo auditable: intake -> ticket -> propuesta -> aprobacion -> respuesta.
- La creacion desde intake debe ser idempotente; asignar o cambiar estado nunca inserta otro ticket.
- `OPS_API_TOKEN` es solo para integraciones centrales; cada Mac usa su propio `OPS_WORKER_TOKEN`.
- Telegram es interno y `WHATSAPP_AUTO_REPLY` debe permanecer desactivado en esta fase.

## MVP
- Intake manual/WhatsApp transcrito.
- Tickets asignados a Ivan u Oscar.
- Worker API para agentes locales en Mac.
- Propuestas tecnicas y respuestas sugeridas al cliente.

## Agente local
- El worker local es `scripts/mac-agent.php`; no es Codex ni implementa automaticamente.
- Para Oscar, leer y seguir `docs/local-agent-oscar.md`.
- Usar `tasks` para leer trabajo sin procesarlo y `submit` para subir una propuesta de Codex.
- Despues de una aprobacion, esperar una orden humana nueva antes de editar el repo cliente.
- Elegir `server_ssh_ivan` o `server_ssh_oscar` segun el worker; las llaves quedan en `~/.ssh` de cada Mac.
