from fastapi import Header, HTTPException, status

from app.config import get_settings


def verify_whatsapp_secret(x_webhook_secret: str | None = Header(default=None)) -> None:
    expected_secret = get_settings().whatsapp_webhook_secret
    if not expected_secret:
        return
    if x_webhook_secret != expected_secret:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid webhook secret.",
        )

