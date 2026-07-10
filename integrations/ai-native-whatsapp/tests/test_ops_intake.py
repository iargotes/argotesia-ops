from datetime import datetime, timezone

from app.config import Settings
from app.core.schemas import ClassificationResult, NormalizedMessage
from app.services.ops_intake import OpsIntakeService


def test_builds_idempotent_ops_payload_without_customer_reply() -> None:
    settings = Settings(
        OPS_INTAKE_URL="https://ops.argotes.com/index.php?r=%2Fapi%2Fintake%2Fmessages",
        OPS_API_TOKEN="test-token",
        OPS_CREATE_TICKET=True,
    )
    service = OpsIntakeService(settings=settings)
    message = NormalizedMessage(
        message_id="wamid-001",
        sender="50760000000@c.us",
        contact_name="Cliente",
        message_type="chat",
        text="No me aparece el viaje en el mapa.",
        received_at=datetime(2026, 7, 9, 20, 0, tzinfo=timezone.utc),
        raw_payload={},
    )
    classification = ClassificationResult(
        project="argodrive",
        category="BUG",
        priority="P2",
        summary="No me aparece el viaje en el mapa.",
        reason="Prueba",
    )

    payload = service.build_payload(message, classification)

    assert payload["external_ref"] == "whatsapp:50760000000@c.us:wamid-001"
    assert payload["transcript"] == "No me aparece el viaje en el mapa."
    assert payload["create_ticket"] is True
    assert "client_reply" not in payload


def test_builds_ops_payload_for_transcribed_whatsapp_audio() -> None:
    settings = Settings(
        OPS_INTAKE_URL="https://ops.argotes.com/index.php?r=%2Fapi%2Fintake%2Fmessages",
        OPS_API_TOKEN="test-token",
    )
    service = OpsIntakeService(settings=settings)
    message = NormalizedMessage(
        message_id="audio-001",
        sender="50700000000@c.us",
        contact_name="Cliente",
        message_type="ptt",
        text="Necesito ayuda con la ruta",
        received_at=datetime(2026, 7, 9, 20, 0, tzinfo=timezone.utc),
        raw_payload={
            "media": {"mimetype": "audio/ogg", "size_bytes": 1200},
            "transcription": {"duration_seconds": 4.2},
        },
    )
    classification = ClassificationResult(
        project="argodrive",
        category="SOPORTE_OPERATIVO",
        priority="P3",
        summary="Necesito ayuda con la ruta",
        reason="Prueba",
    )

    payload = service.build_payload(message, classification)

    assert payload["source_channel"] == "audio"
    assert payload["transcript"] == message.text
    assert payload["audio"] == {"mime_type": "audio/ogg", "duration_seconds": 4.2}
