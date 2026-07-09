from app.config import get_settings
from app.core.schemas import NormalizedMessage


class WhatsAppMessageFilter:
    def __init__(self) -> None:
        self.settings = get_settings()

    def should_process(self, message: NormalizedMessage) -> tuple[bool, str | None]:
        if message.raw_payload.get("chatType") not in {None, "direct"}:
            return False, "Mensaje ignorado porque no es chat directo."

        if message.message_type != "chat":
            return False, "Mensaje ignorado porque no es texto tipo chat."

        allowed_senders = self.settings.allowed_whatsapp_senders
        if not allowed_senders:
            return True, None

        if self._sender_is_allowed(message.sender, allowed_senders):
            return True, None

        return False, "Mensaje ignorado porque el remitente no esta en WHATSAPP_ALLOWED_SENDERS."

    def _sender_is_allowed(self, sender: str, allowed_senders: set[str]) -> bool:
        normalized_sender = sender.strip().lower()
        sender_digits = self._digits_only(normalized_sender)

        for allowed in allowed_senders:
            if normalized_sender == allowed:
                return True
            if sender_digits and sender_digits == self._digits_only(allowed):
                return True
        return False

    def _digits_only(self, value: str) -> str:
        return "".join(character for character in value if character.isdigit())
