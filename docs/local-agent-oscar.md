# Agente local de Oscar

Esta es la guia ejecutable para configurar y operar el lado local de Oscar. El agente
local no es el bot de Telegram, no es el backend AI Native y no es Codex. Es un puente
CLI que corre en la Mac de Oscar y conecta esas piezas.

## Que hace realmente

El worker local:

1. Se autentica en `ops.argotes.com` como `oscar` con su `OPS_WORKER_TOKEN`.
2. Lee solo tickets asignados a Oscar.
3. Recibe proyecto, `local_path` y `server_ssh_target` ya reducidos para Oscar, repo, reglas y contexto operativo.
4. Valida que existan proyecto, ruta, repositorio, contexto y evidencia suficientes.
5. Si falta informacion, pregunta por Telegram y espera respuesta sin crear una propuesta generica.
6. Puede enviar el prompt a un modelo local o dejar que Codex prepare una propuesta.
7. Sube una propuesta valida al ticket central y envia el diagnostico a Telegram para revision.
8. Lee respuestas y autorizaciones registradas en Ops.

El worker local no abre Codex automaticamente, no modifica repos de clientes, no ejecuta
deploy y no responde clientes. La implementacion es un paso separado posterior a una
autorizacion humana.

## Piezas locales

| Archivo | Funcion |
|---|---|
| `.env.oscar.local` | Configuracion privada de la Mac de Oscar |
| `scripts/ops-agent-oscar.sh` | Entrada unica para todos los comandos |
| `scripts/mac-agent.php` | Cliente comun que habla con Ops, Telegram y el modelo local |
| Repo del cliente | Ruta separada donde Codex inspecciona el proyecto asignado |

Para ArgoDrive, Ops ya tiene configurada la ruta de Oscar como
`/Users/oscarargote/ROUTIK`. El repo `argotesia-ops` puede estar en cualquier ruta; el
lanzador calcula su propia ubicacion.

El destino SSH de Oscar es independiente del de Ivan. Ops debe guardar
`server_ssh_oscar` como alias o `usuario@host`; la llave privada se configura solo en la
Mac de Oscar mediante `~/.ssh/config`. Si el ticket dice `No configurado para oscar`, no
se debe reutilizar el usuario SSH de Ivan.

El API no devuelve `local_path_ivan` ni `server_ssh_ivan` al worker de Oscar. Los campos
genericos `local_path` y `server_ssh_target` siempre corresponden al usuario autenticado.

## Configuracion inicial

Desde el clon local de `argotesia-ops`:

```bash
git switch main
git pull --ff-only origin main
chmod +x scripts/ops-agent-oscar.sh scripts/setup-oscar-agent.sh
./scripts/setup-oscar-agent.sh
```

La primera ejecucion crea `.env.oscar.local` con permisos `600` y se detiene. Oscar debe
completar por canal privado:

```env
OPS_WORKER_TOKEN=<token unico de Oscar>
TELEGRAM_AGENT_TOKEN=<token interno compartido>
```

No agregar `OPS_API_TOKEN`. Ese token es exclusivo de integraciones centrales en el VPS.

Para usar un servidor Ollama local, completar tambien:

```env
LOCAL_MODEL_URL=http://127.0.0.1:11434/api/generate
LOCAL_MODEL_NAME=<nombre exacto mostrado por ollama list>
```

Validar de nuevo:

```bash
./scripts/setup-oscar-agent.sh
```

La salida correcta contiene `Oscar worker authenticated` y nunca imprime tokens.

## Proceso local: modo modelo automatico

Este modo sirve para una primera propuesta generada por el modelo local.

```bash
./scripts/ops-agent-oscar.sh
```

Para procesar un solo ticket sin afectar el resto de la cola:

```bash
./scripts/ops-agent-oscar.sh run OPS-2026-00042
```

Proceso interno:

1. Consulta `/api/worker/tasks` como Oscar.
2. Obtiene solo tickets en estado procesable y asignados a Oscar.
3. Selecciona `local_path_oscar` del proyecto, nunca la ruta de Ivan.
4. Construye un prompt con ticket, cliente, proyecto, SSH, repo, reglas y contexto.
5. Si faltan datos, pregunta por Telegram, deja el ticket esperando respuesta y no llama al modelo.
6. Llama `LOCAL_MODEL_URL`; si el modelo falla o no responde, avisa por Telegram y no sube una plantilla.
7. Sube una propuesta valida con `source=local_model`.
8. Ops cambia el ticket a `en_revision` y registra `proposal_ready`.
9. Telegram recibe un resumen, enlace al ticket y botones para autorizar cambios o rechazar.
10. El worker termina; no implementa nada.

Para ejecutar este modo cada 60 segundos con `launchd`, primero se debe configurar un
modelo local real y despues instalar el servicio:

```bash
./scripts/install-oscar-agent.sh --install
launchctl print "gui/$(id -u)/com.argotesia.ops-agent.oscar"
```

