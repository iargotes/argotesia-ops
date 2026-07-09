from abc import ABC, abstractmethod

from app.core.schemas import ClassificationResult, NormalizedMessage


class AIClassifier(ABC):
    @abstractmethod
    def classify(self, message: NormalizedMessage) -> ClassificationResult:
        raise NotImplementedError


class MockAIClassifier(AIClassifier):
    def classify(self, message: NormalizedMessage) -> ClassificationResult:
        text = (message.text or "").lower()
        project = self._guess_project(text)
        category = self._guess_category(text)
        priority = self._guess_priority(text, category)
        summary = self._build_summary(message)

        return ClassificationResult(
            project=project,
            category=category,
            priority=priority,
            summary=summary,
            reason="Clasificacion mock basada en palabras clave simples para validar el flujo MVP.",
            needs_human_review=True,
        )

    def _guess_project(self, text: str) -> str:
        if any(keyword in text for keyword in ["viaje", "conductor", "ruta", "mapa", "argodrive"]):
            return "argodrive"
        if any(keyword in text for keyword in ["cita", "caja", "paciente", "clinica", "agenda"]):
            return "argodental"
        if any(keyword in text for keyword in ["guia", "artesania", "comision", "venta"]):
            return "artesanias"
        return "unknown"

    def _guess_category(self, text: str) -> str:
        if any(keyword in text for keyword in ["caido", "no abre", "no funciona", "bloqueado"]):
            return "INCIDENTE_CRITICO"
        if any(keyword in text for keyword in ["error", "duplicado", "no aparece", "no me aparece", "fallo"]):
            return "BUG"
        if any(keyword in text for keyword in ["pago", "factura", "cobro"]):
            return "FACTURACION"
        return "SOPORTE_OPERATIVO"

    def _guess_priority(self, text: str, category: str) -> str:
        if category == "INCIDENTE_CRITICO":
            return "P1"
        if any(keyword in text for keyword in ["urgente", "no podemos operar", "produccion detenida"]):
            return "P1"
        if category in {"BUG", "ERROR_DATOS"}:
            return "P2"
        return "P3"

    def _build_summary(self, message: NormalizedMessage) -> str:
        if not message.text:
            return "Mensaje recibido sin texto disponible."
        clean_text = " ".join(message.text.split())
        return clean_text[:180]


def get_classifier() -> AIClassifier:
    return MockAIClassifier()
