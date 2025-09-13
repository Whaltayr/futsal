<?php
// /futsal-pj/new-api/next_games.php
require_once __DIR__ . '/connection.php'; // IMPORTANT: not '/new-api/connection.php'
header('Content-Type: application/json; charset=utf-8');

try {
  $m = get_mysqli();
  $m->set_charset('utf8mb4');

  $group = isset($_GET['group']) ? $_GET['group'] : 'tournament'; // 'tournament'|'A'|'B'

  // Choose the latest tournament by start_date (change if you prefer a fixed tournament)
  $t = $m->query("SELECT id, name FROM tournaments ORDER BY start_date DESC, id DESC LIMIT 1")->fetch_assoc();
  if (!$t) { echo json_encode([]); exit; }
  $tournament_id = (int)$t['id'];

  $sql = "
    SELECT
      m.id,
      DATE_FORMAT(m.match_date, '%Y-%m-%d') AS date,
      DATE_FORMAT(m.match_date, '%H:%i') AS time,
      th.name AS home_name,
      ta.name AS away_name,
      p.name  AS phase_name,
      t.name  AS tournament_name
    FROM matches m
    JOIN phases p ON p.id = m.phase_id
    JOIN tournaments t ON t.id = p.tournament_id
    JOIN teams th ON th.id = m.team_home_id
    JOIN teams ta ON ta.id = m.team_away_id
    WHERE t.id = ?
      AND m.status = 'agendado'
      AND m.match_date IS NOT NULL
      AND m.match_date >= NOW()
      " . ($group === 'tournament' ? "" : "AND p.name = CONCAT('Grupo ', ?)") . "
    ORDER BY m.match_date ASC, m.id ASC
    LIMIT 4
  ";

  $st = $m->prepare($sql);
  if ($group === 'tournament') {
    $st->bind_param('i', $tournament_id);
  } else {
    $st->bind_param('is', $tournament_id, $group);
  }
  $st->execute();
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();

  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}