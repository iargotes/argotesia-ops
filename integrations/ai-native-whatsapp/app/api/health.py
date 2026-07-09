from fastapi import APIRouter
from fastapi.responses import RedirectResponse

from app.config import get_settings
from app.core.schemas import HealthResponse

router = APIRouter(tags=["health"])


@router.get("/", include_in_schema=False)
def root() -> RedirectResponse:
    return RedirectResponse(url="/docs")


@router.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    settings = get_settings()
    return HealthResponse(status="ok", service="ai-ops-center", environment=settings.app_env)
