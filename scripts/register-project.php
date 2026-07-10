<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$path = (string)($argv[1] ?? '');
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php scripts/register-project.php project.json\n");
    exit(1);
}

$raw = file_get_contents($path);
$project = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($project)) {
    fwrite(STDERR, "Project file must contain valid JSON.\n");
    exit(1);
}

reject_secret_fields($project);

$key = strtolower(trim((string)($project['project_key'] ?? '')));
$name = trim((string)($project['name'] ?? ''));
if ($key === '' || !preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $key) || $name === '') {
    fwrite(STDERR, "project_key and name are required; project_key must use lowercase letters, numbers, and hyphens.\n");
    exit(1);
}

$aliases = array_values(array_unique(array_filter(array_map(
    static fn(mixed $value): string => trim((string)$value),
    is_array($project['aliases'] ?? null) ? $project['aliases'] : []
))));
$phones = array_values(array_unique(array_filter(array_map(
    static fn(mixed $value): string => trim((string)$value),
    is_array($project['client_phones'] ?? null) ? $project['client_phones'] : []
))));
$rules = is_array($project['codex_rules'] ?? null)
    ? implode("\n", array_map(static fn(mixed $value): string => '- ' . trim((string)$value), $project['codex_rules']))
    : trim((string)($project['codex_rules'] ?? ''));
$context = json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($context)) {
    fwrite(STDERR, "Could not encode operational context.\n");
    exit(1);
}

$legacySsh = value_or_null($project['server_ssh'] ?? null);
$sshIvan = value_or_null($project['server_ssh_ivan'] ?? null);
$sshOscar = value_or_null($project['server_ssh_oscar'] ?? null);

$stmt = $conn->prepare("
  INSERT INTO projects
    (project_key, name, client_name, aliases, client_phones, local_path_ivan, local_path_oscar, server_ssh, server_ssh_ivan, server_ssh_oscar, repo_url, codex_rules, operational_context)
  VALUES
    (:project_key, :name, :client_name, :aliases, :client_phones, :local_path_ivan, :local_path_oscar, :server_ssh, :server_ssh_ivan, :server_ssh_oscar, :repo_url, :codex_rules, :operational_context)
  ON DUPLICATE KEY UPDATE
    name = VALUES(name), client_name = VALUES(client_name), aliases = VALUES(aliases), client_phones = VALUES(client_phones),
    local_path_ivan = VALUES(local_path_ivan), local_path_oscar = VALUES(local_path_oscar),
    server_ssh = COALESCE(VALUES(server_ssh), server_ssh), server_ssh_ivan = VALUES(server_ssh_ivan),
    server_ssh_oscar = VALUES(server_ssh_oscar), repo_url = VALUES(repo_url), codex_rules = VALUES(codex_rules),
    operational_context = VALUES(operational_context), status = 'active'
");
$stmt->execute([
    ':project_key' => $key,
    ':name' => $name,
    ':client_name' => value_or_null($project['client_name'] ?? null),
    ':aliases' => $aliases ? implode("\n", $aliases) : null,
    ':client_phones' => $phones ? implode("\n", $phones) : null,
    ':local_path_ivan' => value_or_null($project['local_path_ivan'] ?? null),
    ':local_path_oscar' => value_or_null($project['local_path_oscar'] ?? null),
    ':server_ssh' => $legacySsh,
    ':server_ssh_ivan' => $sshIvan,
    ':server_ssh_oscar' => $sshOscar,
    ':repo_url' => value_or_null($project['repo_url'] ?? null),
    ':codex_rules' => $rules !== '' ? $rules : null,
    ':operational_context' => $context,
]);

$stmt = $conn->prepare("SELECT id, project_key, name FROM projects WHERE project_key = :project_key LIMIT 1");
$stmt->execute([':project_key' => $key]);
$saved = $stmt->fetch();
echo json_encode([
    'ok' => true,
    'project' => $saved,
    'aliases_count' => count($aliases),
    'phones_count' => count($phones),
    'warnings' => $legacySsh !== null && $sshIvan === null && $sshOscar === null
        ? ['server_ssh is legacy and was not assigned; provide server_ssh_ivan/server_ssh_oscar']
        : [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

function value_or_null(mixed $value): ?string
{
    $value = trim((string)$value);
    return $value !== '' ? $value : null;
}

function reject_secret_fields(array $payload, string $prefix = ''): void
{
    foreach ($payload as $key => $value) {
        $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
        if (preg_match('/password|token|secret|credential|private.?key|keystore/i', (string)$key)) {
            fwrite(STDERR, "Secret-like field is not allowed: {$path}\n");
            exit(1);
        }
        if (is_array($value)) {
            reject_secret_fields($value, $path);
        }
    }
}
