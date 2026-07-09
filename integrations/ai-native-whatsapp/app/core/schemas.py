from datetime import datetime, timezone
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field


class HealthResponse(BaseModel):
    status: Literal["ok"]
    service: str
    environment: str


class NormalizedMessage(BaseModel):
    source_channel: Literal["whatsapp"] = "whatsapp"
    message_id: str
    sender: str
    contact_name: str | None = None
    message_type: str
    text: str | None = None
    media_url: str | None = None
    received_at: datetime
    raw_payload: dict[str, Any]


class ClassificationResult(BaseModel):
    project: Literal["argodrive", "argodental", "artesanias", "unknown"]
    category: Literal[
        "BUG",
        "SOPORTE_OPERATIVO",
        "SOLICITUD_CAMBIO",
        "ERROR_USUARIO",
        "ERROR_DATOS",
        "INCIDENTE_CRITICO",
        "CONSULTA_GENERAL",
        "MEJORA_PRODUCTO",
        "FACTURACION",
        "DESCONOCIDO",
    ]
    priority: Literal["P0", "P1", "P2", "P3", "P4"]
    summary: str
    reason: str
    needs_human_review: bool = True


class WebhookResponse(BaseModel):
    status: Literal["accepted", "ignored"]
    message_id: str | None = None
    incident_id: str | None = None
    classification: ClassificationResult | None = None
    reason: str | None = None
    reply_to_customer: None = None


class WhatsAppReferencePayload(BaseModel):
    model_config = ConfigDict(populate_by_name=True)

    chat_type: str = Field(alias="chatType")
    from_: str = Field(alias="from")
    push_name: str | None = Field(default=None, alias="pushName")
    body: str | None = None
    type: str
    timestamp: int | datetime
    id: str

    def to_normalized(self, raw_payload: dict[str, Any]) -> NormalizedMessage:
        received_at = self.timestamp
        if isinstance(received_at, int):
            received_at = datetime.fromtimestamp(received_at, tz=timezone.utc)

        return NormalizedMessage(
            message_id=self.id,
            sender=self.from_,
            contact_name=self.push_name,
            message_type=self.type,
            text=self.body,
            received_at=received_at,
            raw_payload=raw_payload,
        )


class GenericWebhookPayload(BaseModel):
    message_id: str
    from_: str = Field(alias="from")
    contact_name: str | None = None
    timestamp: datetime
    message_type: str
    text: str | None = None
    media_url: str | None = None
    raw_payload: dict[str, Any] = Field(default_factory=dict)

    model_config = ConfigDict(populate_by_name=True)

    def to_normalized(self, raw_payload: dict[str, Any]) -> NormalizedMessage:
        return NormalizedMessage(
            message_id=self.message_id,
            sender=self.from_,
            contact_name=self.contact_name,
            message_type=self.message_type,
            text=self.text,
            media_url=self.media_url,
            received_at=self.timestamp,
            raw_payload=raw_payload or self.raw_payload,
        )
