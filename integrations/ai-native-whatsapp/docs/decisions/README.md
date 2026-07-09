# Registro de decisiones

Este directorio guarda decisiones tecnicas importantes para que el proyecto no dependa de memoria informal.

## Formato sugerido

```md
# ADR-0001: Titulo de la decision

## Fecha
YYYY-MM-DD

## Contexto
Problema o situacion.

## Decision
Que se decidio.

## Consecuencias
Ventajas, riesgos y trabajo futuro.
```

## Decisiones iniciales

- [ADR-0001: Separar conector WhatsApp del backend FastAPI](ADR-0001-whatsapp-connector-separated.md)
- El MVP sera interno y supervisado.
- Telegram sera la consola inicial para Oscar.
- WhatsApp se mantiene como canal principal de entrada.
- FastAPI sera el backend inicial.
- La clasificacion IA debe vivir detras de una interfaz reemplazable.
- No habra respuesta automatica al cliente en las primeras fases.
