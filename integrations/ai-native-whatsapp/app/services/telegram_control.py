import logging
from typing import Any

from app.config import Settings, get_settings
from app.services.ops_actions import OpsTicketActionClient
from app.services.telegram_notify import TelegramNotificationService

logger = logging.getLogger(__name__)


class TelegramControlService:
    def __init__(
        self,
        settings: Settings | None = None,
        telegram: TelegramNotificationService | None = None,
        actions: OpsTicketActionClient | None = None,
    ) -> None:
        self.settings = settings or get_settings()
        self.telegram = telegram or TelegramNotificationService(self.settings)
        self.actions = actions or OpsTicketActionClient(self.settings)

    @staticmethod
    def parse_command(text: str) -> tuple[str, str, str] | None:
        parts = text.strip().split(maxsplit=2)
        if not parts:
            return None
        command = parts[0].split('@', 1)[0].lower()
        if command in {'/ayuda', '/help'}:
            return ('help', '', '')
        if command in {'/aprobar', '/autorizar', '/aprobar-cambios', '/autorizar-cambios'} and len(parts) >= 2:
            return ('approve_changes', parts[1].upper(), parts[2] if len(parts) == 3 else '')
        if command in {'/aprobar-deploy', '/autorizar-deploy'} and len(parts) >= 2:
            return ('approve_deploy', parts[1].upper(), parts[2] if len(parts) == 3 else '')
        if command == '/rechazar' and len(parts) >= 2:
            return ('reject', parts[1].upper(), parts[2] if len(parts) == 3 else 'Rechazo solicitado por Telegram.')
        if command == '/rechazar-deploy' and len(parts) >= 2:
            return ('reject_deploy', parts[1].upper(), parts[2] if len(parts) == 3 else 'Despliegue rechazado por Telegram.')
        if command in {'/responder', '/respuesta'} and len(parts) >= 3:
            return ('answer', parts[1].upper(), parts[2])
        return None

    def worker_for_user(self, user_id: Any) -> str | None:
        return self.settings.telegram_workers_by_user.get(str(user_id))

    def chat_is_allowed(self, chat_id: Any) -> bool:
        configured_chat = self.settings.telegram_admin_chat_id
        return bool(configured_chat and str(chat_id) == str(configured_chat))

    async def publish_agent_question(
        self,
        *,
        ticket_code: str,
        question: str,
        worker_key: str,
        authorization_type: str,
    ) -> dict[str, Any]:
        message = await self.telegram.send_agent_question(
            ticket_code=ticket_code,
            question=question,
            worker_key=worker_key,
            authorization_type=authorization_type,
        )
        await self.actions.execute(
            ticket_code=ticket_code,
            action='question',
            worker_key=worker_key,
            body=question,
        )
        return {
            'ticket_code': ticket_code,
            'telegram_message_id': message.get('result', {}).get('message_id'),
        }

    async def handle_update(self, update: dict[str, Any]) -> dict[str, Any]:
        callback = update.get('callback_query')
        if isinstance(callback, dict):
            return await self._handle_callback(callback)

        message = update.get('message')
        if isinstance(message, dict):
            return await self._handle_message(message)

        return {'handled': False, 'reason': 'unsupported_update'}

    async def _handle_callback(self, callback: dict[str, Any]) -> dict[str, Any]:
        sender = callback.get('from') or {}
        worker_key = self.worker_for_user(sender.get('id'))
        message = callback.get('message') or {}
        if not worker_key or not self.chat_is_allowed((message.get('chat') or {}).get('id')):
            return {'handled': False, 'reason': 'unauthorized'}

        data = str(callback.get('data') or '')
        prefix, action, ticket_code = (data.split(':', 2) + ['', '', ''])[:3]
        if prefix != 'ops' or action not in {'approve_changes', 'approve_deploy', 'reject', 'reject_deploy'} or not ticket_code:
            return {'handled': False, 'reason': 'unsupported_callback'}

        result = await self.actions.execute(
            ticket_code=ticket_code.upper(),
            action=action,
            worker_key=worker_key,
            body='Accion confirmada desde boton de Telegram.',
            telegram_user_id=str(sender.get('id') or ''),
            telegram_username=str(sender.get('username') or ''),
        )
        callback_id = str(callback.get('id') or '')
        if callback_id:
            await self.telegram.answer_callback(callback_id, 'Accion registrada.')
        await self.telegram.send_text(
            str(message['chat']['id']),
            f"Ticket {ticket_code.upper()}: {self.action_label(action)}. Estado: {result.get('ticket_status', 'actualizado')}.",
        )
        return {'handled': True, 'action': action, 'ticket_code': ticket_code.upper()}

    async def _handle_message(self, message: dict[str, Any]) -> dict[str, Any]:
        chat = message.get('chat') or {}
        sender = message.get('from') or {}
        if not self.chat_is_allowed(chat.get('id')):
            return {'handled': False, 'reason': 'chat_not_allowed'}

        worker_key = self.worker_for_user(sender.get('id'))
        if not worker_key:
            return {'handled': False, 'reason': 'user_not_allowed'}

        command = self.parse_command(str(message.get('text') or ''))
        if command is None:
            return {'handled': False, 'reason': 'not_a_control_command'}
        action, ticket_code, body = command
        if action == 'help':
            await self.telegram.send_text(str(chat['id']), self.help_text())
            return {'handled': True, 'action': 'help'}

        result = await self.actions.execute(
            ticket_code=ticket_code,
            action=action,
            worker_key=worker_key,
            body=body,
            telegram_user_id=str(sender.get('id') or ''),
            telegram_username=str(sender.get('username') or ''),
        )
        await self.telegram.send_text(
            str(chat['id']),
            f"Ticket {ticket_code}: {self.action_label(action)}. Estado: {result.get('ticket_status', 'sin cambio')}.",
        )
        return {'handled': True, 'action': action, 'ticket_code': ticket_code}

    @staticmethod
    def help_text() -> str:
        return (
            "Comandos ArgotesIA Ops:\n"
            "/aprobar OPS-2026-00042  (autoriza cambios y pruebas)\n"
            "/rechazar OPS-2026-00042 motivo\n"
            "/responder OPS-2026-00042 texto\n"
            "/aprobar-deploy OPS-2026-00042\n"
            "/rechazar-deploy OPS-2026-00042 motivo\n"
            "/ayuda"
        )

    @staticmethod
    def action_label(action: str) -> str:
        return {
            'approve_changes': 'cambios autorizados',
            'approve_deploy': 'despliegue autorizado',
            'reject': 'propuesta rechazada',
            'reject_deploy': 'despliegue rechazado',
            'answer': 'respuesta registrada',
        }.get(action, f'{action} registrado')
