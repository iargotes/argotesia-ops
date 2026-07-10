from app.config import Settings
from app.services.telegram_control import TelegramControlService


def test_parses_explicit_telegram_commands() -> None:
    assert TelegramControlService.parse_command('/aprobar ops-2026-00042') == (
        'approve', 'OPS-2026-00042', ''
    )
    assert TelegramControlService.parse_command('/rechazar OPS-2026-00042 falta evidencia') == (
        'reject', 'OPS-2026-00042', 'falta evidencia'
    )
    assert TelegramControlService.parse_command('/responder OPS-2026-00042 revisa el log') == (
        'answer', 'OPS-2026-00042', 'revisa el log'
    )


def test_maps_only_configured_telegram_users() -> None:
    settings = Settings(TELEGRAM_USER_WORKER_MAP='111:ivan,222:oscar')
    service = TelegramControlService(settings=settings)

    assert service.worker_for_user(111) == 'ivan'
    assert service.worker_for_user(999) is None
    assert service.chat_is_allowed('-100123') is False
