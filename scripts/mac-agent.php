<?php
declare(strict_types=1);

$command = strtolower(trim((string)($argv[1] ?? 'run')));
$baseUrl = rtrim(getenv('OPS_BASE_URL') ?: 'http://127.0.0.1:8090', '/');
$workerKey = getenv('OPS_WORKER_KEY') ?: 'ivan';
$token = getenv('OPS_WORKER_TOKEN') ?: '';
$modelUrl = getenv('LOCAL_MODEL_URL') ?: '';
$modelName = getenv('LOCAL_MODEL_NAME') ?: 'local-template';

if ($command === 'ask') {
    $ticketCode = strtoupper(trim((string)($argv[2] ?? '')));
    $question = trim((string)($argv[3] ?? ''));
    $telegramUrl = rtrim(getenv('TELEGRAM_AGENT_URL') ?: 'https://ainative.argotes.com', '/') . '/internal/telegram/questions';
    $telegramToken = getenv('TELEGRAM_AGENT_TOKEN') ?: '';
    $authorizationRequired = in_array('--authorize', $argv, true);
    if ($ticketCode === '' || $question === '' || $telegramToken === '') {
        fwrite(STDERR, "Usage: php scripts/mac-agent.php ask OPS-2026-00042 \"pregunta\" [--authorize]\n");
        exit(1);
    }

    $response = api_post($telegramUrl, $telegramToken, [
        'ticket_code' => $ticketCode,
        'question' => $question,
        'worker_key' => $workerKey,
        'authorization_required' => $authorizationRequired,
    ]);
    if (!($response['ok'] ?? false)) {
        fwrite(STDERR, "Could not send Telegram question: " . json_encode($response) . "\n");
        exit(1);
    }
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

if ($command !== 'run') {
    fwrite(STDERR, "Commands: run (default), ask, updates.\n");
    exit(1);
}

if ($token === '') {
    fwrite(STDERR, "OPS_WORKER_TOKEN is required.\n");
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
    $proposal = generate_proposal($ticket, $modelUrl, $modelName);
    $payload = [
        'ticket_id' => (int)$ticket['id'],
        'model_name' => $proposal['model_name'],
        'body' => $proposal['body'],
        'client_reply_draft' => $proposal['client_reply_draft'],
    ];

    $response = api_post($baseUrl . '/index.php?r=' . rawurlencode('/api/worker/proposals') . '&worker_key=' . urlencode($workerKey), $token, $payload);
    if ($response['ok'] ?? false) {
        echo "Uploaded proposal {$response['proposal_id']} for {$ticket['code']}.\n";
    } else {
        fwrite(STDERR, "Failed proposal for {$ticket['code']}: " . json_encode($response) . "\n");
    }
}

function generate_proposal(array $ticket, string $modelUrl, string $modelName): array
{
    $prompt = build_prompt($ticket);
    if ($modelUrl !== '') {
        $body = call_local_model($modelUrl, $modelName, $prompt);
        if ($body !== '') {
            return [
                'model_name' => $modelName,
                'body' => $body,
                'client_reply_draft' => client_reply($ticket),
            ];
        }
    }

    return [
        'model_name' => 'local-template',
        'body' => "Propuesta inicial generada localmente.\n\n" .
            "Diagnostico probable:\n- Revisar el flujo reportado y reproducir con datos del cliente.\n\n" .
            "Plan:\n1. Abrir proyecto local correspondiente.\n2. Buscar rutas/controladores asociados al caso.\n3. Reproducir en local o staging.\n4. Preparar patch minimo.\n5. Pedir autorizacion antes de implementar.\n\n" .
            "Prompt para Codex/modelo:\n" . $prompt,
        'client_reply_draft' => client_reply($ticket),
    ];
}

function build_prompt(array $ticket): string
{
    $path = ((string)($ticket['project_name'] ?? '') !== '' && (string)($ticket['local_path_ivan'] ?? '') !== '')
        ? (string)$ticket['local_path_ivan']
        : 'Ruta local por confirmar';

    return trim(
        "Objetivo: diagnosticar y proponer solucion, no implementar.\n\n" .
        "Ticket: {$ticket['code']} - {$ticket['title']}\n" .
        "Proyecto: " . ((string)($ticket['project_name'] ?? 'Por confirmar')) . "\n" .
        "Ruta local: {$path}\n" .
        "SSH: " . ((string)($ticket['server_ssh'] ?? 'No configurado')) . "\n" .
        "Repo: " . ((string)($ticket['repo_url'] ?? 'No configurado')) . "\n" .
        "Reglas: " . ((string)($ticket['codex_rules'] ?? 'No implementar sin aprobacion humana.')) . "\n\n" .
        "Cliente: " . ((string)($ticket['client_name'] ?? '')) . "\n" .
        "Contacto: " . ((string)($ticket['client_contact'] ?? '')) . "\n" .
        "Canal: {$ticket['source_channel']}\n" .
        "Intencion: {$ticket['intent']}\n" .
        "Urgencia: {$ticket['urgency']}\n\n" .
        "Descripcion:\n{$ticket['description']}"
    );
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
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        return '';
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return trim((string)($decoded['response'] ?? $decoded['text'] ?? $decoded['output'] ?? ''));
    }
    return trim($raw);
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
