<?php
header('Content-Type: application/json; charset=utf-8');

// PHP Notice/Warning 출력 방지
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// DB 연결
$conn = new mysqli("localhost", "root", "", "testdb");
$conn->set_charset("utf8");

// 선택된 메트릭 (기본 score)
$metric = $_GET['metric'] ?? 'score';

// SQL 초기화
$sql = "";
$main_metric_name = "";
$sub_metric_name = null;

switch($metric){
    case 'score':
        $main_metric_name = "총 득점";
        $sql = "
        SELECT *
        FROM (
            SELECT 
                t.team_ID,
                t.team_Name AS team,
                SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS metric_value
            FROM Player p
            JOIN Team t ON p.current_team_ID = t.team_ID
            JOIN Att_Stats a ON p.player_ID = a.player_ID
            GROUP BY t.team_ID, t.team_Name WITH ROLLUP
        ) AS rollup_result
        ORDER BY 
            CASE WHEN team IS NULL THEN 1 ELSE 0 END,
            metric_value DESC
        ";
        break;

    case 'defense':
        $main_metric_name = "수비 도움";
        $sql = "
        SELECT *
        FROM (
            SELECT 
                t.team_ID,
                t.team_Name AS team,
                SUM(l.dig_suc + l.receive_good) AS metric_value
            FROM Player p
            JOIN Team t ON p.current_team_ID = t.team_ID
            JOIN L_Stats l ON p.player_ID = l.player_ID
            GROUP BY t.team_ID, t.team_Name WITH ROLLUP
        ) AS rollup_result
        ORDER BY 
            CASE WHEN team IS NULL THEN 1 ELSE 0 END,
            metric_value DESC
        ";
        break;

    case 'setting':
        $main_metric_name = "세팅 도움";
        $sql = "
        SELECT *
        FROM (
            SELECT 
                t.team_ID,
                t.team_Name AS team,
                SUM(s.set_suc) AS metric_value
            FROM Player p
            JOIN Team t ON p.current_team_ID = t.team_ID
            JOIN S_Stats s ON p.player_ID = s.player_ID
            GROUP BY t.team_ID, t.team_Name WITH ROLLUP
        ) AS rollup_result
        ORDER BY 
            CASE WHEN team IS NULL THEN 1 ELSE 0 END,
            metric_value DESC
        ";
        break;

    default:
        echo json_encode(['error' => '유효하지 않은 metric 파라미터']);
        exit;
}

// 쿼리 실행
$result = $conn->query($sql);
if(!$result){
    echo json_encode(['error' => "SQL 오류: " . $conn->error]);
    exit;
}

// 데이터 가공
$teams = [];
$total_sum = 0;

while($row = $result->fetch_assoc()){
    if(is_null($row['team'])){
        continue; // 전체 합계는 나중에 추가
    }

    $teams[] = [
        'team_id' => $row['team_ID'],
        'team_name' => $row['team'],
        'main_metric' => (int)$row['metric_value'],
        'drilldown_url' => "drilldown.html?teamId={$row['team_ID']}&metric={$metric}"
    ];
    $total_sum += (int)$row['metric_value'];
}

// 전체 합계 추가
$teams[] = [
    'team_id' => 'TOTAL',
    'team_name' => '전체 합계',
    'main_metric' => $total_sum,
    'drilldown_url' => '-'
];

// JSON 출력
$response = [
    'title' => "팀별 {$main_metric_name} 분석 (ROLLUP)",
    'main_metric_name' => $main_metric_name,
    'sub_metric_name' => $sub_metric_name,
    'data' => $teams
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
