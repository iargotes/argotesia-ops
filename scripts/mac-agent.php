<?php
declare(strict_types=1);

$command = strtolower(trim((string)($argv[1] ?? 'run')));
$baseUrl = rtrim(getenv('OPS_BASE_URL') ?: 'http://127.0.0.1:8090', '/');
$workerKey = getenv('OPS_WORKER_KEY') ?: 'ivan';
$token = getenv('OPS_WORKER_TOKEN') ?: '';
$modelUrl = getenv('LOCAL_MODEL_URL') ?: '';
$modelName = getenv('LOCAL_MODEL_NAME') ?: 'local-template';
$telegramUrl = rtrim(getenv('TELEGRAM_AGENT_URL') ?: 'https://ainative.argotes.com', '/') . '/internal/telegram/questions';
$telegramToken = getenv('TELEGRAM_AGENT_TOKEN') ?: '';

if ($command === 'ask') {
    $ticketCode = strtoupper(trim((string)($argv[2] ?? '')));
    $question = trim((string)($argv[3] ?? ''));
    $authorizationType = in_array('--deploy', $argv, true)
        ? 'deployment'
        : (in_array('--authorize', $argv, true) ? 'changes' : 'none');
    if ($ticketCode === '' || $question === '' || $telegramToken === '') {
        fwrite(STDERR, "Usage: php scripts/mac-agent.php ask OPS-2026-00042 \"pregunta\" [--authorize|--deploy]\n");
        exit(1);
    }

    publish_telegram_message($telegramUrl, $telegramToken, $ticketCode, $question, $workerKey, $authorizationType);
    echo "Telegram question sent for {$ticketCode}.\n";
    exit(0);
}

