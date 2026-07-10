import asyncio

from app.config import Settings
from app.services.telegram_control import TelegramControlService
from app.services.telegram_notify import TelegramNotificationService


def test_parses_explicit_telegram_commands() -> None:
    assert TelegramControlService.parse_command('/aprobar ops-2026-00042') == (
        'approve_changes', 'OPS-2026-00042', ''
    )
    assert TelegramControlService.parse_command('/rechazar OPS-2026-00042 falta evidencia') == (
        'reject', 'OPS-2026-00042', 'falta evidencia'
    )
    assert TelegramControlService.parse_command('/responder OPS-2026-00042 revisa el log') == (
        'answer', 'OPS-2026-00042', 'revisa el log'
    )
    assert TelegramControlService.parse_command('/aprobar-deploy OPS-2026-00042') == (
        'approve_deploy', 'OPS-2026-00042', ''
    )
    assert TelegramControlService.parse_command('/rechazar-deploy OPS-2026-00042 faltan pruebas') == (
        'reject_deploy', 'OPS-2026-00042', 'faltan pruebas'
    )


def test_maps_only_configured_telegram_users() -> None:
    settings = Settings(TELEGRAM_USER_WORKER_MAP='111:ivan,222:oscar')
    service = TelegramControlService(settings=settings)

    assert service.worker_for_user(111) == 'ivan'
    assert service.worker_for_user(999) is None
    assert service.chat_is_allowed('-100123') is False


def test_builds_separate_change_and_deployment_authorization_buttons(monkeypatch) -> None:
    settings = Settings(TELEGRAM_BOT_TOKEN='test-token', TELEGRAM_ADMIN_CHAT_ID='123')
    service = TelegramNotificationService(settings=settings)
    sent_payloads = []

    async def fake_send_text(chat_id, text, reply_markup=None):
        sent_payloads.append((chat_id, text, reply_markup))
        return {'ok': True, 'result': {'message_id': len(sent_payloads)}}

    monkeypatch.setattr(service, 'send_text', fake_send_text)

    asyncio.run(service.send_agent_question(
        ticket_code='OPS-2026-00042',
        question='Diagnostico listo.',
        worker_key='oscar',
        authorization_type='changes',
    ))
    asyncio.run(service.send_agent_question(
        ticket_code='OPS-2026-00042',
        question='Cambios probados; falta autorizar despliegue.',
        worker_key='oscar',
        authorization_type='deployment',
    ))

    change_buttons = sent_payloads[0][2]['inline_keyboard'][0]
    deploy_buttons = sent_payloads[1][2]['inline_keyboard'][0]
    assert change_buttons[0]['callback_data'] == 'ops:approve_changes:OPS-2026-00042'
    assert deploy_buttons[0]['callback_data'] == 'ops:approve_deploy:OPS-2026-00042'
    assert deploy_buttons[1]['callback_data'] == 'ops:reject_deploy:OPS-2026-00042'
