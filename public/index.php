<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/Security.php';
require_once __DIR__ . '/../app/Core/Classifier.php';

$auth = new Auth($conn);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function layout(string $title, array $user, callable $body): void
{
    $csrf = Security::csrfToken();
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | ArgotesIA Ops</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root{--ink:#111827;--muted:#6b7280;--line:#d9dee8;--bg:#f6f8fb}
    body{font-family:Verdana,Arial,sans-serif;background:var(--bg);color:var(--ink)}
    .wrap{max-width:1280px}
    .panel{background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 10px 24px rgba(17,24,39,.05)}
    .metric{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px}
    .metric strong{font-size:1.5rem}
    .soft{border-radius:8px;border:1px solid var(--line);padding:.7rem .75rem}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.86rem;white-space:pre-wrap}
    .nav-pills .nav-link{border-radius:8px}
    textarea.mono{min-height:180px}
  </style>
</head>
<body>
<div class="container wrap py-3 py-md-4">
  <header class="panel p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">
    <div class="fw-bold h5 mb-0 me-2">ArgotesIA Ops</div>
    <nav class="nav nav-pills gap-1">
      <a class="nav-link" href="/"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
      <a class="nav-link" href="/intake"><i class="bi bi-whatsapp me-1"></i>Intake</a>
      <a class="nav-link" href="/tickets"><i class="bi bi-ticket-perforated me-1"></i>Tickets</a>
      <a class="nav-link" href="/projects"><i class="bi bi-folder2-open me-1"></i>Proyectos</a>
    </nav>
    <div class="ms-auto small text-muted">
      <?= h((string)$user['name']) ?> · <?= h((string)$user['worker_key']) ?>
      <a class="btn btn-outline-danger btn-sm ms-2" href="/logout">Salir</a>
    </div>
  </header>
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
</script>
</body>
</html>
<?php
}

function users(PDO $conn): array
{
    return $conn->query("SELECT id, name, worker_key FROM users WHERE status = 'active' ORDER BY id ASC")->fetchAll();
}

function projects(PDO $conn): array
{
    return $conn->query("SELECT * FROM projects WHERE status = 'active' ORDER BY name ASC")->fetchAll();
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
        p.name AS project_name, p.local_path_ivan, p.local_path_oscar, p.server_ssh, p.repo_url, p.codex_rules
      FROM tickets t
      LEFT JOIN projects p ON p.id = t.project_id
      WHERE t.assigned_user_id = :uid
        AND t.status IN ('nuevo','asignado','en_propuesta')
      ORDER BY FIELD(t.urgency,'alta','media','baja'), t.created_at ASC
      LIMIT 10
    ");
    $stmt->execute([':uid' => (int)$worker['id']]);
    json_response(['ok' => true, 'worker' => $worker['worker_key'], 'tickets' => $stmt->fetchAll()]);
}

