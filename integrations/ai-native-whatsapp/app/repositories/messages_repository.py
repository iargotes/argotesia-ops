from sqlalchemy.orm import Session

from app.core.schemas import NormalizedMessage
from app.models.message import Message


class MessagesRepository:
    def __init__(self, db: Session):
        self.db = db

    def get_by_message_id(self, message_id: str) -> Message | None:
        return self.db.query(Message).filter(Message.message_id == message_id).first()

    def create_or_get(self, message: NormalizedMessage) -> Message:
        existing = self.get_by_message_id(message.message_id)
        if existing:
            return existing

        db_message = Message(
            source_channel=message.source_channel,
            message_id=message.message_id,
            sender=message.sender,
            contact_name=message.contact_name,
            message_type=message.message_type,
            text=message.text,
            media_url=message.media_url,
            received_at=message.received_at,
            raw_payload=message.raw_payload,
        )
        self.db.add(db_message)
        self.db.flush()
        return db_message

