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