Logs:

```text
~/Library/Logs/ArgotesIAOps/oscar.out.log
~/Library/Logs/ArgotesIAOps/oscar.err.log
```

El instalador rechaza `local-template` para evitar que un proceso residente llene tickets
con plantillas. El `LaunchAgent` solo ejecuta el modo modelo local; nunca inicia Codex.

## Proceso local: modo Codex

Este es el modo correcto cuando Codex debe revisar el repositorio real.

Este modo es manual porque Codex necesita una tarea/hilo con acceso al repo cliente y una
orden humana concreta. No se ejecuta desde `launchd`.

Primero listar tickets sin procesarlos:

```bash
./scripts/ops-agent-oscar.sh tasks > /tmp/oscar-tasks.json
cat /tmp/oscar-tasks.json
```

Para el ticket elegido, Codex debe:

1. Confirmar el codigo `OPS-...` y que el ticket esta asignado a Oscar.
2. Leer `local_path` y cambiar a ese repo cliente.
3. Leer `AGENTS.md`, README y documentacion propia del proyecto.
4. Ejecutar `git status` y preservar cambios existentes.
5. Diagnosticar en modo de solo lectura; puede ejecutar pruebas no destructivas.
6. Preparar `proposal.md` con diagnostico, archivos, plan, pruebas, riesgos y dudas.
7. No editar archivos del proyecto y no desplegar.
8. Subir la propuesta a Ops:

```bash
./scripts/ops-agent-oscar.sh submit OPS-2026-00042 proposal.md
```

El comando usa `source=codex`, deja la propuesta `ready`, mueve el ticket a `en_revision`
y registra la accion. Un borrador separado para cliente es opcional:

```bash
./scripts/ops-agent-oscar.sh submit OPS-2026-00042 proposal.md client-reply.md
```

## Preguntas y autorizacion

Si Codex necesita contexto antes de terminar la propuesta:

```bash
./scripts/ops-agent-oscar.sh ask OPS-2026-00042 "Pregunta concreta"
```

Si la propuesta ya esta lista y requiere decision:

```bash
./scripts/ops-agent-oscar.sh ask OPS-2026-00042 "Propuesta lista para revisar" --authorize
```

Despues de implementar y ejecutar pruebas, solicitar por separado autorizacion de despliegue:

```bash
./scripts/ops-agent-oscar.sh ask OPS-2026-00042 "Cambios y pruebas listos para revisar" --deploy
```

Leer respuestas nuevas:

```bash
./scripts/ops-agent-oscar.sh updates 0
```

La respuesta incluye `next_since_id`. El proceso que consulte periodicamente debe guardar
ese numero y usarlo en la siguiente llamada.

## Despues de aprobar

`/aprobar` o el boton `Autorizar cambios` registra autorizacion explicita para modificar codigo
y ejecutar pruebas, marca la propuesta `approved` y el ticket `en_progreso`.

Despues, Codex puede implementar la propuesta aprobada y ejecutar pruebas. Debe detenerse antes
del despliegue y enviar el resultado a Telegram. Solo `/aprobar-deploy` o el boton
`Autorizar despliegue` registra el segundo permiso. Ninguna aprobacion ejecuta el deploy por si sola.

## Diagnostico rapido

| Error | Causa probable | Accion |
|---|---|---|
| Pide `OPS_API_TOKEN` | Esta usando la plantilla del servidor | Volver a `.env.oscar.local` |
| `invalid worker token` | Token de otro usuario o valor viejo | Obtener el token propio de Oscar por privado |
| `worker=oscar` no aparece | `OPS_WORKER_KEY` incorrecto | Establecer exactamente `oscar` |
| No aparecen tickets | No hay tickets asignados a Oscar o estan en revision/cerrados | Revisar dashboard y asignacion |
| Modelo local vacio | Ollama/modelo no configurado | Completar URL y nombre exacto; no se suben plantillas |
| Telegram `403` | `TELEGRAM_AGENT_TOKEN` distinto | Sincronizar el token compartido por privado |
| Submit `403` | Ticket no asignado a Oscar | Reasignar en dashboard antes de subir propuesta |
| SSH no configurado | Falta `server_ssh_oscar` en el proyecto | Registrar el alias propio de Oscar; no copiar el de Ivan |

## Definition of done local

- `./scripts/setup-oscar-agent.sh` autentica como Oscar.
- `tasks` devuelve JSON valido.
- El ticket muestra la ruta local de Oscar correcta.
- `submit` crea una propuesta `codex` visible en el dashboard.
- `ask` publica en Telegram.
- `updates` recupera la respuesta o decision.
- Una propuesta valida llega a Telegram con autorizacion de cambios.
- El despliegue exige una segunda autorizacion explicita.
- Ningun secreto aparece en terminal compartida, Git o Slack.
- Ningun repo cliente se modifica antes de autorizacion.
- `launchd` se instala solo si el modelo local real esta configurado.
