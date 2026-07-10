<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/Security.php';
require_once __DIR__ . '/../app/Core/Classifier.php';

$auth = new Auth($conn);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
    $path = '/' . ltrim($_GET['r'], '/');
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function url_for(string $path): string
{
    return '/index.php?r=' . rawurlencode('/' . ltrim($path, '/'));
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_pull(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function layout(string $title, array $user, callable $body): void
{
    $csrf = Security::csrfToken();
    $flash = flash_pull();
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | ArgotesIA Ops</title>
  <script>
    (() => {
      const saved = localStorage.getItem('ops-theme');
      const theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.dataset.theme = theme;
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root{--ink:oklch(24% .015 248);--muted:oklch(53% .025 250);--line:oklch(88% .018 250);--bg:oklch(97% .01 250);--surface:oklch(99% .006 250);--surface-2:oklch(94% .012 250);--field:oklch(99% .006 250);--accent:oklch(55% .16 252);--shadow:0 10px 24px oklch(24% .015 248 / .06)}
    :root[data-theme="dark"]{--ink:oklch(91% .012 250);--muted:oklch(70% .02 250);--line:oklch(33% .02 250);--bg:oklch(18% .015 250);--surface:oklch(23% .018 250);--surface-2:oklch(28% .02 250);--field:oklch(20% .018 250);--accent:oklch(70% .13 252);--shadow:0 12px 28px oklch(12% .015 250 / .34)}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;background:var(--bg);color:var(--ink);transition:background-color .18s ease-out,color .18s ease-out}
    .wrap{max-width:1280px}
    .panel{background:var(--surface);border:1px solid var(--line);border-radius:10px;box-shadow:var(--shadow)}
    .metric{background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:14px}
    .metric strong{font-size:1.5rem}
    .soft{border-radius:8px;border:1px solid var(--line);padding:.7rem .75rem;background:var(--field);color:var(--ink)}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.86rem;white-space:pre-wrap}
    .nav-pills .nav-link{border-radius:8px}
    .nav-link{color:var(--muted)}
    .nav-link:hover{color:var(--ink);background:var(--surface-2)}
    .form-control,.form-select{background-color:var(--field);border-color:var(--line);color:var(--ink)}
    .form-control:focus,.form-select:focus{border-color:var(--accent);box-shadow:0 0 0 .2rem oklch(55% .16 252 / .18)}
    .form-control::placeholder{color:var(--muted)}
    .table{--bs-table-bg:var(--surface);--bs-table-color:var(--ink);--bs-table-border-color:var(--line);--bs-table-hover-bg:var(--surface-2);--bs-table-hover-color:var(--ink)}
    .table-light{--bs-table-bg:var(--surface-2);--bs-table-color:var(--muted);--bs-table-border-color:var(--line)}
    .modal-content,.dropdown-menu{background:var(--surface);border-color:var(--line);color:var(--ink)}
    .bg-light{background-color:var(--surface-2)!important}
    .theme-toggle{border-color:var(--line);color:var(--muted)}
    .theme-toggle:hover{background:var(--surface-2);color:var(--ink)}
    textarea.mono{min-height:180px}
  </style>
</head>
<body>
<div class="container wrap py-3 py-md-4">
  <header class="panel p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">
    <div class="fw-bold h5 mb-0 me-2">ArgotesIA Ops</div>
    <nav class="nav nav-pills gap-1">
      <a class="nav-link" href="<?= h(url_for('/')) ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      <a class="nav-link" href="<?= h(url_for('/intake')) ?>"><i class="bi bi-whatsapp me-1"></i>Intake</a>
      <a class="nav-link" href="<?= h(url_for('/tickets')) ?>"><i class="bi bi-ticket-perforated me-1"></i>Tickets</a>
      <a class="nav-link" href="<?= h(url_for('/projects')) ?>"><i class="bi bi-folder2-open me-1"></i>Proyectos</a>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
        <a class="nav-link" href="<?= h(url_for('/users')) ?>"><i class="bi bi-people me-1"></i>Usuarios</a>
      <?php endif; ?>
    </nav>
    <div class="ms-auto small text-muted">
      <?= h((string)$user['name']) ?> · <?= h((string)$user['worker_key']) ?>
      <button class="btn btn-outline-secondary btn-sm ms-2 theme-toggle" type="button" data-theme-toggle aria-label="Cambiar tema">
        <i class="bi bi-moon-stars" data-theme-icon></i><span class="d-none d-sm-inline ms-1" data-theme-label>Tema</span>
      </button>
      <a class="btn btn-outline-secondary btn-sm ms-2" href="<?= h(url_for('/change-password')) ?>">Clave</a>
      <a class="btn btn-outline-danger btn-sm ms-2" href="<?= h(url_for('/logout')) ?>">Salir</a>
    </div>
  </header>
  <?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'danger' ?>"><?= h((string)($flash['message'] ?? '')) ?></div>
  <?php endif; ?>
  <?php $body($csrf); ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-copy-target]').forEach((button) => {
  button.addEventListener('click', async () => {
    const target = document.getElementById(button.dataset.copyTarget);
    if (!target) return;
    await navigator.clipboard.writeText(target.value || target.textContent || '');
    const old = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check2 me-1"></i>Copiado';
    setTimeout(() => button.innerHTML = old, 1100);
  });
});
(() => {
  const root = document.documentElement;
  const button = document.querySelector('[data-theme-toggle]');
  const icon = document.querySelector('[data-theme-icon]');
  const label = document.querySelector('[data-theme-label]');
  const applyTheme = (theme) => {
    root.dataset.theme = theme;
    root.setAttribute('data-bs-theme', theme);
    localStorage.setItem('ops-theme', theme);
    if (icon) icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    if (label) label.textContent = theme === 'dark' ? 'Claro' : 'Oscuro';
  };
  applyTheme(root.dataset.theme || 'light');
  if (button) {
    button.addEventListener('click', () => applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'));
  }
})();
</script>
</body>
</html>
<?php
}

function users(PDO $conn): array
{
    return $conn->query("SELECT id, name, worker_key FROM users WHERE status = 'active' ORDER BY id ASC")->fetchAll();
}

function admin_users(PDO $conn): array
{
    return $conn->query("
      SELECT id, worker_key, username, name, email, role, status, must_change_password, created_at, updated_at
      FROM users
      ORDER BY status ASC, role ASC, name ASC
    ")->fetchAll();
}

function user_roles(): array
{
    return ['admin' => 'Admin', 'operator' => 'Operador'];
}

function normalize_user_input(array $source): array
{
    $username = strtolower(trim((string)($source['username'] ?? '')));
    $workerKey = strtolower(trim((string)($source['worker_key'] ?? '')));
    if ($workerKey === '') {
        $workerKey = $username;
    }

    return [
        'id' => (int)($source['id'] ?? 0),
        'name' => trim((string)($source['name'] ?? '')),
        'username' => $username,
        'worker_key' => $workerKey,
        'email' => strtolower(trim((string)($source['email'] ?? ''))),
        'role' => trim((string)($source['role'] ?? 'operator')),
        'status' => trim((string)($source['status'] ?? 'active')),
    ];
}

function validate_user_input(array $input): array
{
    $errors = [];
    if ($input['name'] === '') {
        $errors[] = 'El nombre es obligatorio.';
    }
    if (!preg_match('/^[a-z0-9._-]{3,80}$/', (string)$input['username'])) {
        $errors[] = 'El usuario debe tener 3-80 caracteres: letras, numeros, punto, guion o guion bajo.';
    }
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', (string)$input['worker_key'])) {
        $errors[] = 'La clave de agente debe tener 3-40 caracteres: letras, numeros, punto, guion o guion bajo.';
    }
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo no tiene formato valido.';
    }
    if (!array_key_exists($input['role'], user_roles())) {
        $errors[] = 'El rol seleccionado no es valido.';
    }
    if (!in_array($input['status'], ['active', 'paused'], true)) {
        $errors[] = 'El estado seleccionado no es valido.';
    }
    return $errors;
}

