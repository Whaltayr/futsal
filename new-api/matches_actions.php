<?php
// new-api/matches_actions.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth_check.php'; // assumes it exits on unauthenticated

$mysqli = get_mysqli();
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'erro' => 'DB connection failed', 'detalhe' => $mysqli->connect_error]);
  exit;
}

function jfail(int $code, string $msg, string $detail = ''): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'erro' => $msg, 'detalhe' => $detail]);
  exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!csrf_validate($csrf)) {
  jfail(403, 'CSRF inválido');
}

$action = $_POST['action'] ?? '';
if (!$action) jfail(400, 'Ação ausente');

function intOrNull($v) {
  if ($v === '' || $v === null) return null;
  if (is_numeric($v)) return (int)$v;
  return null;
}

function clean_str(?string $s): string {
  return trim((string)$s);
}

// Validate common fields for create/update
function read_match_input(): array {
  $phase_id = intOrNull($_POST['phase_id'] ?? null);
  $team_home_id = intOrNull($_POST['team_home_id'] ?? null);
  $team_away_id = intOrNull($_POST['team_away_id'] ?? null);
  $match_date = clean_str($_POST['match_date'] ?? '');
  $round = clean_str($_POST['round'] ?? '');
  // Scores default to 0 if blank
  $home_score = intOrNull($_POST['home_score'] ?? null);
  if ($home_score === null) $home_score = 0;
  $away_score = intOrNull($_POST['away_score'] ?? null);
  if ($away_score === null) $away_score = 0;
  $status = clean_str($_POST['status'] ?? 'agendado');

  if (!$phase_id) jfail(400, 'phase_id obrigatório');
  if (!$team_home_id) jfail(400, 'team_home_id obrigatório');
  if (!$team_away_id) jfail(400, 'team_away_id obrigatório');
  if ($team_home_id === $team_away_id) jfail(400, 'Times não podem ser iguais');
  if ($match_date === '') jfail(400, 'match_date obrigatório');

  // Normalize datetime-local: ensure "YYYY-mm-dd HH:ii:ss"
  $dt = str_replace('T', ' ', $match_date);
  if (strlen($dt) === 16) $dt .= ':00';

  // Validate status
  $allowed = ['agendado','decorrendo','finalizado'];
  if (!in_array($status, $allowed, true)) $status = 'agendado';

  return [
    'phase_id' => $phase_id,
    'team_home_id' => $team_home_id,
    'team_away_id' => $team_away_id,
    'match_date' => $dt,
    'round' => $round,
    'home_score' => $home_score,
    'away_score' => $away_score,
    'status' => $status,
  ];
}

try {
  if ($action === 'create') {
    $data = read_match_input();

    $sql = "INSERT INTO matches
              (phase_id, team_home_id, team_away_id, match_date, round, home_score, away_score, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) jfail(500, 'Falha ao preparar INSERT', $mysqli->error);

    $ok = $stmt->bind_param(
      'iiississ',
      $data['phase_id'],
      $data['team_home_id'],
      $data['team_away_id'],
      $data['match_date'],
      $data['round'],
      $data['home_score'],
      $data['away_score'],
      $data['status']
    );
    if (!$ok) jfail(500, 'Falha bind INSERT', $stmt->error);
    if (!$stmt->execute()) jfail(500, 'Falha execute INSERT', $stmt->error);

    echo json_encode(['ok' => true, 'id' => $stmt->insert_id]);
    exit;
  }

  if ($action === 'update') {
    $id = intOrNull($_POST['id'] ?? null);
    if (!$id) jfail(400, 'ID obrigatório');
    $data = read_match_input();

    $sql = "UPDATE matches
              SET phase_id=?, team_home_id=?, team_away_id=?, match_date=?, round=?, home_score=?, away_score=?, status=?
            WHERE id=?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) jfail(500, 'Falha ao preparar UPDATE', $mysqli->error);

    $ok = $stmt->bind_param(
      'iiississi',
      $data['phase_id'],
      $data['team_home_id'],
      $data['team_away_id'],
      $data['match_date'],
      $data['round'],
      $data['home_score'],
      $data['away_score'],
      $data['status'],
      $id
    );
    if (!$ok) jfail(500, 'Falha bind UPDATE', $stmt->error);
    if (!$stmt->execute()) jfail(500, 'Falha execute UPDATE', $stmt->error);

    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
  }

  if ($action === 'delete') {
    $id = intOrNull($_POST['id'] ?? null);
    if (!$id) jfail(400, 'ID obrigatório');

    $stmt = $mysqli->prepare("DELETE FROM matches WHERE id=?");
    if (!$stmt) jfail(500, 'Falha ao preparar DELETE', $mysqli->error);
    $ok = $stmt->bind_param('i', $id);
    if (!$ok) jfail(500, 'Falha bind DELETE', $stmt->error);
    if (!$stmt->execute()) jfail(500, 'Falha execute DELETE', $stmt->error);

    echo json_encode(['ok' => true]);
    exit;
  }

  jfail(400, 'Ação inválida');

} catch (Throwable $e) {
  jfail(500, 'Exceção', $e->getMessage());
}

?>