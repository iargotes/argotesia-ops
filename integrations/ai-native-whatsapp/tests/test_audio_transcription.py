import asyncio
import base64
from types import SimpleNamespace

from app.config import Settings
from app.services.audio_transcription import AudioTranscriptionService


class FakeWhisperModel:
    def transcribe(self, _path: str, **_kwargs):
        return iter([SimpleNamespace(text=" Necesito ayuda "), SimpleNamespace(text="con una ruta.")]), SimpleNamespace(
            language="es"
        )


def test_transcribes_audio_and_removes_base64_from_sanitized_payload(monkeypatch) -> None:
    settings = Settings(
        WHISPER_ENABLED=True,
        WHISPER_MODEL="tiny",
        WHISPER_COMPUTE_TYPE="int8",
        WHISPER_MAX_AUDIO_BYTES=1024,
    )
    service = AudioTranscriptionService(settings, model_factory=lambda *_args, **_kwargs: FakeWhisperModel())
    monkeypatch.setattr(service, "_probe_duration", lambda _path: 2.5)

    sanitized, result = asyncio.run(
        service.transcribe_payload(
            {
                "type": "ptt",
                "body": "",
                "media": {
                    "data": base64.b64encode(b"fake-audio").decode("ascii"),
                    "mimetype": "audio/ogg; codecs=opus",
                },
            }
        )
    )

    assert result.text == "Necesito ayuda con una ruta."
    assert result.duration_seconds == 2.5
    assert sanitized["body"] == result.text
    assert "data" not in sanitized["media"]
    assert sanitized["transcription"]["provider"] == "faster-whisper"
