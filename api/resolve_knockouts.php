<?php
/**
 * resolve_knockouts.php
 * Modo híbrido: invocado manualmente pelo admin para resolver fixtures da fase de knockout.
 *
 * Parâmetros:
 *  - admin_key (string)  => chave temporária; substitua por sessão/token
 *  - force=1 (opcional)  => força sobrescrever fixtures locked
 *  - tournament_id (opcional)
 *  - group_phase_id (opcional)
 *  - knock_phase_id (opcional)
 *
 * Saída: JSON em Português.
 */


// auth_check.php - incluir no topo dos endpoints admin
session_start();
require_once 'auth_check.php';


// --- modo dev (remover em produção) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- cabeçalho JSON ---
header('Content-Type: application/json; charset=utf-8');

// --- configuração DB (ajuste conforme o seu ambiente) ---
require_once __DIR__ . '/connection.php';

// autenticação simples (substituir por sessão real em produção)
$ADMIN_KEY = 'changeme_replace_with_real_auth';

// parâmetros
$provided_admin_key = $_GET['admin_key'] ?? null;
$force = (isset($_GET['force']) && ($_GET['force'] === '1' || strtolower($_GET['force']) === 'true'));
$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$group_phase_id = isset($_GET['group_phase_id']) ? intval($_GET['group_phase_id']) : 0;
$knock_phase_id = isset($_GET['knock_phase_id']) ? intval($_GET['knock_phase_id']) : 0;

