from typing import Any

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.schemas import WebhookResponse
from app.core.security import verify_whatsapp_secret
from app.services.audio_transcription import AudioTranscriptionError, AudioTranscriptionService
from app.services.telegram_notify import TelegramNotificationService
from app.services.ops_intake import OpsIntakeError, OpsIntakeService
from app.services.whatsapp_ingestion import IgnoredWhatsAppMessage, WhatsAppIngestionService

router = APIRouter(prefix="/webhooks", tags=["webhooks"])


@router.post(
    "/whatsapp",
    response_model=WebhookResponse,
    dependencies=[Depends(verify_whatsapp_secret)],
)
async def whatsapp_webhook(payload: dict[str, Any], db: Session = Depends(get_db)) -> WebhookResponse:
    service = WhatsAppIngestionService(db)
    if AudioTranscriptionService.is_audio_payload(payload):
        try:
            preliminary = service.normalizer.normalize(payload)
        except ValueError as exc:
            raise HTTPException(
                status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                detail=str(exc),
            ) from exc
        should_process, reason = service.filter.should_process(preliminary)
        if not should_process:
            return WebhookResponse(
                status="ignored",
                message_id=preliminary.message_id,
                reason=reason,
                reply_to_customer=None,
            )
        try:
            payload, _transcription = await AudioTranscriptionService().transcribe_payload(payload)
        except AudioTranscriptionError as exc:
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=str(exc),
            ) from exc

    try:
        normalized, _db_message, classification, incident = service.process(payload)
    except IgnoredWhatsAppMessage as exc:
        return WebhookResponse(
            status="ignored",
            message_id=exc.message.message_id,
            reason=exc.reason,
            reply_to_customer=None,
        )
    except ValueError as exc:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail=str(exc)) from exc

    try:
        ops_result = await OpsIntakeService().send(normalized, classification)
    except OpsIntakeError as exc:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail=str(exc)) from exc

    await TelegramNotificationService().notify_incident(
        normalized,
        incident,
        classification,
        ops_ticket_code=ops_result.get('ticket_code') if ops_result else None,
    )

    return WebhookResponse(
        status="accepted",
        message_id=normalized.message_id,
        incident_id=incident.ticket_id,
        classification=classification,
        reply_to_customer=None,
        ops_intake_id=ops_result.get("intake_id") if ops_result else None,
        ops_ticket_id=ops_result.get("ticket_id") if ops_result else None,
        ops_ticket_code=ops_result.get("ticket_code") if ops_result else None,
    )
