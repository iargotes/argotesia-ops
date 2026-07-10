from functools import lru_cache

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_env: str = Field(default="development", alias="APP_ENV")
    app_port: int = Field(default=8000, alias="APP_PORT")
    database_url: str = Field(default="sqlite:///./data/ai_ops_center.db", alias="DATABASE_URL")
    telegram_bot_token: str | None = Field(default=None, alias="TELEGRAM_BOT_TOKEN")
    telegram_admin_chat_id: str | None = Field(default=None, alias="TELEGRAM_ADMIN_CHAT_ID")
    whatsapp_webhook_secret: str | None = Field(default=None, alias="WHATSAPP_WEBHOOK_SECRET")
    whatsapp_allowed_senders: str | None = Field(default=None, alias="WHATSAPP_ALLOWED_SENDERS")
    whatsapp_auto_reply: bool = Field(default=False, alias="WHATSAPP_AUTO_REPLY")
    ops_intake_url: str | None = Field(default=None, alias="OPS_INTAKE_URL")
    ops_api_token: str | None = Field(default=None, alias="OPS_API_TOKEN")
    ops_project_key: str | None = Field(default=None, alias="OPS_PROJECT_KEY")
    ops_assigned_worker_key: str | None = Field(default=None, alias="OPS_ASSIGNED_WORKER_KEY")
    ops_create_ticket: bool = Field(default=True, alias="OPS_CREATE_TICKET")
    ops_timeout_seconds: float = Field(default=10.0, alias="OPS_TIMEOUT_SECONDS")
    llm_provider: str = Field(default="mock", alias="LLM_PROVIDER")
    log_level: str = Field(default="INFO", alias="LOG_LEVEL")

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    @property
    def allowed_whatsapp_senders(self) -> set[str]:
        if not self.whatsapp_allowed_senders:
            return set()
        return {
            sender.strip().lower()
            for sender in self.whatsapp_allowed_senders.split(",")
            if sender.strip()
        }


@lru_cache
def get_settings() -> Settings:
    return Settings()