if ($command === 'updates') {
    if ($token === '') {
        fwrite(STDERR, "OPS_WORKER_TOKEN is required.\n");
        exit(1);
    }
    $sinceId = max(0, (int)($argv[2] ?? 0));
    $updates = api_get(
        $baseUrl . '/index.php?r=' . rawurlencode('/api/worker/updates') .
        '&worker_key=' . urlencode($workerKey) . '&since_id=' . $sinceId,
        $token
    );
    echo json_encode($updates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(($updates['ok'] ?? false) ? 0 : 1);
}

if ($command === 'tasks') {
    if ($token === '') {
        fwrite(STDERR, "OPS_WORKER_TOKEN is required.\n");
        exit(1);
    }
    $tasks = api_get($baseUrl . '/index.php?r=' . rawurlencode('/api/worker/tasks') . '&worker_key=' . urlencode($workerKey), $token);
    echo json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(($tasks['ok'] ?? false) ? 0 : 1);
}

if ($command === 'submit') {
    if ($token === '') {
        fwrite(STDERR, "OPS_WORKER_TOKEN is required.\n");
        exit(1);
    }
    $ticketCode = strtoupper(trim((string)($argv[2] ?? '')));
    $proposalPath = (string)($argv[3] ?? '');
    $replyPath = (string)($argv[4] ?? '');
    if ($ticketCode === '' || $proposalPath === '' || !is_readable($proposalPath)) {
        fwrite(STDERR, "Usage: php scripts/mac-agent.php submit OPS-2026-00042 proposal.md [client-reply.md]\n");
        exit(1);
    }
    if ($replyPath !== '' && !is_readable($replyPath)) {
        fwrite(STDERR, "Client reply file is not readable: {$replyPath}\n");
        exit(1);
    }
    $body = trim((string)file_get_contents($proposalPath));
    $clientReply = $replyPath !== '' ? trim((string)file_get_contents($replyPath)) : '';
    if ($body === '') {
        fwrite(STDERR, "Proposal file is empty.\n");
        exit(1);
    }

    $response = api_post(
        $baseUrl . '/index.php?r=' . rawurlencode('/api/worker/proposals') . '&worker_key=' . urlencode($workerKey),
        $token,
        [
            'ticket_code' => $ticketCode,
            'source' => 'codex',
            'model_name' => 'codex',
            'body' => $body,
            'client_reply_draft' => $clientReply,
        ]
    );
    if (!($response['ok'] ?? false)) {
        fwrite(STDERR, "Could not submit Codex proposal: " . json_encode($response) . "\n");
        exit(1);
    }
    echo "Uploaded Codex proposal {$response['proposal_id']} for {$ticketCode}.\n";
    exit(0);
}

if ($command !== 'run') {
    fwrite(STDERR, "Commands: run (default), tasks, submit, ask, updates.\n");
    exit(1);
}

if ($token === '') {
    fwrite(STDERR, "OPS_WORKER_TOKEN is required.\n");
    exit(1);
}
if ($telegramToken === '') {
    fwrite(STDERR, "TELEGRAM_AGENT_TOKEN is required for automatic diagnostics.\n");
    exit(1);
}
if ($modelUrl === '' || $modelName === '' || $modelName === 'local-template') {
    fwrite(STDERR, "A real LOCAL_MODEL_URL and LOCAL_MODEL_NAME are required; templates are not submitted.\n");
    exit(1);
}

$tasks = api_get($baseUrl . '/index.php?r=' . rawurlencode('/api/worker/tasks') . '&worker_key=' . urlencode($workerKey), $token);
if (!($tasks['ok'] ?? false)) {
    fwrite(STDERR, "Could not fetch tasks: " . json_encode($tasks) . "\n");
    exit(1);
}

$tickets = $tasks['tickets'] ?? [];
if (!$tickets) {
    echo "No tickets assigned to {$workerKey}.\n";
    exit(0);
}

foreach ($tickets as $ticket) {
    $missingContext = missing_ticket_context($ticket);
    if ($missingContext) {
        $question = context_request_message($ticket, $missingContext);
        try {
            publish_telegram_message(
                $telegramUrl,
                $telegramToken,
                (string)$ticket['code'],
                $question,
                $workerKey,
                'none'
            );
            echo "Requested missing context for {$ticket['code']}; no proposal uploaded.\n";
        } catch (RuntimeException $e) {
            fwrite(STDERR, "Could not request context for {$ticket['code']}: {$e->getMessage()}\n");
        }
        continue;
    }

    try {
        $proposal = generate_proposal($ticket, $modelUrl, $modelName, $workerKey);
    } catch (RuntimeException $e) {
        $failureMessage = "No se genero una propuesta para {$ticket['code']}. " .
            "Ollama fallo o no devolvio contenido; no se subio ninguna plantilla. " .
            "Detalle local: {$e->getMessage()}";
        try {
            publish_telegram_message(
                $telegramUrl,
                $telegramToken,
                (string)$ticket['code'],
                $failureMessage,
                $workerKey,
                'none'
            );
        } catch (RuntimeException $telegramError) {
            fwrite(STDERR, " Telegram notification also failed: {$telegramError->getMessage()}");
        }
        fwrite(STDERR, "Local model failed for {$ticket['code']}; no proposal uploaded.\n");
        continue;
    }

    $payload = [
        'ticket_id' => (int)$ticket['id'],
        'source' => 'local_model',
        'model_name' => $proposal['model_name'],
        'body' => $proposal['body'],
        'client_reply_draft' => $proposal['client_reply_draft'],
    ];

    $response = api_post($baseUrl . '/index.php?r=' . rawurlencode('/api/worker/proposals') . '&worker_key=' . urlencode($workerKey), $token, $payload);
    if ($response['ok'] ?? false) {
        echo "Uploaded proposal {$response['proposal_id']} for {$ticket['code']}.\n";
        try {
            publish_telegram_message(
                $telegramUrl,
                $telegramToken,
                (string)$ticket['code'],
                proposal_review_message($ticket, $proposal['body']),
                $workerKey,
                'changes'
            );
            echo "Telegram review requested for {$ticket['code']}.\n";
        } catch (RuntimeException $e) {
            fwrite(STDERR, "Proposal uploaded but Telegram review failed for {$ticket['code']}: {$e->getMessage()}\n");
        }
    } else {
        fwrite(STDERR, "Failed proposal for {$ticket['code']}: " . json_encode($response) . "\n");
    }
}

function generate_proposal(array $ticket, string $modelUrl, string $modelName, string $workerKey): array
{
    $prompt = build_prompt($ticket, $workerKey);
    $body = call_local_model($modelUrl, $modelName, $prompt);
    if (mb_strlen($body) < 200) {
        throw new RuntimeException('local model response is too short to be a reviewable diagnosis');
    }

    return [
        'model_name' => $modelName,
        'body' => $body,
        'client_reply_draft' => client_reply($ticket),
    ];
}

function build_prompt(array $ticket, string $workerKey): string
{
    $pathKey = strtolower($workerKey) === 'oscar' ? 'local_path_oscar' : 'local_path_ivan';
    $sshKey = strtolower($workerKey) === 'oscar' ? 'server_ssh_oscar' : 'server_ssh_ivan';
    $path = trim((string)($ticket['local_path'] ?? $ticket[$pathKey] ?? ''));
    if ($path === '') {
        $path = 'Ruta local por confirmar';
    }
    $ssh = trim((string)($ticket['server_ssh_target'] ?? $ticket[$sshKey] ?? ''));
    if ($ssh === '') {
        $ssh = 'No configurado para ' . $workerKey;
    }

    return trim(
        "Objetivo: diagnosticar y proponer solucion, no implementar.\n\n" .
        "Reglas del diagnostico:\n" .
        "- Distinguir hechos confirmados de hipotesis.\n" .
        "- No inventar stack, archivos, tablas, endpoints, comandos ni estado de produccion.\n" .
        "- Basar cada recomendacion en la evidencia entregada.\n" .
        "- Si la evidencia no alcanza, indicar exactamente que falta en vez de proponer un cambio.\n" .
        "- La autorizacion de cambios no autoriza despliegue.\n\n" .
        "Ticket: {$ticket['code']} - {$ticket['title']}\n" .
        "Proyecto: " . ((string)($ticket['project_name'] ?? 'Por confirmar')) . "\n" .
        "Ruta local: {$path}\n" .
        "SSH de {$workerKey}: {$ssh}\n" .
        "Repo: " . ((string)($ticket['repo_url'] ?? 'No configurado')) . "\n" .
        "Alias: " . ((string)($ticket['project_aliases'] ?? 'No configurados')) . "\n" .
        "Reglas: " . ((string)($ticket['codex_rules'] ?? 'No implementar sin aprobacion humana.')) . "\n\n" .
        "Contexto operativo del proyecto:\n" . ((string)($ticket['project_operational_context'] ?? 'No configurado')) . "\n\n" .
        "Cliente: " . ((string)($ticket['client_name'] ?? '')) . "\n" .
        "Contacto: " . ((string)($ticket['client_contact'] ?? '')) . "\n" .
        "Canal: {$ticket['source_channel']}\n" .
        "Intencion: {$ticket['intent']}\n" .
        "Urgencia: {$ticket['urgency']}\n\n" .
        "Descripcion:\n{$ticket['description']}\n\n" .
        "Respuesta humana mas reciente:\n" . ((string)($ticket['latest_human_answer'] ?? 'Sin respuesta adicional'))
    );
}

function missing_ticket_context(array $ticket): array
{
    $missing = [];
    $project = strtolower(trim((string)($ticket['project_name'] ?? '')));
    if ($project === '' || str_contains($project, 'por confirmar')) {
        $missing[] = 'proyecto confirmado';
    }
    if (trim((string)($ticket['local_path'] ?? '')) === '') {
        $missing[] = 'ruta local del responsable';
    }
    if (trim((string)($ticket['repo_url'] ?? '')) === '') {
        $missing[] = 'repositorio del proyecto';
    }

    $operationalContext = strtolower(trim((string)($ticket['project_operational_context'] ?? '')));
    if ($operationalContext === '' || $operationalContext === 'no configurado') {
        $missing[] = 'contexto operativo del proyecto';
    }

    $detail = ticket_detail($ticket);
    $latestAnswer = trim((string)($ticket['latest_human_answer'] ?? ''));
    $title = trim((string)($ticket['title'] ?? ''));
    $hasUsefulDetail = mb_strlen($detail) >= 40 && mb_strtolower($detail) !== mb_strtolower($title);
    if (!$hasUsefulDetail && mb_strlen($latestAnswer) < 40) {
        $missing[] = 'evidencia, alcance o pasos para reproducir';
    }
    return $missing;
}

function ticket_detail(array $ticket): string
{
    $description = trim((string)($ticket['description'] ?? ''));
    if (preg_match('/Detalle:\s*([^\r\n]+)/iu', $description, $matches) === 1) {
        return trim($matches[1]);
    }
    return $description;
}

function context_request_message(array $ticket, array $missing): string
{
    $ticketCode = (string)$ticket['code'];
    return "No puedo preparar un diagnostico confiable todavia.\n\n" .
        "Falta:\n- " . implode("\n- ", $missing) . "\n\n" .
        "Actualiza la ficha del proyecto cuando corresponda y responde por Telegram con:\n" .
        "/responder {$ticketCode} <evidencia, comportamiento esperado, comportamiento actual y pasos para reproducir>\n\n" .
        "No se genero ni se subio una propuesta generica.";
}

function proposal_review_message(array $ticket, string $body): string
{
    $ticketCode = (string)$ticket['code'];
    $ticketId = (int)$ticket['id'];
    $excerpt = mb_substr(trim($body), 0, 2600);
    if (mb_strlen(trim($body)) > 2600) {
        $excerpt .= "\n\n[Diagnostico recortado; abre el ticket para leerlo completo.]";
    }
    $url = 'https://ops.argotes.com/index.php?r=%2Ftickets%2Fview&id=' . $ticketId;
    return "Diagnostico listo para revision humana.\n\n" .
        $excerpt . "\n\n" .
        "Ticket completo: {$url}\n\n" .
        "Autorizar cambios permite implementar y probar. No autoriza despliegue.";
}

function publish_telegram_message(
    string $url,
    string $token,
    string $ticketCode,
    string $message,
    string $workerKey,
    string $authorizationType
): void {
    $response = api_post($url, $token, [
        'ticket_code' => $ticketCode,
        'question' => mb_substr($message, 0, 3900),
        'worker_key' => $workerKey,
        'authorization_required' => $authorizationType !== 'none',
        'authorization_type' => $authorizationType,
    ]);
    if (!($response['ok'] ?? false)) {
        throw new RuntimeException((string)($response['error'] ?? 'Telegram request failed'));
    }
}

function client_reply(array $ticket): string
{
    return 'Estamos revisando el caso "' . (string)$ticket['title'] . '". Te confirmamos el diagnostico antes de aplicar cambios.';
}

function call_local_model(string $url, string $modelName, string $prompt): string
{
    $payload = json_encode([
        'model' => $modelName,
        'prompt' => $prompt,
        'stream' => false,
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 120,
        ],
    ]);
    error_clear_last();
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        $lastError = error_get_last();
        $detail = trim((string)($lastError['message'] ?? 'empty HTTP response'));
        throw new RuntimeException(mb_substr($detail, 0, 500));
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $response = trim((string)($decoded['response'] ?? $decoded['text'] ?? $decoded['output'] ?? ''));
        if ($response === '') {
            throw new RuntimeException('local model returned JSON without response text');
        }
        return $response;
    }
    $response = trim($raw);
    if ($response === '') {
        throw new RuntimeException('local model returned an empty body');
    }
    return $response;
}

function api_get(string $url, string $token): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\n",
            'timeout' => 30,
        ],
    ]);
    return decode_response(@file_get_contents($url, false, $context));
}

function api_post(string $url, string $token, array $payload): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 60,
        ],
    ]);
    return decode_response(@file_get_contents($url, false, $context));
}

function decode_response(false|string $raw): array
{
    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'error' => 'empty response'];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'invalid json', 'raw' => $raw];
}
