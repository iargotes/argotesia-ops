import secrets
from typing import Any, Literal

from fastapi import APIRouter, Header, HTTPException, Request, status
from pydantic import BaseModel, Field

from app.config import get_settings
from app.services.ops_actions import OpsActionError
from app.services.telegram_control import TelegramControlService

router = APIRouter(tags=['telegram'])


class AgentQuestionRequest(BaseModel):
    ticket_code: str = Field(min_length=1, max_length=40)
    question: str = Field(min_length=1, max_length=4000)
    worker_key: str = Field(min_length=1, max_length=40)
    authorization_required: bool = False
    authorization_type: Literal['none', 'changes', 'deployment'] = 'none'


def require_agent_token(authorization: str | None) -> None:
    settings = get_settings()
    expected = (settings.telegram_agent_token or '').strip()
    if not expected:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail='telegram agent token not configured')
    received = ''
    if authorization and authorization.lower().startswith('bearer '):
        received = authorization[7:].strip()
    if not received or not secrets.compare_digest(received, expected):
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail='invalid telegram agent token')


@router.post('/webhooks/telegram')
async def telegram_webhook(
    request: Request,
    x_telegram_bot_api_secret_token: str | None = Header(default=None, alias='X-Telegram-Bot-Api-Secret-Token'),
) -> dict[str, Any]:
    settings = get_settings()
    expected = (settings.telegram_webhook_secret or '').strip()
    if not expected:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail='telegram webhook secret not configured')
    if not x_telegram_bot_api_secret_token or not secrets.compare_digest(x_telegram_bot_api_secret_token, expected):
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail='invalid telegram webhook secret')

    payload = await request.json()
    if not isinstance(payload, dict):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail='invalid telegram update')

    try:
        result = await TelegramControlService().handle_update(payload)
    except OpsActionError as exc:
        raise HTTPException(status_code=status.HTTP_502_BAD_GATEWAY, detail=str(exc)) from exc
    return {'ok': True, **result}


@router.post('/internal/telegram/questions')
async def agent_question(
    request: AgentQuestionRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    require_agent_token(authorization)
    authorization_type = request.authorization_type
    if request.authorization_required and authorization_type == 'none':
        authorization_type = 'changes'
    try:
        result = await TelegramControlService().publish_agent_question(
            ticket_code=request.ticket_code.upper(),
            question=request.question,
            worker_key=request.worker_key.lower(),
            authorization_type=authorization_type,
        )
    except OpsActionError as exc:
        raise HTTPException(status_code=status.HTTP_502_BAD_GATEWAY, detail=str(exc)) from exc
    return {'ok': True, **result}
