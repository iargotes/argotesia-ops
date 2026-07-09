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