if ($path === '/api/worker/proposals' && $method === 'POST') {
    $worker = worker_from_request($conn);
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'invalid json'], 400);
    }

    $ticketId = (int)($input['ticket_id'] ?? 0);
    $body = trim((string)($input['body'] ?? ''));
    $modelName = trim((string)($input['model_name'] ?? 'local-model'));
    $clientReply = trim((string)($input['client_reply_draft'] ?? ''));
    if ($ticketId <= 0 || $body === '') {
        json_response(['ok' => false, 'error' => 'ticket_id/body required'], 400);
    }

    $stmt = $conn->prepare("SELECT id FROM tickets WHERE id = :id AND assigned_user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $ticketId, ':uid' => (int)$worker['id']]);
    if (!$stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'ticket not assigned to worker'], 403);
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("
          INSERT INTO ticket_proposals (ticket_id, worker_user_id, source, model_name, status, body, client_reply_draft)
          VALUES (:ticket_id, :worker_user_id, 'local_model', :model_name, 'ready', :body, :client_reply)
        ");
        $stmt->execute([
          ':ticket_id' => $ticketId,
          ':worker_user_id' => (int)$worker['id'],
          ':model_name' => $modelName !== '' ? $modelName : null,
          ':body' => $body,
          ':client_reply' => $clientReply !== '' ? $clientReply : null,
        ]);
        $proposalId = (int)$conn->lastInsertId();

        $stmt = $conn->prepare("UPDATE tickets SET status = 'en_revision', client_reply_draft = COALESCE(:reply, client_reply_draft) WHERE id = :id LIMIT 1");
        $stmt->execute([':reply' => $clientReply !== '' ? $clientReply : null, ':id' => $ticketId]);

        add_event($conn, $ticketId, (int)$worker['id'], 'proposal_ready', 'Propuesta local lista para revision.');

        $stmt = $conn->prepare("INSERT INTO worker_runs (worker_user_id, ticket_id, status, message) VALUES (:worker_id, :ticket_id, 'completed', :message)");
        $stmt->execute([':worker_id' => (int)$worker['id'], ':ticket_id' => $ticketId, ':message' => 'Proposal #' . $proposalId . ' uploaded']);

        $conn->commit();
        json_response(['ok' => true, 'proposal_id' => $proposalId]);
    } catch (Throwable $e) {
        $conn->rollBack();
        json_response(['ok' => false, 'error' => 'proposal save failed'], 500);
    }
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#f6f8fb}.card{max-width:420px;margin:9vh auto;border-radius:10px}</style>
</head>
<body>
  <div class="card shadow-sm border-0 p-4">
    <div class="h4 fw-bold mb-1">ArgotesIA Ops</div>
    <div class="text-muted mb-3">Operacion interna Ivan/Oscar</div>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="/login">
      <input type="hidden" name="_csrf" value="<?= h(Security::csrfToken()) ?>">
      <label class="form-label">Email</label>
      <input class="form-control mb-2" type="email" name="email" required value="ivan@argotes.com">
      <label class="form-label">Password</label>
      <input class="form-control mb-3" type="password" name="password" required value="123456">
      <button class="btn btn-primary w-100" type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
<?php
    exit;
}

if ($path === '/login' && $method === 'POST') {
    Security::requireCsrf();
    if ($auth->attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
        redirect((string)($_GET['return'] ?? '/'));
    }
    redirect('/login?error=' . urlencode('Credenciales invalidas.'));
}

if ($path === '/logout') {
    $auth->logout();
    redirect('/login');
}

$user = $auth->requireAuth();

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
      <a class="btn btn-primary btn-sm" href="/intake">Capturar WhatsApp</a>
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
            <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="/tickets/view?id=<?= (int)$ticket['id'] ?>">Abrir</a></td>
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
      SELECT i.*, p.name AS project_name, u.name AS assigned_name
      FROM intake_items i
      LEFT JOIN projects p ON p.id = i.project_id
      LEFT JOIN users u ON u.id = i.assigned_user_id
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
        <form method="post" action="/intake/create">
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
              <form method="post" action="/intake/classify"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-outline-primary btn-sm">Clasificar</button></form>
              <form method="post" action="/intake/create-ticket"><input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-success btn-sm" <?= $item['status'] === 'ticket_creado' ? 'disabled' : '' ?>>Crear ticket</button></form>
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
        redirect('/intake');
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
    redirect('/intake');
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
    redirect('/intake');
}

