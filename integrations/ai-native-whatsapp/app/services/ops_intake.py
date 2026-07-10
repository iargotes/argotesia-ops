import logging
from typing import Any

import httpx

from app.config import Settings, get_settings
from app.core.schemas import ClassificationResult, NormalizedMessage

logger = logging.getLogger(__name__)


class OpsIntakeError(RuntimeError):
    """Raised when the central ArgotesIA Ops intake cannot be reached."""


class OpsIntakeService:
    def __init__(self, settings: Settings | None = None) -> None:
        self.settings = settings or get_settings()

    def is_configured(self) -> bool:
        return bool(self.settings.ops_intake_url or self.settings.ops_api_token)

    def build_payload(
        self,
        message: NormalizedMessage,
        classification: ClassificationResult,
    ) -> dict[str, Any]:
        transcript = (message.text or "").strip()
        if not transcript:
            raise OpsIntakeError("WhatsApp message has no transcript text.")

        payload: dict[str, Any] = {
            "source_channel": "audio" if message.message_type in {"ptt", "audio"} else "whatsapp",
            "external_ref": f"whatsapp:{message.sender}:{message.message_id}",
            "client_name": message.contact_name,
            "client_contact": message.sender,
            "title": classification.summary[:180],
            "transcript": transcript,
            "raw_notes": (
                f"Clasificacion local: proyecto={classification.project}; "
                f"categoria={classification.category}; prioridad={classification.priority}; "
                f"razon={classification.reason}"
            ),
            "received_at": message.received_at.isoformat(),
            "create_ticket": self.settings.ops_create_ticket,
        }

        if message.message_type in {"ptt", "audio"}:
            media = message.raw_payload.get("media")
            transcription = message.raw_payload.get("transcription")
            payload["audio"] = {
                "mime_type": media.get("mimetype") if isinstance(media, dict) else None,
                "duration_seconds": (
                    transcription.get("duration_seconds") if isinstance(transcription, dict) else None
                ),
            }

        if self.settings.ops_project_key:
            payload["project_key"] = self.settings.ops_project_key
        if self.settings.ops_assigned_worker_key:
            payload["assigned_worker_key"] = self.settings.ops_assigned_worker_key

        return payload

    async def send(
        self,
        message: NormalizedMessage,
        classification: ClassificationResult,
    ) -> dict[str, Any] | None:
        if not self.is_configured():
            logger.info("ArgotesIA Ops intake is not configured; skipping central ticket.")
            return None

        if not self.settings.ops_intake_url or not self.settings.ops_api_token:
            raise OpsIntakeError("OPS_INTAKE_URL and OPS_API_TOKEN must be configured together.")

        payload = self.build_payload(message, classification)
        headers = {
            "Authorization": f"Bearer {self.settings.ops_api_token}",
            "Content-Type": "application/json",
        }

        try:
            async with httpx.AsyncClient(timeout=self.settings.ops_timeout_seconds) as client:
                response = await client.post(self.settings.ops_intake_url, headers=headers, json=payload)
                response.raise_for_status()
                result = response.json()
        except (httpx.HTTPError, ValueError) as exc:
            logger.error("ArgotesIA Ops intake failed with %s.", type(exc).__name__)
            raise OpsIntakeError("ArgotesIA Ops intake request failed.") from exc

        if not isinstance(result, dict) or not result.get("ok"):
            raise OpsIntakeError("ArgotesIA Ops intake returned an invalid response.")

        return result
