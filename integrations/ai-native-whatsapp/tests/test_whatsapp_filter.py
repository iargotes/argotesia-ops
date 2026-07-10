from app.config import get_settings
from app.core.schemas import NormalizedMessage
from app.services.whatsapp_filter import WhatsAppMessageFilter


def test_filter_allows_matching_digits_from_c_us(monkeypatch) -> None:
    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "50700000000")
    get_settings.cache_clear()

    message = NormalizedMessage(
        message_id="allowed-001",
        sender="50700000000@c.us",
        contact_name="Cliente",
        message_type="chat",
        text="Necesito soporte",
        received_at="2026-07-09T00:00:00Z",
        raw_payload={"chatType": "direct"},
    )

    should_process, reason = WhatsAppMessageFilter().should_process(message)

    assert should_process is True
    assert reason is None

    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "")
    get_settings.cache_clear()


def test_filter_requires_exact_lid_when_lid_has_no_known_phone(monkeypatch) -> None:
    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "known-client@lid")
    get_settings.cache_clear()

    message = NormalizedMessage(
        message_id="lid-001",
        sender="other-client@lid",
        contact_name="Cliente",
        message_type="chat",
        text="Necesito soporte",
        received_at="2026-07-09T00:00:00Z",
        raw_payload={"chatType": "direct"},
    )

    should_process, reason = WhatsAppMessageFilter().should_process(message)

    assert should_process is False
    assert reason is not None

    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "")
    get_settings.cache_clear()


def test_filter_allows_audio_from_matching_sender(monkeypatch) -> None:
    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "50700000000")
    get_settings.cache_clear()

    message = NormalizedMessage(
        message_id="audio-001",
        sender="50700000000@c.us",
        contact_name="Cliente",
        message_type="ptt",
        text=None,
        received_at="2026-07-09T00:00:00Z",
        raw_payload={"chatType": "direct"},
    )

    should_process, reason = WhatsAppMessageFilter().should_process(message)

    assert should_process is True
    assert reason is None

    monkeypatch.setenv("WHATSAPP_ALLOWED_SENDERS", "")
    get_settings.cache_clear()
