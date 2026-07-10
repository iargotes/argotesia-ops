import logging
from typing import Any

import httpx

from app.config import Settings, get_settings

logger = logging.getLogger(__name__)


class OpsActionError(RuntimeError):
    """Raised when an internal ticket action cannot be recorded."""


class OpsTicketActionClient:
    def __init__(self, settings: Settings | None = None) -> None:
        self.settings = settings or get_settings()

    async def execute(
        self,
        *,
        ticket_code: str,
        action: str,
        worker_key: str,
        body: str = '',
        telegram_user_id: str = '',
        telegram_username: str = '',
    ) -> dict[str, Any]:
        if not self.settings.ops_ticket_actions_url or not self.settings.ops_api_token:
            raise OpsActionError("OPS_TICKET_ACTIONS_URL and OPS_API_TOKEN must be configured together.")

        payload = {
            "ticket_code": ticket_code,
            "action": action,
            "worker_key": worker_key,
            "body": body,
            "telegram_user_id": telegram_user_id,
            "telegram_username": telegram_username,
        }
        headers = {
            "Authorization": f"Bearer {self.settings.ops_api_token}",
            "Content-Type": "application/json",
        }

        try:
            async with httpx.AsyncClient(timeout=self.settings.ops_timeout_seconds) as client:
                response = await client.post(self.settings.ops_ticket_actions_url, headers=headers, json=payload)
                response.raise_for_status()
                result = response.json()
        except (httpx.HTTPError, ValueError) as exc:
            logger.error("ArgotesIA Ops Telegram action failed with %s.", type(exc).__name__)
            raise OpsActionError("ArgotesIA Ops ticket action failed.") from exc

        if not isinstance(result, dict) or not result.get("ok"):
            raise OpsActionError("ArgotesIA Ops returned an invalid ticket action response.")
        return result
