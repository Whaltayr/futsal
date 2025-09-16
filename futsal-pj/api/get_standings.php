<?php
/**
 * get_standings.php
 * Devolve classificação por grupo (JSON) — mensagens em Português.
 *
 * Uso:
 *  - /futsal/api/get_standings.php?phase_id=1
 *  - ou apenas /futsal/api/get_standings.php  (tenta detectar a fase 'group' automaticamente)
 *
 * NOTA: Remova display_errors em ambiente de produção.
 */

// --- modo de desenvolvimento (remover em produção) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- cabeçalho JSON ---
header('Content-Type: application/json; charset=utf-8');

// --- configuração DB (ajuste conforme o seu ambiente) ---
require_once __DIR__ . '/connection.php';


// $password_plain = 'SenhaForte123';
// $hash = password_hash($password_plain, PASSWORD_DEFAULT);
// echo "INSERT INTO users (username, email, password_hash, role) VALUES ('admin','admin@example.com','{$hash}','admin');";


// --- obter parâmetro phase_id (opcional) ---
$phase_id = isset($_GET['phase_id']) ? intval($_GET['phase_id']) : 0;
$tournament_id = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;

try {
    // auto-detecta a fase 'group' se necessário
    if ($phase_id <= 0) {
        $sqlAuto = "SELECT p.id
                    FROM phases p
                    WHERE p.slug = 'group' " .
                    ($tournament_id ? " AND p.tournament_id = " . intval($tournament_id) : "") .
                    " ORDER BY p.id ASC LIMIT 1";
        $resAuto = $mysqli->query($sqlAuto);
        if ($row = $resAuto->fetch_assoc()) {
            $phase_id = intval($row['id']);
        } else {
            http_response_code(400);
            echo json_encode(['erro' => "Parâmetro 'phase_id' não fornecido e nenhuma fase 'group' encontrada."], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }

    // Query: agregações — sem alias usados no ORDER BY (compatível com MySQL/MariaDB)
    $sql = "
    SELECT
      g.label AS grupo,
      t.id    AS team_id,
      t.name  AS team_name,
      COALESCE(SUM(CASE WHEN f.home_team_id = t.id THEN f.score_home
                        WHEN f.away_team_id = t.id THEN f.score_away
                        ELSE 0 END), 0) AS golos_marcados,
      COALESCE(SUM(CASE WHEN f.home_team_id = t.id THEN f.score_away
                        WHEN f.away_team_id = t.id THEN f.score_home
                        ELSE 0 END), 0) AS golos_sofridos,
      COALESCE(SUM(
        CASE
          WHEN (f.home_team_id = t.id AND f.score_home > f.score_away) OR
               (f.away_team_id = t.id AND f.score_away > f.score_home) THEN 3
          WHEN f.score_home = f.score_away THEN 1
          ELSE 0
        END
      ), 0) AS pontos
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
    $stmt->bind_param('i', $phase_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $dados = [];
    while ($r = $res->fetch_assoc()) {
        $grupo = $r['grupo'];
        if (!isset($dados[$grupo])) $dados[$grupo] = [];
        $gf = intval($r['golos_marcados']);
        $ga = intval($r['golos_sofridos']);
        $dados[$grupo][] = [
            'team_id' => intval($r['team_id']),
            'equipa' => $r['team_name'],
            'pontos' => intval($r['pontos']),
            'golos_marcados' => $gf,
            'golos_sofridos' => $ga,
            'saldo' => $gf - $ga
        ];
    }

    // ordenar cada grupo: pontos, saldo, golos_marcados, nome
    foreach ($dados as $g => &$arr) {
        usort($arr, function($a, $b){
            if ($a['pontos'] !== $b['pontos']) return $b['pontos'] - $a['pontos'];
            if ($a['saldo'] !== $b['saldo']) return $b['saldo'] - $a['saldo'];
            if ($a['golos_marcados'] !== $b['golos_marcados']) return $b['golos_marcados'] - $a['golos_marcados'];
            return strcmp($a['equipa'], $b['equipa']);
        });
    }
    unset($arr);

    // resposta
    echo json_encode([
        'phase_id' => $phase_id,
        'mensagem' => 'Classificações carregadas com sucesso',
        'standings' => $dados
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao calcular a classificação', 'detalhe' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$mysqli->close();