function user_field_exists(PDO $conn, string $field, string $value, ?int $exceptId = null): bool
{
    if (!in_array($field, ['email', 'username', 'worker_key'], true)) {
        throw new InvalidArgumentException('Campo de usuario invalido.');
    }
    $sql = "SELECT COUNT(*) FROM users WHERE {$field} = :value";
    $params = [':value' => $value];
    if ($exceptId !== null) {
        $sql .= " AND id <> :id";
        $params[':id'] = $exceptId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function find_user(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function count_active_admins(PDO $conn, ?int $exceptId = null): int
{
    $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'";
    $params = [];
    if ($exceptId !== null) {
        $sql .= " AND id <> :id";
        $params[':id'] = $exceptId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function require_admin_user(array $user): void
{
    if (($user['role'] ?? '') === 'admin') {
        return;
    }
    http_response_code(403);
    exit('Acceso restringido.');
}

function random_worker_token(): string
{
    return bin2hex(random_bytes(24));
}

function projects(PDO $conn): array
{
    return $conn->query("SELECT * FROM projects ORDER BY FIELD(status, 'active', 'paused', 'archived'), name ASC")->fetchAll();
}

function project_by_id(PDO $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function project_key_exists(PDO $conn, string $key, ?int $exceptId = null): bool
{
    $sql = "SELECT COUNT(*) FROM projects WHERE project_key = :project_key";
    $params = [':project_key' => $key];
    if ($exceptId !== null) {
        $sql .= " AND id <> :id";
        $params[':id'] = $exceptId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function generate_ticket_code(PDO $conn, int $ticketId): string
{
    $code = 'OPS-' . date('Y') . '-' . str_pad((string)$ticketId, 5, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("UPDATE tickets SET code = :code WHERE id = :id LIMIT 1");
    $stmt->execute([':code' => $code, ':id' => $ticketId]);
    return $code;
}

function add_event(PDO $conn, int $ticketId, ?int $userId, string $eventType, string $body): void
{
    $stmt = $conn->prepare("INSERT INTO ticket_events (ticket_id, user_id, event_type, body) VALUES (:ticket_id, :user_id, :event_type, :body)");
    $stmt->execute([':ticket_id' => $ticketId, ':user_id' => $userId, ':event_type' => $eventType, ':body' => $body]);
}

function bearer_token(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', (string)$authHeader, $m)) {
        return trim($m[1]);
    }

    return trim((string)($headers['X-Ops-Api-Key'] ?? $headers['x-ops-api-key'] ?? $_GET['token'] ?? ''));
}

function require_integration_token(): void
{
    $expected = trim((string)env('OPS_API_TOKEN', ''));
    if ($expected === '') {
        json_response(['ok' => false, 'error' => 'integration token not configured'], 503);
    }

    $received = bearer_token();
    if ($received === '' || !hash_equals($expected, $received)) {
        json_response(['ok' => false, 'error' => 'invalid integration token'], 403);
    }
}

function json_input(): array
{
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'invalid json'], 400);
    }
    return $input;
}

function integration_creator_id(PDO $conn): int
{
    $workerKey = strtolower(trim((string)env('OPS_INTAKE_CREATED_BY', 'ivan')));
    $stmt = $conn->prepare("SELECT id FROM users WHERE worker_key = :worker_key AND status = 'active' LIMIT 1");
    $stmt->execute([':worker_key' => $workerKey]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $fallback = (int)$conn->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($fallback <= 0) {
        json_response(['ok' => false, 'error' => 'no active creator user configured'], 500);
    }
    return $fallback;
}

function project_id_from_key(PDO $conn, ?string $projectKey): ?int
{
    $projectKey = strtolower(trim((string)$projectKey));
    if ($projectKey === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT id FROM projects WHERE project_key = :project_key AND status = 'active' LIMIT 1");
    $stmt->execute([':project_key' => $projectKey]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function user_id_from_worker_key(PDO $conn, ?string $workerKey): ?int
{
    $workerKey = strtolower(trim((string)$workerKey));
    if ($workerKey === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT id FROM users WHERE worker_key = :worker_key AND status = 'active' LIMIT 1");
    $stmt->execute([':worker_key' => $workerKey]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function worker_project_context(?string $rawContext, string $workerKey, string $localPath, string $sshTarget): ?string
{
    $context = json_decode((string)$rawContext, true);
    if (!is_array($context)) {
        return null;
    }
    foreach ([
        'local_path_current_machine', 'local_machine_owner', 'local_path_ivan', 'local_path_oscar',
        'server_ssh', 'server_ssh_ivan', 'server_ssh_oscar',
    ] as $field) {
        unset($context[$field]);
    }
    $context['local_worker'] = $workerKey;
    $context['local_path'] = $localPath !== '' ? $localPath : null;
    $context['server_ssh_target'] = $sshTarget !== '' ? $sshTarget : null;
    return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
}

function existing_intake_response(PDO $conn, string $sourceChannel, string $externalRef): ?array
{
    if ($externalRef === '') {
        return null;
    }

    $stmt = $conn->prepare("
      SELECT
        i.id AS intake_id, i.status AS intake_status, i.detected_intent, i.urgency, i.confidence,
        t.id AS ticket_id, t.code AS ticket_code, t.status AS ticket_status
      FROM intake_items i
      LEFT JOIN tickets t ON t.intake_id = i.id
      WHERE i.source_channel = :source_channel
        AND i.external_ref = :external_ref
      LIMIT 1
    ");
    $stmt->execute([
        ':source_channel' => $sourceChannel,
        ':external_ref' => $externalRef,
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'ok' => true,
        'duplicate' => true,
        'intake_id' => (int)$row['intake_id'],
        'intake_status' => $row['intake_status'],
        'ticket_id' => $row['ticket_id'] !== null ? (int)$row['ticket_id'] : null,
        'ticket_code' => $row['ticket_code'],
        'ticket_status' => $row['ticket_status'],
        'classification' => [
            'intent' => $row['detected_intent'],
            'urgency' => $row['urgency'],
            'confidence' => (float)$row['confidence'],
        ],
    ];
}

function worker_from_request(PDO $conn): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', (string)$authHeader, $m)) {
        $token = trim($m[1]);
    }
    if ($token === '') {
        $token = trim((string)($_GET['token'] ?? ''));
    }

    $workerKey = trim((string)($_GET['worker_key'] ?? ''));
    if ($workerKey === '' || $token === '') {
        json_response(['ok' => false, 'error' => 'worker_key/token required'], 401);
    }

    $stmt = $conn->prepare("SELECT id, worker_key, name, worker_token FROM users WHERE worker_key = :worker_key AND status = 'active' LIMIT 1");
    $stmt->execute([':worker_key' => $workerKey]);
    $worker = $stmt->fetch();
    if (!$worker || !hash_equals((string)$worker['worker_token'], $token)) {
        json_response(['ok' => false, 'error' => 'invalid worker credentials'], 403);
    }

    return $worker;
}

if ($path === '/api/worker/tasks' && $method === 'GET') {
    $worker = worker_from_request($conn);
    $stmt = $conn->prepare("
      SELECT
        t.id, t.code, t.title, t.description, t.client_name, t.client_contact,
        t.source_channel, t.intent, t.urgency, t.status, t.client_reply_draft,
        p.name AS project_name, p.local_path_ivan, p.local_path_oscar,
        p.server_ssh, p.server_ssh_ivan, p.server_ssh_oscar, p.repo_url, p.codex_rules,
        p.aliases AS project_aliases, p.operational_context AS project_operational_context,
        (
          SELECT answer.body
          FROM ticket_events answer
          WHERE answer.ticket_id = t.id AND answer.event_type = 'telegram_answer'
          ORDER BY answer.id DESC
          LIMIT 1
        ) AS latest_human_answer
      FROM tickets t
      LEFT JOIN projects p ON p.id = t.project_id
      WHERE t.assigned_user_id = :uid
        AND t.status IN ('nuevo','asignado','en_propuesta')
        AND NOT EXISTS (
          SELECT 1
          FROM ticket_events question
          WHERE question.ticket_id = t.id
            AND question.event_type = 'telegram_question'
            AND NOT EXISTS (
              SELECT 1
              FROM ticket_events answer
              WHERE answer.ticket_id = t.id
                AND answer.event_type = 'telegram_answer'
                AND answer.id > question.id
            )
        )
      ORDER BY FIELD(t.urgency,'alta','media','baja'), t.created_at ASC
      LIMIT 10
    ");
    $stmt->execute([':uid' => (int)$worker['id']]);
    $workerKey = (string)$worker['worker_key'];
    $tickets = $stmt->fetchAll();
    foreach ($tickets as &$ticket) {
        $isOscar = $workerKey === 'oscar';
        $localPath = trim((string)($ticket[$isOscar ? 'local_path_oscar' : 'local_path_ivan'] ?? ''));
        $sshTarget = trim((string)($ticket[$isOscar ? 'server_ssh_oscar' : 'server_ssh_ivan'] ?? ''));
        $ticket['local_path'] = $localPath;
        $ticket['server_ssh_target'] = $sshTarget;
        $ticket['project_operational_context'] = worker_project_context(
            $ticket['project_operational_context'] ?? null,
            $workerKey,
            $localPath,
            $sshTarget
        );
        unset(
            $ticket['local_path_ivan'],
            $ticket['local_path_oscar'],
            $ticket['server_ssh'],
            $ticket['server_ssh_ivan'],
            $ticket['server_ssh_oscar']
        );
    }
    unset($ticket);
    json_response(['ok' => true, 'worker' => $workerKey, 'tickets' => $tickets]);
}

if ($path === '/api/worker/updates' && $method === 'GET') {
    $worker = worker_from_request($conn);
    $sinceId = max(0, (int)($_GET['since_id'] ?? 0));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $stmt = $conn->prepare(
        "SELECT te.id, t.id AS ticket_id, t.code AS ticket_code, te.event_type, te.body, te.created_at
         FROM ticket_events te
         INNER JOIN tickets t ON t.id = te.ticket_id
         WHERE t.assigned_user_id = :uid
           AND te.id > :since_id
           AND te.event_type IN (
             'telegram_answer',
             'implementation_authorized',
             'implementation_rejected',
             'deployment_authorized',
             'deployment_rejected'
           )
         ORDER BY te.id ASC
         LIMIT {$limit}"
    );
    $stmt->execute([':uid' => (int)$worker['id'], ':since_id' => $sinceId]);
    $events = $stmt->fetchAll();
    $nextSinceId = $sinceId;
    foreach ($events as $event) {
        $nextSinceId = max($nextSinceId, (int)$event['id']);
    }
    json_response([
        'ok' => true,
        'worker' => $worker['worker_key'],
        'since_id' => $sinceId,
        'next_since_id' => $nextSinceId,
        'events' => $events,
    ]);
}

if ($path === '/api/worker/proposals' && $method === 'POST') {
    $worker = worker_from_request($conn);
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'invalid json'], 400);
    }

    $ticketId = (int)($input['ticket_id'] ?? 0);
    $ticketCode = strtoupper(trim((string)($input['ticket_code'] ?? '')));
    $body = trim((string)($input['body'] ?? ''));
    $modelName = trim((string)($input['model_name'] ?? 'local-model'));
    $source = strtolower(trim((string)($input['source'] ?? 'local_model')));
    $clientReply = trim((string)($input['client_reply_draft'] ?? ''));
    if (($ticketId <= 0 && $ticketCode === '') || $body === '') {
        json_response(['ok' => false, 'error' => 'ticket_id or ticket_code, and body required'], 400);
    }
    if (!in_array($source, ['local_model', 'codex', 'manual'], true)) {
        json_response(['ok' => false, 'error' => 'invalid proposal source'], 422);
    }

    if ($ticketId > 0) {
        $stmt = $conn->prepare("SELECT id, code FROM tickets WHERE id = :id AND assigned_user_id = :uid AND status NOT IN ('cerrado','descartado') LIMIT 1");
        $stmt->execute([':id' => $ticketId, ':uid' => (int)$worker['id']]);
    } else {
        $stmt = $conn->prepare("SELECT id, code FROM tickets WHERE code = :code AND assigned_user_id = :uid AND status NOT IN ('cerrado','descartado') LIMIT 1");
        $stmt->execute([':code' => $ticketCode, ':uid' => (int)$worker['id']]);
    }
    $ticket = $stmt->fetch();
    if (!$ticket) {
        json_response(['ok' => false, 'error' => 'ticket not assigned to worker'], 403);
    }
    $ticketId = (int)$ticket['id'];
    $ticketCode = (string)$ticket['code'];

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("
          INSERT INTO ticket_proposals (ticket_id, worker_user_id, source, model_name, status, body, client_reply_draft)
          VALUES (:ticket_id, :worker_user_id, :source, :model_name, 'ready', :body, :client_reply)
        ");
        $stmt->execute([
          ':ticket_id' => $ticketId,
          ':worker_user_id' => (int)$worker['id'],
          ':source' => $source,
          ':model_name' => $modelName !== '' ? $modelName : null,
          ':body' => $body,
          ':client_reply' => $clientReply !== '' ? $clientReply : null,
        ]);
        $proposalId = (int)$conn->lastInsertId();

        $stmt = $conn->prepare("UPDATE tickets SET status = 'en_revision', client_reply_draft = COALESCE(:reply, client_reply_draft) WHERE id = :id LIMIT 1");
        $stmt->execute([':reply' => $clientReply !== '' ? $clientReply : null, ':id' => $ticketId]);

        add_event($conn, $ticketId, (int)$worker['id'], 'proposal_ready', 'Propuesta ' . $source . ' lista para revision.');

        $stmt = $conn->prepare("INSERT INTO worker_runs (worker_user_id, ticket_id, status, message) VALUES (:worker_id, :ticket_id, 'completed', :message)");
        $stmt->execute([':worker_id' => (int)$worker['id'], ':ticket_id' => $ticketId, ':message' => 'Proposal #' . $proposalId . ' uploaded']);

        $conn->commit();
        json_response(['ok' => true, 'proposal_id' => $proposalId, 'ticket_code' => $ticketCode, 'source' => $source]);
    } catch (Throwable $e) {
        $conn->rollBack();
        json_response(['ok' => false, 'error' => 'proposal save failed'], 500);
    }
}

if ($path === '/api/tickets/telegram-actions' && $method === 'POST') {
    require_integration_token();
    $input = json_input();
    $ticketCode = strtoupper(trim((string)($input['ticket_code'] ?? '')));
    $action = strtolower(trim((string)($input['action'] ?? '')));
    if ($action === 'approve') {
        $action = 'approve_changes';
    }
    $body = trim((string)($input['body'] ?? ''));
    $workerKey = strtolower(trim((string)($input['worker_key'] ?? '')));
    $telegramUserId = trim((string)($input['telegram_user_id'] ?? ''));
    $telegramUsername = trim((string)($input['telegram_username'] ?? ''));

    $allowedActions = [
        'approve_changes',
        'approve_deploy',
        'reject',
        'reject_deploy',
        'question',
        'answer',
    ];
    if ($ticketCode === '' || !in_array($action, $allowedActions, true) || $workerKey === '') {
        json_response(['ok' => false, 'error' => 'ticket_code/action/worker_key required'], 422);
    }
    if (in_array($action, ['question', 'answer', 'reject', 'reject_deploy'], true) && $body === '') {
        json_response(['ok' => false, 'error' => 'body required for this action'], 422);
    }

    $actorId = user_id_from_worker_key($conn, $workerKey);
    if ($actorId === null) {
        json_response(['ok' => false, 'error' => 'invalid worker_key'], 403);
    }

    $stmt = $conn->prepare("SELECT id, code, status FROM tickets WHERE code = :code LIMIT 1");
    $stmt->execute([':code' => $ticketCode]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        json_response(['ok' => false, 'error' => 'ticket not found'], 404);
    }

    $ticketId = (int)$ticket['id'];
    $actorLabel = 'Telegram ' . ($telegramUsername !== '' ? '@' . $telegramUsername : $telegramUserId);
    $auditBody = trim($actorLabel . ' (' . $workerKey . '). ' . $body);

    if (in_array($action, ['question', 'answer'], true)) {
        $newTicketStatus = (string)$ticket['status'];
        if ($action === 'question' && in_array($newTicketStatus, ['nuevo', 'asignado'], true)) {
            $newTicketStatus = 'en_propuesta';
        } elseif ($action === 'answer' && $newTicketStatus === 'en_propuesta') {
            $newTicketStatus = 'asignado';
        }

        if ($newTicketStatus !== (string)$ticket['status']) {
            $stmt = $conn->prepare("UPDATE tickets SET status = :status WHERE id = :id LIMIT 1");
            $stmt->execute([':status' => $newTicketStatus, ':id' => $ticketId]);
        }
        add_event($conn, $ticketId, $actorId, 'telegram_' . $action, mb_substr($auditBody, 0, 4000));
        json_response([
            'ok' => true,
            'action' => $action,
            'ticket_id' => $ticketId,
            'ticket_code' => $ticket['code'],
            'ticket_status' => $newTicketStatus,
        ]);
    }

    $stmt = $conn->prepare("SELECT id, status FROM ticket_proposals WHERE ticket_id = :ticket_id ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':ticket_id' => $ticketId]);
    $proposal = $stmt->fetch();
    if (!$proposal) {
        json_response(['ok' => false, 'error' => 'ticket has no proposal'], 409);
    }

    $proposalId = (int)$proposal['id'];
    $proposalStatus = (string)$proposal['status'];

    if (in_array($action, ['approve_deploy', 'reject_deploy'], true)) {
        if ($proposalStatus !== 'approved') {
            json_response(['ok' => false, 'error' => 'changes must be approved before deployment decision'], 409);
        }

        $stmt = $conn->prepare(
            "SELECT id FROM ticket_events WHERE ticket_id = :ticket_id AND event_type = 'implementation_authorized' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':ticket_id' => $ticketId]);
        $implementationEventId = (int)($stmt->fetchColumn() ?: 0);
        if ($implementationEventId <= 0) {
            json_response(['ok' => false, 'error' => 'implementation authorization not found'], 409);
        }

        $eventType = $action === 'approve_deploy' ? 'deployment_authorized' : 'deployment_rejected';
        $stmt = $conn->prepare(
            "SELECT id FROM ticket_events WHERE ticket_id = :ticket_id AND event_type = :event_type AND id > :after_id ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':event_type' => $eventType,
            ':after_id' => $implementationEventId,
        ]);
        $existingDecisionId = (int)($stmt->fetchColumn() ?: 0);
        if ($existingDecisionId > 0) {
            json_response([
                'ok' => true,
                'already' => true,
                'action' => $action,
                'ticket_id' => $ticketId,
                'ticket_code' => $ticket['code'],
                'ticket_status' => $ticket['status'],
                'proposal_id' => $proposalId,
                'proposal_status' => $proposalStatus,
            ]);
        }

        $eventBody = $action === 'approve_deploy'
            ? trim($auditBody . ' Autorizacion explicita para desplegar la propuesta #' . $proposalId . '.')
            : trim($auditBody . ' Despliegue de la propuesta #' . $proposalId . ' rechazado.');
        add_event($conn, $ticketId, $actorId, $eventType, mb_substr($eventBody, 0, 4000));
        json_response([
            'ok' => true,
            'action' => $action,
            'ticket_id' => $ticketId,
            'ticket_code' => $ticket['code'],
            'ticket_status' => $ticket['status'],
            'proposal_id' => $proposalId,
            'proposal_status' => $proposalStatus,
        ]);
    }

    if ($action === 'approve_changes' && $proposalStatus === 'approved') {
        json_response([
            'ok' => true,
            'already' => true,
            'action' => $action,
            'ticket_id' => $ticketId,
            'ticket_code' => $ticket['code'],
            'ticket_status' => $ticket['status'],
            'proposal_id' => $proposalId,
        ]);
    }
    if ($action === 'reject' && $proposalStatus === 'rejected') {
        json_response([
            'ok' => true,
            'already' => true,
            'action' => $action,
            'ticket_id' => $ticketId,
            'ticket_code' => $ticket['code'],
            'ticket_status' => $ticket['status'],
            'proposal_id' => $proposalId,
        ]);
    }
    if ($proposalStatus !== 'ready') {
        json_response(['ok' => false, 'error' => 'latest proposal is not awaiting decision'], 409);
    }

    $newProposalStatus = $action === 'approve_changes' ? 'approved' : 'rejected';
    $newTicketStatus = $action === 'approve_changes' ? 'en_progreso' : 'en_revision';
    $eventType = $action === 'approve_changes' ? 'implementation_authorized' : 'implementation_rejected';
    $eventBody = $action === 'approve_changes'
        ? trim($auditBody . ' Autorizacion explicita para implementar la propuesta #' . $proposalId . '.')
        : trim($auditBody . ' Propuesta #' . $proposalId . ' rechazada; requiere revision.');

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("UPDATE ticket_proposals SET status = :proposal_status WHERE id = :id LIMIT 1");
        $stmt->execute([':proposal_status' => $newProposalStatus, ':id' => $proposalId]);
        $stmt = $conn->prepare("UPDATE tickets SET status = :ticket_status WHERE id = :id LIMIT 1");
        $stmt->execute([':ticket_status' => $newTicketStatus, ':id' => $ticketId]);
        add_event($conn, $ticketId, $actorId, $eventType, mb_substr($eventBody, 0, 4000));
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollBack();
        json_response(['ok' => false, 'error' => 'telegram action failed'], 500);
    }

    json_response([
        'ok' => true,
        'action' => $action,
        'ticket_id' => $ticketId,
        'ticket_code' => $ticket['code'],
        'ticket_status' => $newTicketStatus,
        'proposal_id' => $proposalId,
        'proposal_status' => $newProposalStatus,
    ]);
}

if ($path === '/api/intake/messages' && $method === 'POST') {
    require_integration_token();
    $input = json_input();

    $validChannels = ['whatsapp', 'audio', 'email', 'phone', 'manual', 'web', 'other'];
    $sourceChannel = strtolower(trim((string)($input['source_channel'] ?? 'whatsapp')));
    if (!in_array($sourceChannel, $validChannels, true)) {
        json_response(['ok' => false, 'error' => 'invalid source_channel'], 422);
    }

    $externalRef = trim((string)($input['external_ref'] ?? ''));
    $duplicate = existing_intake_response($conn, $sourceChannel, $externalRef);
    if ($duplicate !== null) {
        json_response($duplicate);
    }

    $transcript = trim((string)($input['transcript'] ?? ''));
    if ($transcript === '') {
        json_response(['ok' => false, 'error' => 'transcript required'], 422);
    }

    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        $title = mb_substr((string)preg_replace('/\s+/', ' ', $transcript), 0, 120);
    }

    $audio = is_array($input['audio'] ?? null) ? $input['audio'] : [];
    $metadata = [
        'received_at' => $input['received_at'] ?? null,
        'audio' => $audio ?: null,
        'sender' => $input['sender'] ?? null,
        'raw_payload' => $input['raw_payload'] ?? null,
    ];
    $metadata = array_filter($metadata, static fn ($value): bool => $value !== null && $value !== []);
    $rawNotes = trim((string)($input['raw_notes'] ?? ''));
    if ($metadata) {
        $rawNotes = trim($rawNotes . "\n\nMetadata:\n" . json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $classification = Classifier::classify(
        $conn,
        $title,
        $transcript,
        trim((string)($input['client_contact'] ?? ''))
    );
    $projectOverride = project_id_from_key($conn, $input['project_key'] ?? null);
    if ($projectOverride !== null) {
        $classification['project_id'] = $projectOverride;
    }
    $assignedOverride = user_id_from_worker_key($conn, $input['assigned_worker_key'] ?? null);
    if ($assignedOverride !== null) {
        $classification['assigned_user_id'] = $assignedOverride;
    }

    $createTicket = !array_key_exists('create_ticket', $input) || (bool)$input['create_ticket'];
    $creatorId = integration_creator_id($conn);

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("
          INSERT INTO intake_items
            (source_channel, external_ref, client_name, client_contact, title, transcript, raw_notes,
             project_id, assigned_user_id, detected_intent, urgency, confidence, status,
             ai_summary, codex_prompt, client_reply_draft, created_by_user_id)
          VALUES
            (:source_channel, :external_ref, :client_name, :client_contact, :title, :transcript, :raw_notes,
             :project_id, :assigned_user_id, :detected_intent, :urgency, :confidence, 'clasificado',
             :ai_summary, :codex_prompt, :client_reply_draft, :created_by_user_id)
        ");
        $stmt->execute([
            ':source_channel' => $sourceChannel,
            ':external_ref' => $externalRef !== '' ? $externalRef : null,
            ':client_name' => trim((string)($input['client_name'] ?? '')) ?: null,
            ':client_contact' => trim((string)($input['client_contact'] ?? '')) ?: null,
            ':title' => mb_substr($title, 0, 180),
            ':transcript' => $transcript,
            ':raw_notes' => $rawNotes !== '' ? $rawNotes : null,
            ':project_id' => $classification['project_id'],
            ':assigned_user_id' => $classification['assigned_user_id'],
            ':detected_intent' => $classification['detected_intent'],
            ':urgency' => $classification['urgency'],
            ':confidence' => $classification['confidence'],
            ':ai_summary' => $classification['ai_summary'],
            ':codex_prompt' => $classification['codex_prompt'],
            ':client_reply_draft' => $classification['client_reply_draft'],
            ':created_by_user_id' => $creatorId,
        ]);
        $intakeId = (int)$conn->lastInsertId();

        $ticketId = null;
        $ticketCode = null;
        $ticketStatus = null;
        if ($createTicket) {
            $ticketStatus = !empty($classification['assigned_user_id']) ? 'asignado' : 'nuevo';
            $description = "Resumen IA:\n" . (string)$classification['ai_summary'] . "\n\nPrompt Codex:\n" . (string)$classification['codex_prompt'] . "\n\nTranscripcion:\n" . $transcript;
            $stmt = $conn->prepare("
              INSERT INTO tickets
                (intake_id, project_id, assigned_user_id, title, description, client_name, client_contact,
                 source_channel, intent, urgency, status, client_reply_draft, created_by_user_id)
              VALUES
                (:intake_id, :project_id, :assigned_user_id, :title, :description, :client_name, :client_contact,
                 :source_channel, :intent, :urgency, :status, :client_reply_draft, :created_by)
            ");
            $stmt->execute([
                ':intake_id' => $intakeId,
                ':project_id' => $classification['project_id'],
                ':assigned_user_id' => $classification['assigned_user_id'],
                ':title' => mb_substr($title, 0, 180),
                ':description' => $description,
                ':client_name' => trim((string)($input['client_name'] ?? '')) ?: null,
                ':client_contact' => trim((string)($input['client_contact'] ?? '')) ?: null,
                ':source_channel' => $sourceChannel,
                ':intent' => $classification['detected_intent'],
                ':urgency' => $classification['urgency'],
                ':status' => $ticketStatus,
                ':client_reply_draft' => $classification['client_reply_draft'],
                ':created_by' => $creatorId,
            ]);
            $ticketId = (int)$conn->lastInsertId();
            $ticketCode = generate_ticket_code($conn, $ticketId);
            add_event($conn, $ticketId, $creatorId, 'ticket_created', 'Ticket creado desde API de intake.');

            $stmt = $conn->prepare("UPDATE intake_items SET status = 'ticket_creado' WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $intakeId]);
        }

        $conn->commit();
        json_response([
            'ok' => true,
            'duplicate' => false,
            'intake_id' => $intakeId,
            'intake_status' => $createTicket ? 'ticket_creado' : 'clasificado',
            'ticket_id' => $ticketId,
            'ticket_code' => $ticketCode,
            'ticket_status' => $ticketStatus,
            'classification' => [
                'intent' => $classification['detected_intent'],
                'urgency' => $classification['urgency'],
                'confidence' => (float)$classification['confidence'],
                'assigned_user_id' => $classification['assigned_user_id'],
                'project_id' => $classification['project_id'],
            ],
            'client_reply_draft' => $classification['client_reply_draft'],
        ], 201);
    } catch (Throwable $e) {
        $conn->rollBack();
        $duplicate = existing_intake_response($conn, $sourceChannel, $externalRef);
        if ($duplicate !== null) {
            json_response($duplicate);
        }
        json_response(['ok' => false, 'error' => 'intake save failed'], 500);
    }
}

if ($path === '/api/outbox/client-replies' && $method === 'GET') {
    require_integration_token();
    $status = trim((string)($_GET['status'] ?? 'aprobado'));
    $allowed = ['aprobado', 'en_revision', 'resuelto'];
    if (!in_array($status, $allowed, true)) {
        json_response(['ok' => false, 'error' => 'invalid status'], 422);
    }
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $stmt = $conn->prepare("
      SELECT
        t.id, t.code, t.title, t.client_name, t.client_contact, t.source_channel,
        t.status, t.client_reply_draft, t.updated_at, t.created_at,
        i.external_ref AS intake_external_ref
      FROM tickets t
      LEFT JOIN intake_items i ON i.id = t.intake_id
      WHERE t.status = :status
        AND t.client_reply_draft IS NOT NULL
        AND t.client_reply_draft <> ''
      ORDER BY COALESCE(t.updated_at, t.created_at) ASC
      LIMIT {$limit}
    ");
    $stmt->execute([':status' => $status]);
    json_response(['ok' => true, 'status' => $status, 'items' => $stmt->fetchAll()]);
}

if ($path === '/api/outbox/client-replies/ack' && $method === 'POST') {
    require_integration_token();
    $input = json_input();
    $ticketId = (int)($input['ticket_id'] ?? 0);
    if ($ticketId <= 0) {
        json_response(['ok' => false, 'error' => 'ticket_id required'], 422);
    }

    $stmt = $conn->prepare("SELECT id FROM tickets WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $ticketId]);
    if (!$stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'ticket not found'], 404);
    }

    $externalRef = trim((string)($input['external_ref'] ?? ''));
    $sentText = trim((string)($input['sent_text'] ?? ''));
    $body = 'Respuesta enviada por integracion.';
    if ($externalRef !== '') {
        $body .= ' Ref: ' . $externalRef . '.';
    }
    if ($sentText !== '') {
        $body .= "\n\n" . $sentText;
    }
    add_event($conn, $ticketId, null, 'client_reply_sent', $body);

    json_response(['ok' => true, 'ticket_id' => $ticketId]);
}

if ($path === '/login' && $method === 'GET') {
    Security::startSession();
    $error = trim((string)($_GET['error'] ?? ''));
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | ArgotesIA Ops</title>
  <script>
    (() => {
      const saved = localStorage.getItem('ops-theme');
      const theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.dataset.theme = theme;
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--ink:oklch(24% .015 248);--muted:oklch(53% .025 250);--line:oklch(88% .018 250);--bg:oklch(97% .01 250);--surface:oklch(99% .006 250);--field:oklch(99% .006 250)}
    :root[data-theme="dark"]{--ink:oklch(91% .012 250);--muted:oklch(70% .02 250);--line:oklch(33% .02 250);--bg:oklch(18% .015 250);--surface:oklch(23% .018 250);--field:oklch(20% .018 250)}
    body{background:var(--bg);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif}
    .card{max-width:420px;margin:9vh auto;border-radius:10px;background:var(--surface);border:1px solid var(--line)}
    .text-muted{color:var(--muted)!important}
    .form-control{background:var(--field);border-color:var(--line);color:var(--ink)}
    .form-control::placeholder{color:var(--muted)}
    .theme-toggle{position:fixed;right:18px;top:18px}
  </style>
</head>
<body>
  <button class="btn btn-outline-secondary btn-sm theme-toggle" type="button" data-theme-toggle>Oscuro</button>
  <div class="card shadow-sm border-0 p-4">
    <div class="h4 fw-bold mb-1">ArgotesIA Ops</div>
    <div class="text-muted mb-3">Operacion interna Ivan/Oscar</div>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="<?= h(url_for('/login')) ?>">
      <input type="hidden" name="_csrf" value="<?= h(Security::csrfToken()) ?>">
      <label class="form-label">Usuario o correo</label>
      <input class="form-control mb-2" type="text" name="login" required autocomplete="username" placeholder="ivan o ivan@argotes.com">
      <label class="form-label">Password</label>
      <input class="form-control mb-3" type="password" name="password" required autocomplete="current-password">
      <button class="btn btn-primary w-100" type="submit">Entrar</button>
    </form>
  </div>
  <script>
  (() => {
    const root = document.documentElement;
    const button = document.querySelector('[data-theme-toggle]');
    const applyTheme = (theme) => {
      root.dataset.theme = theme;
      root.setAttribute('data-bs-theme', theme);
      localStorage.setItem('ops-theme', theme);
      if (button) button.textContent = theme === 'dark' ? 'Claro' : 'Oscuro';
    };
    applyTheme(root.dataset.theme || 'light');
    if (button) {
      button.addEventListener('click', () => applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'));
    }
  })();
  </script>
</body>
</html>
<?php
    exit;
}

if ($path === '/login' && $method === 'POST') {
    Security::requireCsrf();
    if ($auth->attempt((string)($_POST['login'] ?? $_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
        redirect((string)($_GET['return'] ?? url_for('/')));
    }
    redirect(url_for('/login') . '&error=' . urlencode('Credenciales invalidas.'));
}

if ($path === '/logout') {
    $auth->logout();
    redirect(url_for('/login'));
}

$user = $auth->requireAuth();

if ($path === '/change-password' && $method === 'GET') {
    layout('Cambiar clave', $user, function (string $csrf) {
        ?>
  <div class="row justify-content-center">
    <div class="col-12 col-lg-5">
      <div class="panel p-4">
        <div class="h5 fw-bold mb-1">Cambiar clave</div>
        <div class="text-muted mb-3">Actualiza tu acceso local a ArgotesIA Ops.</div>
        <form method="post" action="<?= h(url_for('/change-password')) ?>">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <label class="form-label">Clave actual</label>
          <input class="form-control soft mb-3" type="password" name="current_password" autocomplete="current-password" required>
          <label class="form-label">Nueva clave</label>
          <input class="form-control soft mb-3 js-password-main" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
          <label class="form-label">Confirmar nueva clave</label>
          <input class="form-control soft mb-3 js-password-confirm" type="password" name="new_password_confirm" minlength="8" autocomplete="new-password" required>
          <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-light" href="<?= h(url_for('/')) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit">Guardar clave</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script>
  document.querySelectorAll('form').forEach((form) => {
    const password = form.querySelector('.js-password-main');
    const confirm = form.querySelector('.js-password-confirm');
    if (!password || !confirm) return;
    const validate = () => confirm.setCustomValidity(password.value === confirm.value ? '' : 'Las claves no coinciden.');
    password.addEventListener('input', validate);
    confirm.addEventListener('input', validate);
    form.addEventListener('submit', validate);
  });
  </script>
<?php
    });
    exit;
}

if ($path === '/change-password' && $method === 'POST') {
    Security::requireCsrf();
    $currentPassword = trim((string)($_POST['current_password'] ?? ''));
    $newPassword = trim((string)($_POST['new_password'] ?? ''));
    $newPasswordConfirm = trim((string)($_POST['new_password_confirm'] ?? ''));

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute([':id' => (int)$user['id']]);
    $currentUser = $stmt->fetch();
    if (!$currentUser || !password_verify($currentPassword, (string)$currentUser['password_hash'])) {
        flash_set('error', 'La clave actual no es correcta.');
        redirect(url_for('/change-password'));
    }
    if (strlen($newPassword) < 8) {
        flash_set('error', 'La nueva clave debe tener al menos 8 caracteres.');
        redirect(url_for('/change-password'));
    }
    if ($newPassword !== $newPasswordConfirm) {
        flash_set('error', 'La confirmacion de clave no coincide.');
        redirect(url_for('/change-password'));
    }

    $stmt = $conn->prepare("UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id LIMIT 1");
    $stmt->execute([
        ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => (int)$user['id'],
    ]);
    flash_set('success', 'Clave actualizada correctamente.');
    redirect(url_for('/'));
}

if ($path === '/users' && $method === 'GET') {
    require_admin_user($user);
    $items = admin_users($conn);
    $stats = [
        'total' => count($items),
        'active' => count(array_filter($items, static fn (array $item): bool => $item['status'] === 'active')),
        'admins' => count(array_filter($items, static fn (array $item): bool => $item['role'] === 'admin' && $item['status'] === 'active')),
    ];
    $roles = user_roles();
    layout('Usuarios', $user, function (string $csrf) use ($items, $stats, $roles) {
        ?>
  <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
    <div>
      <div class="h4 fw-bold mb-1">Usuarios internos</div>
      <div class="text-muted">Accesos para Ivan/Oscar y operadores del motor.</div>
    </div>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createUserModal">
      <i class="bi bi-person-plus me-1"></i>Nuevo usuario
    </button>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-4"><div class="metric"><strong><?= (int)$stats['total'] ?></strong><div class="text-muted small">Total</div></div></div>
    <div class="col-4"><div class="metric"><strong><?= (int)$stats['active'] ?></strong><div class="text-muted small">Activos</div></div></div>
    <div class="col-4"><div class="metric"><strong><?= (int)$stats['admins'] ?></strong><div class="text-muted small">Admins</div></div></div>
  </div>

  <div class="panel p-0 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th class="ps-3">Persona</th><th>Acceso</th><th>Agente local</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold"><?= h((string)$item['name']) ?></div>
              <div class="small text-muted"><?= h((string)$item['email']) ?></div>
              <div class="small mono"><?= h((string)$item['username']) ?></div>
            </td>
            <td><span class="badge text-bg-<?= $item['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= h($roles[$item['role']] ?? (string)$item['role']) ?></span></td>
            <td>
              <span class="mono"><?= h((string)$item['worker_key']) ?></span>
              <?php if ((int)$item['must_change_password'] === 1): ?><div class="small text-warning">Clave temporal</div><?php endif; ?>
            </td>
            <td><span class="badge text-bg-<?= $item['status'] === 'active' ? 'success' : 'light text-secondary border' ?>"><?= h((string)$item['status']) ?></span></td>
            <td class="text-end pe-3">
              <button
                class="btn btn-sm btn-outline-primary js-edit-user"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#editUserModal"
                data-id="<?= (int)$item['id'] ?>"
                data-name="<?= h((string)$item['name']) ?>"
                data-username="<?= h((string)$item['username']) ?>"
                data-worker-key="<?= h((string)$item['worker_key']) ?>"
                data-email="<?= h((string)$item['email']) ?>"
                data-role="<?= h((string)$item['role']) ?>"
                data-status="<?= h((string)$item['status']) ?>"
              ><i class="bi bi-pencil-square"></i></button>
              <button
                class="btn btn-sm btn-outline-secondary js-reset-user"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#resetPasswordModal"
                data-id="<?= (int)$item['id'] ?>"
                data-name="<?= h((string)$item['name']) ?>"
              ><i class="bi bi-key"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="text-muted p-4">Sin usuarios registrados.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="post" action="<?= h(url_for('/users/create')) ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <div class="modal-header"><h5 class="modal-title">Nuevo usuario</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
          <label class="form-label">Nombre</label>
          <input class="form-control mb-2" name="name" required>
          <label class="form-label">Usuario</label>
          <input class="form-control mb-2" name="username" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]{3,80}" autocomplete="username">
          <label class="form-label">Clave agente local</label>
          <input class="form-control mb-2" name="worker_key" minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]{3,40}" placeholder="Opcional, por defecto igual al usuario">
          <label class="form-label">Correo</label>
          <input class="form-control mb-2" type="email" name="email" required>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Rol</label>
              <select class="form-select" name="role"><?php foreach ($roles as $role => $label): ?><option value="<?= h($role) ?>"><?= h($label) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-6">
              <label class="form-label">Estado</label>
              <select class="form-select" name="status"><option value="active">Activo</option><option value="paused">Pausado</option></select>
            </div>
          </div>
          <label class="form-label mt-2">Clave temporal</label>
          <input class="form-control mb-2 js-password-main" type="password" name="password" minlength="8" autocomplete="new-password" required>
          <label class="form-label">Confirmar clave temporal</label>
          <input class="form-control js-password-confirm" type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Crear</button></div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="post" action="<?= h(url_for('/users/update')) ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" id="edit-id">
        <div class="modal-header"><h5 class="modal-title">Editar usuario</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
          <label class="form-label">Nombre</label>
          <input class="form-control mb-2" name="name" id="edit-name" required>
          <label class="form-label">Usuario</label>
          <input class="form-control mb-2" name="username" id="edit-username" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]{3,80}">
          <label class="form-label">Clave agente local</label>
          <input class="form-control mb-2" name="worker_key" id="edit-worker-key" required minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]{3,40}">
          <div class="form-text mb-2">Cambiar esta clave requiere actualizar el agente local en esa Mac.</div>
          <label class="form-label">Correo</label>
          <input class="form-control mb-2" type="email" name="email" id="edit-email" required>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Rol</label>
              <select class="form-select" name="role" id="edit-role"><?php foreach ($roles as $role => $label): ?><option value="<?= h($role) ?>"><?= h($label) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-6">
              <label class="form-label">Estado</label>
              <select class="form-select" name="status" id="edit-status"><option value="active">Activo</option><option value="paused">Pausado</option></select>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="post" action="<?= h(url_for('/users/reset-password')) ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" id="reset-id">
        <div class="modal-header"><h5 class="modal-title">Resetear clave</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
          <p class="text-muted" id="reset-user-label"></p>
          <label class="form-label">Nueva clave temporal</label>
          <input class="form-control mb-2 js-password-main" type="password" name="password" minlength="8" autocomplete="new-password" required>
          <label class="form-label">Confirmar nueva clave temporal</label>
          <input class="form-control js-password-confirm" type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Actualizar</button></div>
      </form>
    </div>
  </div>

  <script>
  document.querySelectorAll('.js-edit-user').forEach((button) => {
    button.addEventListener('click', () => {
      document.getElementById('edit-id').value = button.dataset.id || '';
      document.getElementById('edit-name').value = button.dataset.name || '';
      document.getElementById('edit-username').value = button.dataset.username || '';
      document.getElementById('edit-worker-key').value = button.dataset.workerKey || '';
      document.getElementById('edit-email').value = button.dataset.email || '';
      document.getElementById('edit-role').value = button.dataset.role || 'operator';
      document.getElementById('edit-status').value = button.dataset.status || 'active';
    });
  });
  document.querySelectorAll('.js-reset-user').forEach((button) => {
    button.addEventListener('click', () => {
      document.getElementById('reset-id').value = button.dataset.id || '';
      document.getElementById('reset-user-label').textContent = button.dataset.name ? `Usuario: ${button.dataset.name}` : '';
    });
  });
  document.querySelectorAll('form').forEach((form) => {
    const password = form.querySelector('.js-password-main');
    const confirm = form.querySelector('.js-password-confirm');
    if (!password || !confirm) return;
    const validate = () => confirm.setCustomValidity(password.value === confirm.value ? '' : 'Las claves no coinciden.');
    password.addEventListener('input', validate);
    confirm.addEventListener('input', validate);
    form.addEventListener('submit', validate);
  });
  </script>
<?php
    });
    exit;
}

if ($path === '/users/create' && $method === 'POST') {
    require_admin_user($user);
    Security::requireCsrf();
    $input = normalize_user_input($_POST);
    $password = trim((string)($_POST['password'] ?? ''));
    $passwordConfirm = trim((string)($_POST['password_confirm'] ?? ''));
    $errors = validate_user_input($input);
    if (strlen($password) < 8) {
        $errors[] = 'La clave temporal debe tener al menos 8 caracteres.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'La confirmacion de clave no coincide.';
    }
    foreach (['email', 'username', 'worker_key'] as $field) {
        if (user_field_exists($conn, $field, (string)$input[$field])) {
            $errors[] = 'Ya existe un usuario con ' . $field . ' igual.';
        }
    }
    if ($errors) {
        flash_set('error', implode(' ', $errors));
        redirect(url_for('/users'));
    }

    $stmt = $conn->prepare("
      INSERT INTO users (worker_key, username, name, email, password_hash, role, worker_token, must_change_password, status)
      VALUES (:worker_key, :username, :name, :email, :password_hash, :role, :worker_token, 1, :status)
    ");
    $stmt->execute([
        ':worker_key' => $input['worker_key'],
        ':username' => $input['username'],
        ':name' => $input['name'],
        ':email' => $input['email'],
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => $input['role'],
        ':worker_token' => random_worker_token(),
        ':status' => $input['status'],
    ]);
    flash_set('success', 'Usuario creado correctamente.');
    redirect(url_for('/users'));
}

if ($path === '/users/update' && $method === 'POST') {
    require_admin_user($user);
    Security::requireCsrf();
    $input = normalize_user_input($_POST);
    $existing = find_user($conn, (int)$input['id']);
    if (!$existing) {
        flash_set('error', 'No se encontro el usuario solicitado.');
        redirect(url_for('/users'));
    }
    $errors = validate_user_input($input);
    foreach (['email', 'username', 'worker_key'] as $field) {
        if (user_field_exists($conn, $field, (string)$input[$field], (int)$input['id'])) {
            $errors[] = 'Ya existe otro usuario con ' . $field . ' igual.';
        }
    }
    if ((int)$input['id'] === (int)$user['id'] && $input['status'] !== 'active') {
        $errors[] = 'No puedes pausar tu propio usuario.';
    }
    if ($existing['role'] === 'admin' && ($input['role'] !== 'admin' || $input['status'] !== 'active') && count_active_admins($conn, (int)$input['id']) === 0) {
        $errors[] = 'Debe quedar al menos un administrador activo.';
    }
    if ($errors) {
        flash_set('error', implode(' ', $errors));
        redirect(url_for('/users'));
    }

    $stmt = $conn->prepare("
      UPDATE users
      SET worker_key = :worker_key, username = :username, name = :name, email = :email, role = :role, status = :status
      WHERE id = :id
      LIMIT 1
    ");
    $stmt->execute([
        ':id' => (int)$input['id'],
        ':worker_key' => $input['worker_key'],
        ':username' => $input['username'],
        ':name' => $input['name'],
        ':email' => $input['email'],
        ':role' => $input['role'],
        ':status' => $input['status'],
    ]);
    flash_set('success', 'Usuario actualizado correctamente.');
    redirect(url_for('/users'));
}

if ($path === '/users/reset-password' && $method === 'POST') {
    require_admin_user($user);
    Security::requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $password = trim((string)($_POST['password'] ?? ''));
    $passwordConfirm = trim((string)($_POST['password_confirm'] ?? ''));
    if (!find_user($conn, $id)) {
        flash_set('error', 'No se encontro el usuario solicitado.');
        redirect(url_for('/users'));
    }
    if (strlen($password) < 8) {
        flash_set('error', 'La clave temporal debe tener al menos 8 caracteres.');
        redirect(url_for('/users'));
    }
    if ($password !== $passwordConfirm) {
        flash_set('error', 'La confirmacion de clave no coincide.');
        redirect(url_for('/users'));
    }
    $stmt = $conn->prepare("UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id LIMIT 1");
    $stmt->execute([
        ':hash' => password_hash($password, PASSWORD_DEFAULT),
        ':id' => $id,
    ]);
    flash_set('success', 'Clave temporal actualizada correctamente.');
    redirect(url_for('/users'));
}

if ($path === '/') {
    $counts = [
        'intake' => (int)$conn->query("SELECT COUNT(*) FROM intake_items WHERE status IN ('nuevo','clasificado')")->fetchColumn(),
        'tickets' => (int)$conn->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('cerrado','descartado')")->fetchColumn(),
        'review' => (int)$conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'en_revision'")->fetchColumn(),
        'proposals' => (int)$conn->query("SELECT COUNT(*) FROM ticket_proposals WHERE status = 'ready'")->fetchColumn(),
    ];
    $stmt = $conn->prepare("
      SELECT t.*, u.name AS assignee_name, p.name AS project_name
      FROM tickets t
      LEFT JOIN users u ON u.id = t.assigned_user_id
      LEFT JOIN projects p ON p.id = t.project_id
      WHERE t.status NOT IN ('cerrado','descartado')
      ORDER BY FIELD(t.urgency,'alta','media','baja'), t.created_at DESC
      LIMIT 12
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll();

    layout('Dashboard', $user, function () use ($counts, $tickets) {
        ?>
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="metric"><strong><?= $counts['intake'] ?></strong><div class="text-muted small">Entradas pendientes</div></div></div>
    <div class="col-6 col-md-3"><div class="metric"><strong><?= $counts['tickets'] ?></strong><div class="text-muted small">Tickets activos</div></div></div>
    <div class="col-6 col-md-3"><div class="metric"><strong><?= $counts['review'] ?></strong><div class="text-muted small">En revision humana</div></div></div>
    <div class="col-6 col-md-3"><div class="metric"><strong><?= $counts['proposals'] ?></strong><div class="text-muted small">Propuestas listas</div></div></div>
  </div>
  <div class="panel p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-bold">Cola operativa</div>
      <a class="btn btn-primary btn-sm" href="<?= h(url_for('/intake')) ?>">Capturar WhatsApp</a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Ticket</th><th>Proyecto</th><th>Asignado</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td><strong><?= h((string)$ticket['code']) ?></strong><br><?= h((string)$ticket['title']) ?></td>
            <td><?= h((string)($ticket['project_name'] ?? '-')) ?></td>
            <td><?= h((string)($ticket['assignee_name'] ?? 'Sin asignar')) ?></td>
            <td><span class="badge text-bg-secondary"><?= h((string)$ticket['status']) ?></span> <span class="badge text-bg-warning"><?= h((string)$ticket['urgency']) ?></span></td>
            <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="<?= h(url_for('/tickets/view')) ?>&id=<?= (int)$ticket['id'] ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="5" class="text-muted">Sin tickets activos.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
    });
    exit;
}

if ($path === '/intake' && $method === 'GET') {
    $stmt = $conn->query("
      SELECT i.*, p.name AS project_name, u.name AS assigned_name,
             t.id AS ticket_id, t.code AS ticket_code
      FROM intake_items i
      LEFT JOIN projects p ON p.id = i.project_id
      LEFT JOIN users u ON u.id = i.assigned_user_id
      LEFT JOIN tickets t ON t.id = (
        SELECT MIN(existing_ticket.id) FROM tickets existing_ticket WHERE existing_ticket.intake_id = i.id
      )
      ORDER BY i.created_at DESC
      LIMIT 50
    ");
    $items = $stmt->fetchAll();
    $people = users($conn);

    layout('Intake', $user, function (string $csrf) use ($items, $people) {
        ?>
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="panel p-3">
        <div class="fw-bold mb-2">Capturar WhatsApp/audio</div>
        <form method="post" action="<?= h(url_for('/intake/create')) ?>">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small">Canal</label>
              <select class="form-select soft" name="source_channel">
                <option value="whatsapp">WhatsApp</option>
                <option value="audio">Audio</option>
                <option value="manual">Manual</option>
                <option value="email">Email</option>
                <option value="phone">Llamada</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small">Asignar directo</label>
              <select class="form-select soft" name="assigned_user_id">
                <option value="">Sugerir automatico</option>
                <?php foreach ($people as $person): ?><option value="<?= (int)$person['id'] ?>"><?= h((string)$person['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><input class="form-control soft" name="client_name" placeholder="Cliente"></div>
            <div class="col-6"><input class="form-control soft" name="client_contact" placeholder="Contacto"></div>
            <div class="col-12"><input class="form-control soft" name="title" required placeholder="Titulo"></div>
            <div class="col-12"><textarea class="form-control soft" name="transcript" rows="7" required placeholder="Transcripcion del audio o mensaje de WhatsApp"></textarea></div>
            <div class="col-12"><textarea class="form-control soft" name="raw_notes" rows="2" placeholder="Notas internas"></textarea></div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Capturar</button>
        </form>
      </div>
    </div>
    <div class="col-12 col-lg-7">
      <div class="panel p-3">
        <div class="fw-bold mb-2">Entradas</div>
        <?php foreach ($items as $item): ?>
          <div class="border rounded-3 p-3 mb-2">
            <div class="d-flex justify-content-between gap-2">
              <div><strong>#<?= (int)$item['id'] ?> <?= h((string)$item['title']) ?></strong><div class="small text-muted"><?= h((string)$item['source_channel']) ?> · <?= h((string)($item['client_name'] ?? '')) ?></div></div>
              <div><span class="badge text-bg-secondary"><?= h((string)$item['status']) ?></span> <span class="badge text-bg-warning"><?= h((string)$item['urgency']) ?></span></div>
            </div>
            <div class="small mt-2"><?= nl2br(h(mb_substr((string)$item['transcript'], 0, 380))) ?><?= mb_strlen((string)$item['transcript']) > 380 ? '...' : '' ?></div>
            <?php if (!empty($item['ai_summary'])): ?><pre class="mono bg-light p-2 rounded mt-2"><?= h((string)$item['ai_summary']) ?></pre><?php endif; ?>
            <div class="d-flex gap-2 mt-2">
              <form method="post" action="<?= h(url_for('/intake/classify')) ?>"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-outline-primary btn-sm">Clasificar</button></form>
              <?php if (!empty($item['ticket_id'])): ?>
                <a class="btn btn-outline-success btn-sm" href="<?= h(url_for('/tickets/view')) ?>&id=<?= (int)$item['ticket_id'] ?>">Abrir <?= h((string)$item['ticket_code']) ?></a>
              <?php else: ?>
                <form method="post" action="<?= h(url_for('/intake/create-ticket')) ?>"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-success btn-sm">Crear ticket</button></form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$items): ?><div class="text-muted">Sin entradas.</div><?php endif; ?>
      </div>
    </div>
  </div>
<?php
    });
    exit;
}

if ($path === '/intake/create' && $method === 'POST') {
    Security::requireCsrf();
    $title = trim((string)($_POST['title'] ?? ''));
    $transcript = trim((string)($_POST['transcript'] ?? ''));
    if ($title === '' || $transcript === '') {
        redirect(url_for('/intake'));
    }

    $result = Classifier::classify($conn, $title, $transcript);
    $assignedUserId = (int)($_POST['assigned_user_id'] ?? 0);
    if ($assignedUserId > 0) {
        $result['assigned_user_id'] = $assignedUserId;
    }

    $stmt = $conn->prepare("
      INSERT INTO intake_items
        (source_channel, client_name, client_contact, title, transcript, raw_notes,
         project_id, assigned_user_id, detected_intent, urgency, confidence, status,
         ai_summary, codex_prompt, client_reply_draft, created_by_user_id)
      VALUES
        (:source_channel, :client_name, :client_contact, :title, :transcript, :raw_notes,
         :project_id, :assigned_user_id, :detected_intent, :urgency, :confidence, 'clasificado',
         :ai_summary, :codex_prompt, :client_reply_draft, :created_by_user_id)
    ");
    $stmt->execute([
      ':source_channel' => $_POST['source_channel'] ?? 'manual',
      ':client_name' => trim((string)($_POST['client_name'] ?? '')) ?: null,
      ':client_contact' => trim((string)($_POST['client_contact'] ?? '')) ?: null,
      ':title' => mb_substr($title, 0, 180),
      ':transcript' => $transcript,
      ':raw_notes' => trim((string)($_POST['raw_notes'] ?? '')) ?: null,
      ':project_id' => $result['project_id'],
      ':assigned_user_id' => $result['assigned_user_id'],
      ':detected_intent' => $result['detected_intent'],
      ':urgency' => $result['urgency'],
      ':confidence' => $result['confidence'],
      ':ai_summary' => $result['ai_summary'],
      ':codex_prompt' => $result['codex_prompt'],
      ':client_reply_draft' => $result['client_reply_draft'],
      ':created_by_user_id' => (int)$user['id'],
    ]);
    redirect(url_for('/intake'));
}

if ($path === '/intake/classify' && $method === 'POST') {
    Security::requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM intake_items WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
    if ($item) {
        $result = Classifier::classify($conn, (string)$item['title'], (string)$item['transcript']);
        $stmt = $conn->prepare("
          UPDATE intake_items
          SET project_id = :project_id, assigned_user_id = COALESCE(assigned_user_id, :assigned_user_id),
              detected_intent = :detected_intent, urgency = :urgency, confidence = :confidence,
              status = 'clasificado', ai_summary = :ai_summary, codex_prompt = :codex_prompt,
              client_reply_draft = :client_reply_draft
          WHERE id = :id LIMIT 1
        ");
        $stmt->execute([
          ':project_id' => $result['project_id'],
          ':assigned_user_id' => $result['assigned_user_id'],
          ':detected_intent' => $result['detected_intent'],
          ':urgency' => $result['urgency'],
          ':confidence' => $result['confidence'],
          ':ai_summary' => $result['ai_summary'],
          ':codex_prompt' => $result['codex_prompt'],
          ':client_reply_draft' => $result['client_reply_draft'],
          ':id' => $id,
        ]);
    }
    redirect(url_for('/intake'));
}

if ($path === '/intake/create-ticket' && $method === 'POST') {
    Security::requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash_set('error', 'Intake invalido.');
        redirect(url_for('/intake'));
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM intake_items WHERE id = :id LIMIT 1 FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
        if (!$item) {
            $conn->rollBack();
            flash_set('error', 'No se encontro el intake solicitado.');
            redirect(url_for('/intake'));
        }

        $stmt = $conn->prepare("SELECT id, code FROM tickets WHERE intake_id = :intake_id ORDER BY id ASC LIMIT 1");
        $stmt->execute([':intake_id' => $id]);
        $existing = $stmt->fetch();
        if ($existing) {
            $stmt = $conn->prepare("UPDATE intake_items SET status = 'ticket_creado' WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $conn->commit();
            flash_set('info', 'Este intake ya tiene el ticket ' . (string)$existing['code'] . '.');
            redirect(url_for('/tickets/view') . '&id=' . (int)$existing['id']);
        }

        $status = !empty($item['assigned_user_id']) ? 'asignado' : 'nuevo';
        $description = "Resumen IA:\n" . (string)$item['ai_summary'] . "\n\nPrompt Codex:\n" . (string)$item['codex_prompt'] . "\n\nTranscripcion:\n" . (string)$item['transcript'];
        $stmt = $conn->prepare("
          INSERT INTO tickets
            (intake_id, project_id, assigned_user_id, title, description, client_name, client_contact,
             source_channel, intent, urgency, status, client_reply_draft, created_by_user_id)
          VALUES
            (:intake_id, :project_id, :assigned_user_id, :title, :description, :client_name, :client_contact,
             :source_channel, :intent, :urgency, :status, :client_reply_draft, :created_by)
        ");
        $stmt->execute([
          ':intake_id' => $id,
          ':project_id' => $item['project_id'],
          ':assigned_user_id' => $item['assigned_user_id'],
          ':title' => $item['title'],
          ':description' => $description,
          ':client_name' => $item['client_name'],
          ':client_contact' => $item['client_contact'],
          ':source_channel' => $item['source_channel'],
          ':intent' => $item['detected_intent'],
          ':urgency' => $item['urgency'],
          ':status' => $status,
          ':client_reply_draft' => $item['client_reply_draft'],
          ':created_by' => (int)$user['id'],
        ]);
        $ticketId = (int)$conn->lastInsertId();
        generate_ticket_code($conn, $ticketId);
        add_event($conn, $ticketId, (int)$user['id'], 'ticket_created', 'Ticket creado desde intake.');

        $stmt = $conn->prepare("UPDATE intake_items SET status = 'ticket_creado' WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        flash_set('error', 'No se pudo crear el ticket.');
        redirect(url_for('/intake'));
    }
    redirect(url_for('/tickets/view') . '&id=' . $ticketId);
}

if ($path === '/tickets' && $method === 'GET') {
    $stmt = $conn->query("
      SELECT t.*, u.name AS assignee_name, p.name AS project_name
      FROM tickets t
      LEFT JOIN users u ON u.id = t.assigned_user_id
      LEFT JOIN projects p ON p.id = t.project_id
      ORDER BY FIELD(t.status,'en_revision','asignado','nuevo','en_propuesta','aprobado','en_progreso','resuelto','cerrado','descartado'), t.created_at DESC
      LIMIT 120
    ");
    $tickets = $stmt->fetchAll();
    layout('Tickets', $user, function () use ($tickets) {
        ?>
  <div class="panel p-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-bold">Tickets</div><a class="btn btn-primary btn-sm" href="<?= h(url_for('/intake')) ?>">Nuevo desde WhatsApp</a></div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Codigo</th><th>Titulo</th><th>Cliente</th><th>Asignado</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td><?= h((string)$ticket['code']) ?></td>
            <td><?= h((string)$ticket['title']) ?><div class="small text-muted"><?= h((string)($ticket['project_name'] ?? '')) ?></div></td>
            <td><?= h((string)($ticket['client_name'] ?? '')) ?></td>
            <td><?= h((string)($ticket['assignee_name'] ?? 'Sin asignar')) ?></td>
            <td><span class="badge text-bg-secondary"><?= h((string)$ticket['status']) ?></span></td>
            <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="<?= h(url_for('/tickets/view')) ?>&id=<?= (int)$ticket['id'] ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
    });
    exit;
}

if ($path === '/tickets/view' && $method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("
      SELECT t.*, u.name AS assignee_name, p.name AS project_name
      FROM tickets t
      LEFT JOIN users u ON u.id = t.assigned_user_id
      LEFT JOIN projects p ON p.id = t.project_id
      WHERE t.id = :id
      LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        http_response_code(404);
        exit('Ticket no encontrado');
    }

    $stmt = $conn->prepare("SELECT tp.*, u.name AS worker_name FROM ticket_proposals tp INNER JOIN users u ON u.id = tp.worker_user_id WHERE tp.ticket_id = :id ORDER BY tp.created_at DESC");
    $stmt->execute([':id' => $id]);
    $proposals = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT te.*, u.name AS user_name FROM ticket_events te LEFT JOIN users u ON u.id = te.user_id WHERE te.ticket_id = :id ORDER BY te.created_at DESC");
    $stmt->execute([':id' => $id]);
    $events = $stmt->fetchAll();

    $people = users($conn);
    layout((string)$ticket['code'], $user, function (string $csrf) use ($ticket, $proposals, $events, $people) {
        ?>
  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="panel p-3 mb-3">
        <div class="d-flex justify-content-between gap-2">
          <div><div class="h5 fw-bold mb-1"><?= h((string)$ticket['code']) ?> · <?= h((string)$ticket['title']) ?></div><div class="text-muted small"><?= h((string)$ticket['client_name']) ?> · <?= h((string)$ticket['client_contact']) ?> · <?= h((string)$ticket['source_channel']) ?></div></div>
          <div><span class="badge text-bg-secondary"><?= h((string)$ticket['status']) ?></span> <span class="badge text-bg-warning"><?= h((string)$ticket['urgency']) ?></span></div>
        </div>
        <pre class="mono bg-light rounded p-3 mt-3"><?= h((string)$ticket['description']) ?></pre>
      </div>

      <div class="panel p-3">
        <div class="fw-bold mb-2">Propuestas</div>
        <?php foreach ($proposals as $proposal): ?>
          <div class="border rounded-3 p-3 mb-2">
            <div class="d-flex justify-content-between"><strong><?= h((string)$proposal['worker_name']) ?> · <?= h((string)$proposal['model_name']) ?></strong><span class="badge text-bg-info"><?= h((string)$proposal['status']) ?></span></div>
            <pre class="mono bg-light rounded p-2 mt-2"><?= h((string)$proposal['body']) ?></pre>
            <?php if (!empty($proposal['client_reply_draft'])): ?><div class="small"><strong>Cliente:</strong> <?= h((string)$proposal['client_reply_draft']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$proposals): ?><div class="text-muted">Sin propuestas todavia. Ejecuta el agente local.</div><?php endif; ?>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="panel p-3 mb-3">
        <div class="fw-bold mb-2">Control humano</div>
        <form class="mb-2" method="post" action="<?= h(url_for('/tickets/assign')) ?>">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
          <label class="form-label small">Asignar</label>
          <select class="form-select soft mb-2" name="assigned_user_id">
            <?php foreach ($people as $person): ?><option value="<?= (int)$person['id'] ?>" <?= (int)$ticket['assigned_user_id'] === (int)$person['id'] ? 'selected' : '' ?>><?= h((string)$person['name']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-outline-primary btn-sm">Guardar asignacion</button>
        </form>
        <form method="post" action="<?= h(url_for('/tickets/status')) ?>">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
          <label class="form-label small">Estado</label>
          <select class="form-select soft mb-2" name="status">
            <?php foreach (['nuevo','asignado','en_propuesta','en_revision','aprobado','en_progreso','resuelto','cerrado','descartado'] as $status): ?><option value="<?= h($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-outline-dark btn-sm">Actualizar estado</button>
        </form>
      </div>
      <div class="panel p-3">
        <div class="fw-bold mb-2">Eventos</div>
        <?php foreach ($events as $event): ?><div class="small border-bottom py-2"><strong><?= h((string)$event['event_type']) ?></strong><br><?= h((string)$event['body']) ?><br><span class="text-muted"><?= h((string)$event['created_at']) ?> · <?= h((string)($event['user_name'] ?? 'sistema')) ?></span></div><?php endforeach; ?>
      </div>
    </div>
  </div>
<?php
    });
    exit;
}

if ($path === '/tickets/assign' && $method === 'POST') {
    Security::requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $assignedUserId = (int)($_POST['assigned_user_id'] ?? 0);
    $stmt = $conn->prepare("
      SELECT t.status, t.assigned_user_id, u.name AS assigned_name
      FROM tickets t
      LEFT JOIN users u ON u.id = t.assigned_user_id
      WHERE t.id = :id
      LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $ticket = $stmt->fetch();
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute([':id' => $assignedUserId]);
    $target = $stmt->fetch();
    if (!$ticket || !$target) {
        flash_set('error', 'Ticket o usuario de asignacion invalido.');
        redirect(url_for('/tickets/view') . '&id=' . $id);
    }

    $oldStatus = (string)$ticket['status'];
    $newStatus = $oldStatus === 'nuevo' ? 'asignado' : $oldStatus;
    $stmt = $conn->prepare("UPDATE tickets SET assigned_user_id = :assigned_user_id, status = :status WHERE id = :id LIMIT 1");
    $stmt->execute([':assigned_user_id' => (int)$target['id'], ':status' => $newStatus, ':id' => $id]);
    $from = trim((string)($ticket['assigned_name'] ?? '')) ?: 'Sin asignar';
    add_event(
        $conn,
        $id,
        (int)$user['id'],
        'assigned',
        'Asignacion actualizada de ' . $from . ' a ' . (string)$target['name'] . '. Estado: ' . $newStatus . '.'
    );
    flash_set('success', 'Asignacion actualizada sin crear otro ticket.');
    redirect(url_for('/tickets/view') . '&id=' . $id);
}

if ($path === '/tickets/status' && $method === 'POST') {
    Security::requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'nuevo');
    $valid = ['nuevo','asignado','en_propuesta','en_revision','aprobado','en_progreso','resuelto','cerrado','descartado'];
    if (!in_array($status, $valid, true)) {
        flash_set('error', 'Estado invalido.');
        redirect(url_for('/tickets/view') . '&id=' . $id);
    }
    $stmt = $conn->prepare("SELECT status FROM tickets WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $oldStatus = $stmt->fetchColumn();
    if (!is_string($oldStatus)) {
        flash_set('error', 'No se encontro el ticket solicitado.');
        redirect(url_for('/tickets'));
    }
    $stmt = $conn->prepare("
      UPDATE tickets
      SET status = :status,
          closed_at = CASE WHEN :status2 IN ('cerrado','descartado') THEN COALESCE(closed_at, NOW()) ELSE NULL END
      WHERE id = :id
      LIMIT 1
    ");
    $stmt->execute([':status' => $status, ':status2' => $status, ':id' => $id]);
    add_event($conn, $id, (int)$user['id'], 'status_changed', 'Estado actualizado de ' . $oldStatus . ' a ' . $status . '.');
    flash_set('success', 'Estado actualizado sin crear otro ticket.');
    redirect(url_for('/tickets/view') . '&id=' . $id);
}

if ($path === '/projects' && $method === 'GET') {
    $items = projects($conn);
    $editId = max(0, (int)($_GET['edit_id'] ?? 0));
    $editing = project_by_id($conn, $editId);
    if ($editId > 0 && $editing === null) {
        flash_set('error', 'No se encontro el proyecto solicitado.');
        redirect(url_for('/projects'));
    }
    layout('Proyectos', $user, function (string $csrf) use ($items, $editing) {
        ?>
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="panel p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-bold"><?= $editing ? 'Editar proyecto' : 'Nuevo proyecto' ?></div>
          <?php if ($editing): ?><a class="btn btn-outline-secondary btn-sm" href="<?= h(url_for('/projects')) ?>">Cancelar</a><?php endif; ?>
        </div>
        <form method="post" action="<?= h(url_for('/projects/save')) ?>">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
          <input class="form-control soft mb-2" name="name" required placeholder="Nombre" value="<?= h((string)($editing['name'] ?? '')) ?>">
          <input class="form-control soft mb-2" name="project_key" placeholder="clave-proyecto" value="<?= h((string)($editing['project_key'] ?? '')) ?>">
          <input class="form-control soft mb-2" name="client_name" placeholder="Cliente" value="<?= h((string)($editing['client_name'] ?? '')) ?>">
          <textarea class="form-control soft mb-2" name="aliases" rows="2" placeholder="Alias, uno por linea"><?= h((string)($editing['aliases'] ?? '')) ?></textarea>
          <textarea class="form-control soft mb-2" name="client_phones" rows="2" placeholder="Telefonos del cliente, uno por linea"><?= h((string)($editing['client_phones'] ?? '')) ?></textarea>
          <input class="form-control soft mb-2" name="local_path_ivan" placeholder="Ruta local Ivan" value="<?= h((string)($editing['local_path_ivan'] ?? '')) ?>">
          <input class="form-control soft mb-2" name="local_path_oscar" placeholder="Ruta local Oscar" value="<?= h((string)($editing['local_path_oscar'] ?? '')) ?>">
          <input class="form-control soft mb-2" name="server_ssh_ivan" placeholder="SSH Ivan: alias o usuario@host" value="<?= h((string)($editing['server_ssh_ivan'] ?? '')) ?>">
          <input class="form-control soft mb-2" name="server_ssh_oscar" placeholder="SSH Oscar: alias o usuario@host" value="<?= h((string)($editing['server_ssh_oscar'] ?? '')) ?>">
          <input class="form-control soft mb-2" name="repo_url" placeholder="Repo Git" value="<?= h((string)($editing['repo_url'] ?? '')) ?>">
          <textarea class="form-control soft mb-2" name="codex_rules" rows="3" placeholder="Reglas Codex"><?= h((string)($editing['codex_rules'] ?? '')) ?></textarea>
          <textarea class="form-control soft mb-2 font-monospace" name="operational_context" rows="5" placeholder="Contexto operativo JSON"><?= h((string)($editing['operational_context'] ?? '')) ?></textarea>
          <select class="form-select soft mb-2" name="status">
            <?php foreach (['active', 'paused', 'archived'] as $status): ?><option value="<?= h($status) ?>" <?= ($editing['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-primary"><?= $editing ? 'Actualizar' : 'Guardar' ?></button>
        </form>
      </div>
    </div>
    <div class="col-12 col-lg-7">
      <div class="panel p-3">
        <div class="fw-bold mb-2">Catalogo</div>
        <?php foreach ($items as $project): ?>
          <div class="border rounded-3 p-3 mb-2">
            <div class="d-flex justify-content-between gap-2">
              <div><strong><?= h((string)$project['name']) ?></strong><div class="small text-muted"><?= h((string)$project['project_key']) ?> · <?= h((string)($project['client_name'] ?? '')) ?> · <?= h((string)$project['status']) ?></div></div>
              <a class="btn btn-outline-secondary btn-sm align-self-start" href="<?= h(url_for('/projects')) ?>&edit_id=<?= (int)$project['id'] ?>">Editar</a>
            </div>
            <?php if (!empty($project['aliases'])): ?><div class="small">Alias: <?= h(str_replace("\n", ', ', (string)$project['aliases'])) ?></div><?php endif; ?>
            <?php if (!empty($project['client_phones'])): ?><div class="small">Telefonos: <?= h(str_replace("\n", ', ', (string)$project['client_phones'])) ?></div><?php endif; ?>
            <div class="small">Ivan: <?= h((string)($project['local_path_ivan'] ?? '-')) ?></div>
            <div class="small">Oscar: <?= h((string)($project['local_path_oscar'] ?? '-')) ?></div>
            <div class="small">SSH Ivan: <?= h((string)($project['server_ssh_ivan'] ?? '-')) ?></div>
            <div class="small">SSH Oscar: <?= h((string)($project['server_ssh_oscar'] ?? '-')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php
    });
    exit;
}

if ($path === '/projects/save' && $method === 'POST') {
    Security::requireCsrf();
    $id = max(0, (int)($_POST['id'] ?? 0));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        flash_set('error', 'El nombre del proyecto es obligatorio.');
        redirect(url_for('/projects'));
    }
    $key = strtolower(trim((string)($_POST['project_key'] ?? '')));
    if ($key === '') {
        $key = trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $name)), '-');
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $key)) {
        flash_set('error', 'La clave debe usar minusculas, numeros y guiones.');
        redirect(url_for('/projects') . ($id > 0 ? '&edit_id=' . $id : ''));
    }
    if (project_key_exists($conn, $key, $id > 0 ? $id : null)) {
        flash_set('error', 'Ya existe otro proyecto con esa clave.');
        redirect(url_for('/projects') . ($id > 0 ? '&edit_id=' . $id : ''));
    }
    $status = (string)($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'paused', 'archived'], true)) {
        $status = 'active';
    }
    $params = [
      ':project_key' => $key,
      ':name' => $name,
      ':client_name' => trim((string)($_POST['client_name'] ?? '')) ?: null,
      ':aliases' => trim((string)($_POST['aliases'] ?? '')) ?: null,
      ':client_phones' => trim((string)($_POST['client_phones'] ?? '')) ?: null,
      ':local_path_ivan' => trim((string)($_POST['local_path_ivan'] ?? '')) ?: null,
      ':local_path_oscar' => trim((string)($_POST['local_path_oscar'] ?? '')) ?: null,
      ':server_ssh_ivan' => trim((string)($_POST['server_ssh_ivan'] ?? '')) ?: null,
      ':server_ssh_oscar' => trim((string)($_POST['server_ssh_oscar'] ?? '')) ?: null,
      ':repo_url' => trim((string)($_POST['repo_url'] ?? '')) ?: null,
      ':codex_rules' => trim((string)($_POST['codex_rules'] ?? '')) ?: null,
      ':operational_context' => trim((string)($_POST['operational_context'] ?? '')) ?: null,
      ':status' => $status,
    ];
    if ($id > 0) {
        if (project_by_id($conn, $id) === null) {
            flash_set('error', 'No se encontro el proyecto solicitado.');
            redirect(url_for('/projects'));
        }
        $stmt = $conn->prepare("
          UPDATE projects SET
            project_key = :project_key, name = :name, client_name = :client_name,
            aliases = :aliases, client_phones = :client_phones,
            local_path_ivan = :local_path_ivan, local_path_oscar = :local_path_oscar,
            server_ssh_ivan = :server_ssh_ivan, server_ssh_oscar = :server_ssh_oscar,
            repo_url = :repo_url, codex_rules = :codex_rules,
            operational_context = :operational_context, status = :status
          WHERE id = :id LIMIT 1
        ");
        $params[':id'] = $id;
        $stmt->execute($params);
        flash_set('success', 'Proyecto actualizado correctamente.');
    } else {
        $stmt = $conn->prepare("
          INSERT INTO projects
            (project_key, name, client_name, aliases, client_phones, local_path_ivan, local_path_oscar,
             server_ssh_ivan, server_ssh_oscar, repo_url, codex_rules, operational_context, status)
          VALUES
            (:project_key, :name, :client_name, :aliases, :client_phones, :local_path_ivan, :local_path_oscar,
             :server_ssh_ivan, :server_ssh_oscar, :repo_url, :codex_rules, :operational_context, :status)
        ");
        $stmt->execute($params);
        flash_set('success', 'Proyecto creado correctamente.');
    }
    redirect(url_for('/projects'));
}

http_response_code(404);
echo 'Not found';
