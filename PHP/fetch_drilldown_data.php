<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$conn = new mysqli("localhost", "root", "", "testdb");
$conn->set_charset("utf8");

$team_id = $_GET['team'] ?? '';
$metric = $_GET['metric'] ?? 'score';

if(!$team_id){
    echo json_encode(['error' => '팀 ID가 전달되지 않았습니다.']);
    exit;
}


$sql = "";
switch($metric){
    case 'score':
        $sql = "SELECT p.player_name AS player,
                    pp.position_name AS position,
                    SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN Att_Stats a ON p.player_ID = a.player_ID
                WHERE p.current_team_ID = ?
                GROUP BY p.player_name, pp.position_name
                ORDER BY metric_value DESC";
        break;
    case 'defense':
        $sql = "SELECT p.player_name AS player,
                        pp.position_name AS position,
                       SUM(l.dig_suc + l.receive_good) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN L_Stats l ON p.player_ID = l.player_ID
                WHERE p.current_team_ID = ?
                GROUP BY p.player_name
                ORDER BY metric_value DESC";
        break;
    case 'setting':
        $sql = "SELECT p.player_name AS player,
                        pp.position_name AS position,
                       SUM(s.set_suc) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN S_Stats s ON p.player_ID = s.player_ID
                WHERE p.current_team_ID = ?
                GROUP BY p.player_name
                ORDER BY metric_value DESC";
        break;
    default:
        echo json_encode(['error'=>'유효하지 않은 metric 파라미터']);
        exit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();

$players = [];
while($row = $result->fetch_assoc()){
    $players[] = [
        'player' => $row['player'],
        'position' => $row['position'],  
        'metric_value' => (int)$row['metric_value']
    ];
}

echo json_encode($players, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
