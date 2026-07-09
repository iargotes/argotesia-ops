from app.services.message_normalizer import MessageNormalizer


def test_normalizes_reference_payload_with_lid_sender() -> None:
    payload = {
        "chatType": "direct",
        "from": "123456789@lid",
        "pushName": "Cliente LID",
        "body": "No aparece el viaje",
        "type": "chat",
        "timestamp": 1783555200,
        "id": "lid-test-001",
    }

    normalized = MessageNormalizer().normalize(payload)

    assert normalized.message_id == "lid-test-001"
    assert normalized.sender == "123456789@lid"
    assert normalized.contact_name == "Cliente LID"
    assert normalized.text == "No aparece el viaje"
    assert normalized.message_type == "chat"

