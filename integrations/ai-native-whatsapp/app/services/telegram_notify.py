import logging

import httpx

from app.config import get_settings
from app.core.schemas import ClassificationResult, NormalizedMessage
from app.models.incident import Incident

logger = logging.getLogger(__name__)


class TelegramNotificationService:
    def __init__(self) -> None:
        self.settings = get_settings()

    async def notify_incident(
        self,
        message: NormalizedMessage,
        incident: Incident,
        classification: ClassificationResult,
    ) -> bool:
        if not self.settings.telegram_bot_token or not self.settings.telegram_admin_chat_id:
            logger.info("Telegram is not configured; skipping notification for %s.", incident.ticket_id)
            return False

        text = self._format_message(message, incident, classification)
        url = f"https://api.telegram.org/bot{self.settings.telegram_bot_token}/sendMessage"
        payload = {
            "chat_id": self.settings.telegram_admin_chat_id,
            "text": text,
            "parse_mode": "HTML",
            "disable_web_page_preview": True,
        }

        try:
            async with httpx.AsyncClient(timeout=10) as client:
                response = await client.post(url, json=payload)
                response.raise_for_status()
        except httpx.HTTPError:
            logger.exception("Telegram notification failed for %s.", incident.ticket_id)
            return False

        return True

    def _format_message(
        self,
        message: NormalizedMessage,
        incident: Incident,
        classification: ClassificationResult,
    ) -> str:
        return "\n".join(
            [
                "<b>AI Ops Center</b>",
                "",
                f"ID: {incident.ticket_id}",
                f"Cliente: {message.contact_name or message.sender}",
                f"Proyecto: {classification.project}",
                f"Prioridad: {classification.priority}",
                f"Tipo: {classification.category}",
                "",
                "<b>Resumen</b>",
                classification.summary,
                "",
                "<b>Nota</b>",
                "Pendiente de revision humana. No se respondio automaticamente al cliente.",
            ]
        )