if ($provided_admin_key !== $ADMIN_KEY) {
    http_response_code(403);
    echo json_encode(['estado' => 'erro', 'mensagem' => 'Não autorizado. Forneça admin_key válido ou implemente autenticação por sessão.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $mysqli->set_charset('utf8mb4');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['estado' => 'erro', 'mensagem' => 'Falha na conexão à base de dados', 'detalhe' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// auto-detect phases se necessário
try {
    if ($group_phase_id <= 0) {
        $q = "SELECT p.id FROM phases p WHERE p.slug = 'group' " . ($tournament_id ? " AND p.tournament_id = " . intval($tournament_id) : "") . " ORDER BY p.id ASC LIMIT 1";
        $r = $mysqli->query($q)->fetch_assoc();
        if ($r) $group_phase_id = intval($r['id']);
    }
    if ($knock_phase_id <= 0) {
        $q = "SELECT p.id FROM phases p WHERE p.slug = 'knockout' " . ($tournament_id ? " AND p.tournament_id = " . intval($tournament_id) : "") . " ORDER BY p.id ASC LIMIT 1";
        $r = $mysqli->query($q)->fetch_assoc();
        if ($r) $knock_phase_id = intval($r['id']);
    }
    if ($group_phase_id <= 0 || $knock_phase_id <= 0) {
        http_response_code(400);
        echo json_encode(['estado'=>'erro','mensagem'=>'Não foi possível determinar group_phase_id ou knock_phase_id. Forneça como parâmetros.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['estado'=>'erro','mensagem'=>'Erro ao detectar fases','detalhe'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 1) calcular standings (reutiliza lógica do get_standings)
try {
    $sql = "
    SELECT 
      g.label AS grupo,
      t.id    AS team_id,
      t.name  AS team_name,
      COALESCE(SUM(CASE WHEN f.home_team_id = t.id THEN f.score_home WHEN f.away_team_id = t.id THEN f.score_away ELSE 0 END),0) AS gf,
      COALESCE(SUM(CASE WHEN f.home_team_id = t.id THEN f.score_away WHEN f.away_team_id = t.id THEN f.score_home ELSE 0 END),0) AS ga,
      COALESCE(SUM(
        CASE
          WHEN (f.home_team_id = t.id AND f.score_home > f.score_away) OR
               (f.away_team_id = t.id AND f.score_away > f.score_home) THEN 3
          WHEN f.score_home = f.score_away THEN 1
          ELSE 0
        END
      ),0) AS pontos
    FROM group_members gm
    JOIN groups g ON gm.group_id = g.id
    JOIN teams t  ON gm.team_id = t.id
    LEFT JOIN fixtures f 
      ON (f.home_team_id = t.id OR f.away_team_id = t.id)
      AND f.group_id = g.id
      AND f.status = 'played'
    WHERE g.phase_id = ?
    GROUP BY g.label, t.id, t.name
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $group_phase_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $standings = [];
    while ($row = $res->fetch_assoc()) {
        $g = $row['grupo'];
        if (!isset($standings[$g])) $standings[$g] = [];
        $gf = intval($row['gf']); $ga = intval($row['ga']);
        $standings[$g][] = [
            'team_id' => intval($row['team_id']),
            'team_name' => $row['team_name'],
            'pontos' => intval($row['pontos']),
            'golos_marcados' => $gf,
            'golos_sofridos' => $ga,
            'saldo' => $gf - $ga
        ];
    }
    // ordenar
    foreach ($standings as $lbl => &$arr) {
        usort($arr, function($a,$b){
            if ($a['pontos'] !== $b['pontos']) return $b['pontos'] - $a['pontos'];
            if ($a['saldo'] !== $b['saldo']) return $b['saldo'] - $a['saldo'];
            if ($a['golos_marcados'] !== $b['golos_marcados']) return $b['golos_marcados'] - $a['golos_marcados'];
            return strcmp($a['team_name'], $b['team_name']);
        });
    }
    unset($arr);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['estado'=>'erro','mensagem'=>'Falha ao calcular classificações','detalhe'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2) criar mapa posicional (ex: '1A' => team_id)
$pos_map = [];
foreach ($standings as $label => $teams) {
    for ($i=0;$i<count($teams);$i++) {
        $key = ($i+1) . $label; // 1A, 2A...
        $pos_map[$key] = $teams[$i]['team_id'];
    }
}

// 3) carregar fixtures com pos_home/pos_away na fase knockout
try {
    $q = "SELECT * FROM fixtures WHERE phase_id = ? " . ($tournament_id ? " AND tournament_id = " . intval($tournament_id) : "");
    $stmtF = $mysqli->prepare($q);
    $stmtF->bind_param('i', $knock_phase_id);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    $fixtures = [];
    while ($f = $resF->fetch_assoc()) $fixtures[] = $f;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['estado'=>'erro','mensagem'=>'Falha ao carregar fixtures de knockout','detalhe'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 4) resolver placeholders e actualizar (transacção)
$atualizados = [];
$ignorados = [];
$erros = [];

try {
    $mysqli->begin_transaction();

    $upd = $mysqli->prepare("UPDATE fixtures SET home_team_id = ?, away_team_id = ?, auto_generated = 1 WHERE id = ? AND (locked = 0 OR ? = 1)");
    $delPart = $mysqli->prepare("DELETE FROM fixture_participants WHERE fixture_id = ?");
    $insPart = $mysqli->prepare("INSERT INTO fixture_participants (fixture_id, team_id, is_home) VALUES (?, ?, ?)");

    // helper closure para resolver placeholders
    $resolve_placeholder = function($pos) use ($mysqli, $pos_map) {
        if ($pos === null) return null;
        $pos = trim($pos);
        if ($pos === '') return null;
        // 1A style
        if (preg_match('/^[1-9][0-9]*[A-Z]$/', $pos)) {
            return $pos_map[$pos] ?? null;
        }
        // W_SF1 or L_SF1
        if (preg_match('/^(W|L)[_\\-](.+)$/i', $pos, $m)) {
            $kind = strtoupper($m[1]);
            $label = $m[2];
            $s = $mysqli->prepare("SELECT id, winner_team_id, score_home, score_away, home_team_id, away_team_id FROM fixtures WHERE bracket_label = ? LIMIT 1");
            $s->bind_param('s', $label);
            $s->execute();
            $r = $s->get_result()->fetch_assoc();
            if (!$r) return null;
            if (!empty($r['winner_team_id'])) {
                $winner = intval($r['winner_team_id']);
                if ($kind === 'W') return $winner;
                $home = intval($r['home_team_id']); $away = intval($r['away_team_id']);
                if ($home && $away) return ($home === $winner) ? $away : $home;
                return null;
            } else {
                if ($r['score_home'] !== null && $r['score_away'] !== null) {
                    if (intval($r['score_home']) > intval($r['score_away'])) $w = intval($r['home_team_id']);
                    elseif (intval($r['score_home']) < intval($r['score_away'])) $w = intval($r['away_team_id']);
                    else $w = null;
                    if ($w) {
                        if ($kind === 'W') return $w;
                        $home = intval($r['home_team_id']); $away = intval($r['away_team_id']);
                        return ($home === $w) ? $away : $home;
                    }
                }
                return null;
            }
        }
        return null;
    };

    foreach ($fixtures as $f) {
        $fid = intval($f['id']);
        $pos_home = $f['pos_home'];
        $pos_away = $f['pos_away'];
        $locked = intval($f['locked']);
        $already_home = $f['home_team_id'];
        $already_away = $f['away_team_id'];

        if (!$force && ($already_home || $already_away)) {
            $ignorados[] = ['fixture_id'=>$fid, 'motivo'=>'já preenchido'];
            continue;
        }
        if ($locked === 1 && !$force) {
            $ignorados[] = ['fixture_id'=>$fid, 'motivo'=>'fixture bloqueada (locked)'];
            continue;
        }

        $resolved_home = $resolve_placeholder($pos_home);
        $resolved_away = $resolve_placeholder($pos_away);

        if ($pos_home !== null && $resolved_home === null) {
            $ignorados[] = ['fixture_id'=>$fid, 'motivo'=>"não foi possível resolver casa ($pos_home)"];
            continue;
        }
        if ($pos_away !== null && $resolved_away === null) {
            $ignorados[] = ['fixture_id'=>$fid, 'motivo'=>"não foi possível resolver fora ($pos_away)"];
            continue;
        }

        if ($resolved_home !== null && $resolved_away !== null && intval($resolved_home) === intval($resolved_away)) {
            $ignorados[] = ['fixture_id'=>$fid, 'motivo'=>'mesma equipa em ambos os lados'];
            continue;
        }

        // executar update
        $upd->bind_param('iiii', $resolved_home, $resolved_away, $fid, ($force ? 1 : 0));
        $upd->execute();

        // atualizar fixture_participants
        $delPart->bind_param('i', $fid); $delPart->execute();
        if ($resolved_home !== null) { $insPart->bind_param('iii', $fid, $resolved_home, 1); $insPart->execute(); }
        if ($resolved_away !== null) { $insPart->bind_param('iii', $fid, $resolved_away, 0); $insPart->execute(); }

        $atualizados[] = ['fixture_id'=>$fid, 'pos_home'=>$pos_home, 'pos_away'=>$pos_away, 'home_team_id'=>$resolved_home, 'away_team_id'=>$resolved_away];
    }

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['estado'=>'erro','mensagem'=>'Transação fracassou ao resolver knockouts','detalhe'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// resposta final em Português
echo json_encode([
    'estado' => 'ok',
    'mensagem' => 'Processo de resolução concluído',
    'forcado' => $force ? true : false,
    'atualizados' => $atualizados,
    'ignorados' => $ignorados,
    'erros' => $erros
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$mysqli->close();
