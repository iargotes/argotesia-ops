import asyncio
import base64
import binascii
import subprocess
import tempfile
import threading
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Callable

from app.config import Settings, get_settings


AUDIO_MESSAGE_TYPES = {"ptt", "audio"}
_MIME_SUFFIXES = {
    "audio/ogg": ".ogg",
    "audio/opus": ".opus",
    "audio/mpeg": ".mp3",
    "audio/mp4": ".m4a",
    "audio/wav": ".wav",
    "audio/x-wav": ".wav",
    "audio/webm": ".webm",
}


class AudioTranscriptionError(RuntimeError):
    """Raised when an inbound WhatsApp audio cannot be transcribed safely."""


@dataclass(frozen=True)
class TranscriptionResult:
    text: str
    language: str
    duration_seconds: float | None
    model: str
    size_bytes: int
    mime_type: str


class AudioTranscriptionService:
    _model: Any = None
    _model_key: tuple[str, str] | None = None
    _model_lock = threading.Lock()
    _transcription_lock = threading.Lock()

    def __init__(
        self,
        settings: Settings | None = None,
        model_factory: Callable[..., Any] | None = None,
    ) -> None:
        self.settings = settings or get_settings()
        self.model_factory = model_factory

    @staticmethod
    def is_audio_payload(payload: dict[str, Any]) -> bool:
        message_type = payload.get("type") or payload.get("message_type") or ""
        return str(message_type).lower() in AUDIO_MESSAGE_TYPES

    async def transcribe_payload(self, payload: dict[str, Any]) -> tuple[dict[str, Any], TranscriptionResult]:
        if not self.settings.whisper_enabled:
            raise AudioTranscriptionError("WHISPER_ENABLED is disabled.")

        return await asyncio.to_thread(self._transcribe_payload_sync, payload)

    def _transcribe_payload_sync(self, payload: dict[str, Any]) -> tuple[dict[str, Any], TranscriptionResult]:
        media = payload.get("media")
        if not isinstance(media, dict):
            raise AudioTranscriptionError("Audio payload has no media object.")

        mime_type = str(media.get("mimetype") or "").split(";", 1)[0].strip().lower()
        if not mime_type.startswith("audio/"):
            raise AudioTranscriptionError("Inbound media is not an audio file.")

        encoded = media.get("data")
        if not isinstance(encoded, str) or not encoded:
            raise AudioTranscriptionError("Audio payload has no base64 data.")

        try:
            audio_bytes = base64.b64decode(encoded, validate=True)
        except (binascii.Error, ValueError) as exc:
            raise AudioTranscriptionError("Audio payload contains invalid base64 data.") from exc

        size_bytes = len(audio_bytes)
        if size_bytes == 0:
            raise AudioTranscriptionError("Audio payload is empty.")
        if size_bytes > self.settings.whisper_max_audio_bytes:
            raise AudioTranscriptionError("Audio exceeds WHISPER_MAX_AUDIO_BYTES.")

        suffix = _MIME_SUFFIXES.get(mime_type, ".audio")
        path: Path | None = None
        try:
            with tempfile.NamedTemporaryFile(prefix="ai-native-audio-", suffix=suffix, delete=False) as handle:
                handle.write(audio_bytes)
                path = Path(handle.name)

            duration = self._probe_duration(path)
            if duration is not None and duration > self.settings.whisper_max_audio_seconds:
                raise AudioTranscriptionError("Audio exceeds WHISPER_MAX_AUDIO_SECONDS.")

            with self._transcription_lock:
                model = self._get_model()
                segments, info = model.transcribe(
                    str(path),
                    language=self.settings.whisper_language or None,
                    beam_size=1,
                    vad_filter=True,
                    condition_on_previous_text=False,
                )
                text = " ".join(
                    str(segment.text).strip()
                    for segment in segments
                    if str(segment.text).strip()
                ).strip()

            if not text:
                raise AudioTranscriptionError("Whisper returned an empty transcription.")

            detected_language = str(getattr(info, "language", "") or self.settings.whisper_language)
            result = TranscriptionResult(
                text=text,
                language=detected_language,
                duration_seconds=duration,
                model=self.settings.whisper_model,
                size_bytes=size_bytes,
                mime_type=mime_type,
            )
            return self._sanitized_payload(payload, result), result
        finally:
            if path is not None:
                path.unlink(missing_ok=True)

    def _get_model(self) -> Any:
        key = (self.settings.whisper_model, self.settings.whisper_compute_type)
        with self._model_lock:
            if self.model_factory is not None:
                return self.model_factory(
                    self.settings.whisper_model,
                    device="cpu",
                    compute_type=self.settings.whisper_compute_type,
                )

            if self.__class__._model is None or self.__class__._model_key != key:
                try:
                    from faster_whisper import WhisperModel
                except ImportError as exc:
                    raise AudioTranscriptionError("faster-whisper is not installed.") from exc

                self.__class__._model = WhisperModel(
                    self.settings.whisper_model,
                    device="cpu",
                    compute_type=self.settings.whisper_compute_type,
                )
                self.__class__._model_key = key
            return self.__class__._model

    def _probe_duration(self, path: Path) -> float | None:
        try:
            completed = subprocess.run(
                [
                    self.settings.whisper_ffprobe_binary,
                    "-v",
                    "error",
                    "-show_entries",
                    "format=duration",
                    "-of",
                    "default=noprint_wrappers=1:nokey=1",
                    str(path),
                ],
                check=True,
                capture_output=True,
                text=True,
                timeout=10,
            )
            return float(completed.stdout.strip())
        except (FileNotFoundError, subprocess.SubprocessError, ValueError):
            return None

    def _sanitized_payload(
        self,
        payload: dict[str, Any],
        result: TranscriptionResult,
    ) -> dict[str, Any]:
        sanitized = dict(payload)
        original_media = payload.get("media")
        media = dict(original_media) if isinstance(original_media, dict) else {}
        media.pop("data", None)
        media["size_bytes"] = result.size_bytes
        media["mimetype"] = result.mime_type
        sanitized["media"] = media
        sanitized["body"] = result.text
        sanitized["transcription"] = {
            "provider": "faster-whisper",
            "model": result.model,
            "language": result.language,
            "duration_seconds": result.duration_seconds,
        }
        return sanitized
