from uuid import uuid4

from fastapi.testclient import TestClient

from app.config import get_settings
from app.main import app


def test_whatsapp_webhook_accepts_reference_payload_without_customer_reply(monkeypatch) -> None:
    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "")
    monkeypatch.setenv("WHATSAPP_WEBHOOK_SECRET", "")
    get_settings.cache_clear()
    client = TestClient(app)
    message_id = f"test-{uuid4()}"

    response = client.post(
        "/webhooks/whatsapp",
        json={
            "chatType": "direct",
            "from": "50700000000@c.us",
            "pushName": "Cliente de prueba",
            "body": "No me aparece el viaje asignado en el mapa",
            "type": "chat",
            "timestamp": 1783555200,
            "id": message_id,
        },
    )

    body = response.json()

    assert response.status_code == 200
    assert body["status"] == "accepted"
    assert body["message_id"] == message_id
    assert body["incident_id"].startswith("INC-")
    assert body["reply_to_customer"] is None
    assert body["classification"]["project"] == "argodrive"
    assert body["classification"]["priority"] == "P2"


def test_whatsapp_webhook_ignores_sender_outside_allowlist(monkeypatch) -> None:
    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "50711111111@c.us")
    monkeypatch.setenv("WHATSAPP_WEBHOOK_SECRET", "")
    get_settings.cache_clear()
    client = TestClient(app)

    response = client.post(
        "/webhooks/whatsapp",
        json={
            "chatType": "direct",
            "from": "50700000000@c.us",
            "pushName": "Cliente fuera de lista",
            "body": "Mensaje que no debe entrar",
            "type": "chat",
            "timestamp": 1783555200,
            "id": f"ignored-{uuid4()}",
        },
    )

    body = response.json()

    assert response.status_code == 200
    assert body["status"] == "ignored"
    assert body["incident_id"] is None
    assert body["classification"] is None
    assert body["reply_to_customer"] is None
    assert "WHATSAPP_ALLOWED_SENDERS" in body["reason"]

    monkeypatch.delenv("WHATSAPP_ALLOWED_SENDERS")
    get_settings.cache_clear()
