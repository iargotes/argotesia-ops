import logging

import httpx

from app.config import Settings, get_settings
from app.core.schemas import ClassificationResult, NormalizedMessage
from app.models.incident import Incident

logger = logging.getLogger(__name__)


class TelegramNotificationService:
    def __init__(self, settings: Settings | None = None) -> None:
        self.settings = settings or get_settings()

    async def send_text(self, chat_id: str, text: str, reply_markup: dict | None = None) -> dict:
        if not self.settings.telegram_bot_token:
            raise RuntimeError("TELEGRAM_BOT_TOKEN is not configured")

        payload = {
            "chat_id": chat_id,
            "text": text,
            "disable_web_page_preview": True,
        }
        if reply_markup is not None:
            payload["reply_markup"] = reply_markup

        url = f"https://api.telegram.org/bot{self.settings.telegram_bot_token}/sendMessage"
        async with httpx.AsyncClient(timeout=10) as client:
            response = await client.post(url, json=payload)
            response.raise_for_status()
            result = response.json()
        if not isinstance(result, dict) or not result.get("ok"):
            raise RuntimeError("Telegram sendMessage returned an invalid response")
        return result

    async def answer_callback(self, callback_query_id: str, text: str) -> None:
        if not self.settings.telegram_bot_token:
            raise RuntimeError("TELEGRAM_BOT_TOKEN is not configured")
        url = f"https://api.telegram.org/bot{self.settings.telegram_bot_token}/answerCallbackQuery"
        async with httpx.AsyncClient(timeout=10) as client:
            response = await client.post(url, json={"callback_query_id": callback_query_id, "text": text})
            response.raise_for_status()

    async def notify_incident(
        self,
        message: NormalizedMessage,
        incident: Incident,
        classification: ClassificationResult,
        ops_ticket_code: str | None = None,
    ) -> bool:
        if not self.settings.telegram_bot_token or not self.settings.telegram_admin_chat_id:
            logger.info("Telegram is not configured; skipping notification for %s.", incident.ticket_id)
            return False

        text = self._format_message(message, incident, classification, ops_ticket_code)

        try:
            await self.send_text(str(self.settings.telegram_admin_chat_id), text)
        except httpx.HTTPError:
            logger.exception("Telegram notification failed for %s.", incident.ticket_id)
            return False
        except (RuntimeError, ValueError):
            logger.exception("Telegram notification failed for %s.", incident.ticket_id)
            return False

        return True

    async def send_agent_question(
        self,
        ticket_code: str,
        question: str,
        worker_key: str,
        authorization_required: bool,
    ) -> dict:
        if not self.settings.telegram_admin_chat_id:
            raise RuntimeError("TELEGRAM_ADMIN_CHAT_ID is not configured")

        lines = [
            "AI Ops - pregunta del agente",
            f"Ticket: {ticket_code}",
            f"Agente: {worker_key}",
            "",
            question,
        ]
        reply_markup = None
        if authorization_required:
            reply_markup = {
                "inline_keyboard": [[
                    {"text": "Autorizar implementacion", "callback_data": f"ops:approve:{ticket_code}"},
                    {"text": "Rechazar", "callback_data": f"ops:reject:{ticket_code}"},
                ]]
            }
            lines.extend(["", "Usa los botones solo despues de revisar la propuesta."])

        return await self.send_text(str(self.settings.telegram_admin_chat_id), "\n".join(lines), reply_markup)

    def _format_message(
        self,
        message: NormalizedMessage,
        incident: Incident,
        classification: ClassificationResult,
        ops_ticket_code: str | None = None,
    ) -> str:
        central_ticket = f"Ticket central: {ops_ticket_code}" if ops_ticket_code else "Ticket central: pendiente de sincronizar"
        return "\n".join(
            [
                "AI Ops Center",
                "",
                f"ID: {incident.ticket_id}",
                central_ticket,
                f"Cliente: {message.contact_name or message.sender}",
                f"Proyecto: {classification.project}",
                f"Prioridad: {classification.priority}",
                f"Tipo: {classification.category}",
                "",
                "Resumen",
                classification.summary,
                "",
                "Nota",
                "Pendiente de revision humana. No se respondio automaticamente al cliente.",
            ]
        )
