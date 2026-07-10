<?php
declare(strict_types=1);

final class Classifier
{
    public static function classify(PDO $conn, string $title, string $text, string $clientContact = ''): array
    {
        $normalized = self::normalize($title . ' ' . $text);
        $intent = 'unknown';
        $urgency = 'media';
        $confidence = 0.55;

        if (self::hasAny($normalized, ['urgente', 'produccion', 'caido', 'caida', 'no funciona', 'error 500', 'down'])) {
            $intent = 'incident';
            $urgency = 'alta';
            $confidence = 0.8;
        } elseif (self::hasAny($normalized, ['bug', 'error', 'falla', 'no guarda', 'no abre', 'pantalla blanca'])) {
            $intent = 'bug';
            $confidence = 0.74;
        } elseif (self::hasAny($normalized, ['agregar', 'crear', 'cambiar', 'mejorar', 'automatizar', 'nuevo campo'])) {
            $intent = 'change';
            $confidence = 0.68;
        } elseif (self::hasAny($normalized, ['como', 'consulta', 'pregunta', 'duda'])) {
            $intent = 'question';
            $urgency = 'baja';
            $confidence = 0.62;
        } elseif (self::hasAny($normalized, ['factura', 'cobro', 'pago', 'cotizacion'])) {
            $intent = 'billing';
            $urgency = 'baja';
            $confidence = 0.7;
        }

        $project = self::detectProject($conn, $normalized, $clientContact);
        $assigned = self::suggestAssignee($conn, $intent, $urgency);
        $summary = self::summary($title, $text, $intent, $urgency);

        return [
            'project_id' => $project['id'] ?? null,
            'assigned_user_id' => $assigned['id'] ?? null,
            'detected_intent' => $intent,
            'urgency' => $urgency,
            'confidence' => $confidence,
            'ai_summary' => $summary,
            'codex_prompt' => self::codexPrompt($project, $assigned, $title, $text, $intent, $urgency),
            'client_reply_draft' => self::clientReply($title, $urgency),
        ];
    }

    private static function detectProject(PDO $conn, string $text, string $clientContact): ?array
    {
        $projects = $conn->query("SELECT * FROM projects WHERE status = 'active' ORDER BY id ASC")->fetchAll();
        $contactDigits = self::phoneDigits($clientContact);
        if ($contactDigits !== '') {
            foreach ($projects as $project) {
                $phones = preg_split('/[\r\n,;]+/', (string)($project['client_phones'] ?? '')) ?: [];
                foreach ($phones as $phone) {
                    if (self::samePhone($contactDigits, self::phoneDigits($phone))) {
                        return $project;
                    }
                }
            }
        }

        foreach ($projects as $project) {
            $searchable = implode(' ', [
                (string)$project['project_key'],
                (string)$project['name'],
                (string)($project['client_name'] ?? ''),
                (string)($project['aliases'] ?? ''),
            ]);
            $tokens = preg_split('/\s+/', self::normalize($searchable)) ?: [];
            foreach ($tokens as $token) {
                if (mb_strlen($token) >= 5 && str_contains($text, $token)) {
                    return $project;
                }
            }
        }
        return count($projects) === 1 ? $projects[0] : null;
    }

    private static function samePhone(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }
        if ($left === $right) {
            return true;
        }
        $shorter = strlen($left) <= strlen($right) ? $left : $right;
        $longer = strlen($left) > strlen($right) ? $left : $right;
        return strlen($shorter) >= 8 && str_ends_with($longer, $shorter);
    }

    private static function phoneDigits(string $value): string
    {
        return (string)preg_replace('/\D+/', '', $value);
    }

    private static function suggestAssignee(PDO $conn, string $intent, string $urgency): ?array
    {
        $workerKey = $urgency === 'alta' || $intent === 'incident' ? 'ivan' : 'oscar';
        $stmt = $conn->prepare("SELECT id, name, worker_key FROM users WHERE worker_key = :worker_key AND status = 'active' LIMIT 1");
        $stmt->execute([':worker_key' => $workerKey]);
        return $stmt->fetch() ?: null;
    }

    private static function codexPrompt(?array $project, ?array $assigned, string $title, string $text, string $intent, string $urgency): string
    {
        $projectName = $project['name'] ?? 'Proyecto por confirmar';
        $ivanPath = $project['local_path_ivan'] ?? 'No configurado';
        $oscarPath = $project['local_path_oscar'] ?? 'No configurado';
        $workerKey = strtolower((string)($assigned['worker_key'] ?? ''));
        $sshField = $workerKey === 'oscar' ? 'server_ssh_oscar' : 'server_ssh_ivan';
        $ssh = trim((string)($project[$sshField] ?? ''));
        if ($ssh === '') {
            $ssh = 'No configurado para ' . ($workerKey !== '' ? $workerKey : 'el operador');
        }
        $repo = $project['repo_url'] ?? 'No configurado';
        $rules = $project['codex_rules'] ?? 'No implementar sin autorizacion humana.';
        $owner = $assigned['name'] ?? 'Por asignar';

        return trim("Objetivo: diagnosticar y proponer solucion. No implementar sin aprobacion humana.\n\nProyecto: {$projectName}\nAsignado sugerido: {$owner}\nRuta Ivan: {$ivanPath}\nRuta Oscar: {$oscarPath}\nSSH servidor: {$ssh}\nRepo: {$repo}\nReglas: {$rules}\n\nTicket: {$title}\nIntencion: {$intent}\nUrgencia: {$urgency}\n\nContexto/transcripcion:\n{$text}\n\nEntregable:\n1. Diagnostico probable.\n2. Archivos o areas a revisar.\n3. Plan de solucion.\n4. Comandos de prueba.\n5. Riesgos.\n6. Pregunta de autorizacion antes de implementar.");
    }

    private static function clientReply(string $title, string $urgency): string
    {
        $prefix = $urgency === 'alta'
            ? 'Recibimos el reporte y lo estamos priorizando por impacto operativo.'
            : 'Recibimos el reporte y lo estamos revisando.';

        return $prefix . ' Caso: "' . $title . '". Te confirmamos avances antes de aplicar cambios.';
    }

    private static function summary(string $title, string $text, string $intent, string $urgency): string
    {
        $clean = trim((string)preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($clean) > 420) {
            $clean = mb_substr($clean, 0, 417) . '...';
        }
        return "Resumen: {$title}\nIntencion: {$intent}\nUrgencia: {$urgency}\nDetalle: {$clean}";
    }

    private static function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, self::normalize($needle))) {
                return true;
            }
        }
        return false;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n',
        ]);
    }
}