if ($path === '/intake/create-ticket' && $method === 'POST') {
    Security::requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM intake_items WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
    if (!$item) {
        redirect('/intake');
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
    redirect('/tickets/view?id=' . $ticketId);
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
    <div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-bold">Tickets</div><a class="btn btn-primary btn-sm" href="/intake">Nuevo desde WhatsApp</a></div>
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
            <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="/tickets/view?id=<?= (int)$ticket['id'] ?>">Abrir</a></td>
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
        <form class="mb-2" method="post" action="/tickets/assign">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
          <label class="form-label small">Asignar</label>
          <select class="form-select soft mb-2" name="assigned_user_id">
            <?php foreach ($people as $person): ?><option value="<?= (int)$person['id'] ?>" <?= (int)$ticket['assigned_user_id'] === (int)$person['id'] ? 'selected' : '' ?>><?= h((string)$person['name']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-outline-primary btn-sm">Guardar asignacion</button>
        </form>
        <form method="post" action="/tickets/status">
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
    $stmt = $conn->prepare("UPDATE tickets SET assigned_user_id = :assigned_user_id, status = 'asignado' WHERE id = :id LIMIT 1");
    $stmt->execute([':assigned_user_id' => $assignedUserId ?: null, ':id' => $id]);
    add_event($conn, $id, (int)$user['id'], 'assigned', 'Ticket asignado.');
    redirect('/tickets/view?id=' . $id);
}

if ($path === '/tickets/status' && $method === 'POST') {
    Security::requireCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'nuevo');
    $valid = ['nuevo','asignado','en_propuesta','en_revision','aprobado','en_progreso','resuelto','cerrado','descartado'];
    if (!in_array($status, $valid, true)) {
        $status = 'nuevo';
    }
    $stmt = $conn->prepare("UPDATE tickets SET status = :status, closed_at = IF(:status2 IN ('cerrado','descartado'), NOW(), closed_at) WHERE id = :id LIMIT 1");
    $stmt->execute([':status' => $status, ':status2' => $status, ':id' => $id]);
    add_event($conn, $id, (int)$user['id'], 'status_changed', 'Estado actualizado a ' . $status . '.');
    redirect('/tickets/view?id=' . $id);
}

if ($path === '/projects' && $method === 'GET') {
    $items = projects($conn);
    layout('Proyectos', $user, function (string $csrf) use ($items) {
        ?>
  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="panel p-3">
        <div class="fw-bold mb-2">Proyecto</div>
        <form method="post" action="/projects/save">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
          <input class="form-control soft mb-2" name="name" required placeholder="Nombre">
          <input class="form-control soft mb-2" name="project_key" placeholder="clave-proyecto">
          <input class="form-control soft mb-2" name="client_name" placeholder="Cliente">
          <input class="form-control soft mb-2" name="local_path_ivan" placeholder="Ruta local Ivan">
          <input class="form-control soft mb-2" name="local_path_oscar" placeholder="Ruta local Oscar">
          <input class="form-control soft mb-2" name="server_ssh" placeholder="SSH servidor">
          <input class="form-control soft mb-2" name="repo_url" placeholder="Repo Git">
          <textarea class="form-control soft mb-2" name="codex_rules" rows="3" placeholder="Reglas Codex"></textarea>
          <button class="btn btn-primary">Guardar</button>
        </form>
      </div>
    </div>
    <div class="col-12 col-lg-7">
      <div class="panel p-3">
        <div class="fw-bold mb-2">Activos</div>
        <?php foreach ($items as $project): ?>
          <div class="border rounded-3 p-3 mb-2">
            <strong><?= h((string)$project['name']) ?></strong><div class="small text-muted"><?= h((string)$project['project_key']) ?> · <?= h((string)($project['client_name'] ?? '')) ?></div>
            <div class="small">Ivan: <?= h((string)($project['local_path_ivan'] ?? '-')) ?></div>
            <div class="small">Oscar: <?= h((string)($project['local_path_oscar'] ?? '-')) ?></div>
            <div class="small">SSH: <?= h((string)($project['server_ssh'] ?? '-')) ?></div>
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
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        redirect('/projects');
    }
    $key = trim((string)($_POST['project_key'] ?? ''));
    if ($key === '') {
        $key = trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $name)), '-');
    }
    $stmt = $conn->prepare("
      INSERT INTO projects (project_key, name, client_name, local_path_ivan, local_path_oscar, server_ssh, repo_url, codex_rules)
      VALUES (:project_key, :name, :client_name, :local_path_ivan, :local_path_oscar, :server_ssh, :repo_url, :codex_rules)
      ON DUPLICATE KEY UPDATE
        name = VALUES(name), client_name = VALUES(client_name), local_path_ivan = VALUES(local_path_ivan),
        local_path_oscar = VALUES(local_path_oscar), server_ssh = VALUES(server_ssh), repo_url = VALUES(repo_url),
        codex_rules = VALUES(codex_rules), status = 'active'
    ");
    $stmt->execute([
      ':project_key' => $key,
      ':name' => $name,
      ':client_name' => trim((string)($_POST['client_name'] ?? '')) ?: null,
      ':local_path_ivan' => trim((string)($_POST['local_path_ivan'] ?? '')) ?: null,
      ':local_path_oscar' => trim((string)($_POST['local_path_oscar'] ?? '')) ?: null,
      ':server_ssh' => trim((string)($_POST['server_ssh'] ?? '')) ?: null,
      ':repo_url' => trim((string)($_POST['repo_url'] ?? '')) ?: null,
      ':codex_rules' => trim((string)($_POST['codex_rules'] ?? '')) ?: null,
    ]);
    redirect('/projects');
}

http_response_code(404);
echo 'Not found';

