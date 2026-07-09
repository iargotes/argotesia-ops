from typing import Any

from sqlalchemy.orm import Session

from app.core.schemas import ClassificationResult, NormalizedMessage
from app.models.incident import Incident
from app.models.message import Message
from app.repositories.incidents_repository import IncidentsRepository
from app.repositories.messages_repository import MessagesRepository
from app.services.ai_classifier import get_classifier
from app.services.whatsapp_filter import WhatsAppMessageFilter
from app.services.message_normalizer import MessageNormalizer


class WhatsAppIngestionService:
    def __init__(self, db: Session) -> None:
        self.db = db
        self.normalizer = MessageNormalizer()
        self.classifier = get_classifier()
        self.filter = WhatsAppMessageFilter()
        self.messages = MessagesRepository(db)
        self.incidents = IncidentsRepository(db)

    def process(self, payload: dict[str, Any]) -> tuple[NormalizedMessage, Message, ClassificationResult, Incident]:
        normalized = self.normalizer.normalize(payload)
        should_process, reason = self.filter.should_process(normalized)
        if not should_process:
            raise IgnoredWhatsAppMessage(normalized, reason or "Mensaje ignorado por filtros de WhatsApp.")

        db_message = self.messages.create_or_get(normalized)
        classification = self.classifier.classify(normalized)
        incident = self.incidents.create_or_get(db_message, classification)
        self.db.commit()
        self.db.refresh(db_message)
        self.db.refresh(incident)
        return normalized, db_message, classification, incident


class IgnoredWhatsAppMessage(Exception):
    def __init__(self, message: NormalizedMessage, reason: str) -> None:
        super().__init__(reason)
        self.message = message
        self.reason = reason
