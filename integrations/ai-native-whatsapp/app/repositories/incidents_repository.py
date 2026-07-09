from datetime import datetime, timezone

from sqlalchemy.orm import Session

from app.core.schemas import ClassificationResult
from app.models.incident import Incident
from app.models.message import Message


class IncidentsRepository:
    def __init__(self, db: Session):
        self.db = db

    def get_by_message(self, message: Message) -> Incident | None:
        return self.db.query(Incident).filter(Incident.message_db_id == message.id).first()

    def create_or_get(self, message: Message, classification: ClassificationResult) -> Incident:
        existing = self.get_by_message(message)
        if existing:
            return existing

        ticket_id = f"INC-{datetime.now(timezone.utc).strftime('%Y%m%d')}-{message.id:04d}"
        incident = Incident(
            ticket_id=ticket_id,
            message_db_id=message.id,
            project=classification.project,
            category=classification.category,
            priority=classification.priority,
            summary=classification.summary,
            reason=classification.reason,
            needs_human_review=classification.needs_human_review,
        )
        self.db.add(incident)
        self.db.flush()
        return incident

