from fastapi import FastAPI

from app.api.health import router as health_router
from app.api.webhooks import router as webhooks_router
from app.core.database import Base, engine
from app import models  # noqa: F401


def create_app() -> FastAPI:
    Base.metadata.create_all(bind=engine)

    app = FastAPI(
        title="AI Ops Center",
        version="0.1.0",
        description="Internal AI-assisted operations center for incoming support messages.",
    )
    app.include_router(health_router)
    app.include_router(webhooks_router)
    return app


app = create_app()
