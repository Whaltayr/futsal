<?php
// new-api/results_helper.php
function upsert_match_results(mysqli $db, int $match_id): void {
  $sql = "SELECT id, team_home_id, team_away_id, home_score, away_score, status
          FROM matches WHERE id = ?";
  $st = $db->prepare($sql);
  $st->bind_param('i', $match_id);
  $st->execute();
  $m = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$m) return;

  // If not final or scores missing, remove any results
  if ($m['status'] !== 'finalizado' || $m['home_score'] === null || $m['away_score'] === null) {
    $del = $db->prepare("DELETE FROM match_results WHERE match_id=?");
    $del->bind_param('i', $match_id);
    $del->execute();
    $del->close();
    return;
  }

  $hid = (int)$m['team_home_id']; $aid = (int)$m['team_away_id'];
  $hs  = (int)$m['home_score'];   $as  = (int)$m['away_score'];

  $homeOutcome = ($hs > $as) ? 'V' : (($hs === $as) ? 'E' : 'P');
  $awayOutcome = ($as > $hs) ? 'V' : (($as === $hs) ? 'E' : 'P');
  $homePoints  = ($hs > $as) ? 3 : (($hs === $as) ? 1 : 0);
  $awayPoints  = ($as > $hs) ? 3 : (($as === $hs) ? 1 : 0);

  $sqlIns = "INSERT INTO match_results
               (match_id, team_id, is_home, goals_for, goals_against, outcome, points)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               is_home=VALUES(is_home),
               goals_for=VALUES(goals_for),
               goals_against=VALUES(goals_against),
               outcome=VALUES(outcome),
               points=VALUES(points)";
  $ins = $db->prepare($sqlIns);

  // home row
  $is_home = 1; $gf = $hs; $ga = $as; $out = $homeOutcome; $pts = $homePoints;
  $ins->bind_param('iiiiisi', $match_id, $hid, $is_home, $gf, $ga, $out, $pts);
  $ins->execute();

  // away row
  $is_home = 0; $gf = $as; $ga = $hs; $out = $awayOutcome; $pts = $awayPoints;
  $ins->bind_param('iiiiisi', $match_id, $aid, $is_home, $gf, $ga, $out, $pts);
  $ins->execute();

  $ins->close();
}