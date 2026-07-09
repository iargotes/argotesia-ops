from typing import Any

from pydantic import ValidationError

from app.core.schemas import GenericWebhookPayload, NormalizedMessage, WhatsAppReferencePayload


class MessageNormalizer:
    def normalize(self, payload: dict[str, Any]) -> NormalizedMessage:
        if self._looks_like_reference_payload(payload):
            try:
                return WhatsAppReferencePayload.model_validate(payload).to_normalized(payload)
            except ValidationError as exc:
                raise ValueError(f"Invalid WhatsApp reference payload: {exc}") from exc

        try:
            return GenericWebhookPayload.model_validate(payload).to_normalized(payload)
        except ValidationError as exc:
            raise ValueError(f"Unsupported webhook payload: {exc}") from exc

    def _looks_like_reference_payload(self, payload: dict[str, Any]) -> bool:
        return {"chatType", "from", "type", "timestamp", "id"}.issubset(payload)

