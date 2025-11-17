<?php
// rollup.php
$conn = new mysqli("localhost", "root", "", "testdb");
$conn->set_charset("utf8");

$sql = "
SELECT 
    t.team_ID,
    t.team_Name AS team,
    SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS total_points
FROM Player p
JOIN Team t ON p.current_team_ID = t.team_ID
JOIN Att_Stats a ON p.player_ID = a.player_ID
GROUP BY t.team_ID, t.team_Name
ORDER BY total_points DESC
";


$result = $conn->query($sql);
$rank = 1; // 순위 초기화
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Team ROLLUP</title></head>
<body>
<h2>팀별 득점 현황 (ROLLUP)</h2>
<table border="1">
<tr><th>순위</th><th>팀</th><th>총 득점</th><th>Drilldown</th></tr>
<?php
$rank = 1;
$total_sum = 0;
$rows = [];
while($row = $result->fetch_assoc()){
    $rows[] = $row;
    $total_sum += $row['total_points'];
}

foreach($rows as $row){
    echo "<tr>";
    echo "<td>{$rank}</td>";
    echo "<td>{$row['team']}</td>";
    echo "<td>{$row['total_points']}</td>";
    echo "<td><a href='drilldown.php?team={$row['team_ID']}'>보기</a></td>";
    echo "</tr>";
    $rank++;
}

// 전체 합계 표시
echo "<tr><td>-</td><td>전체 합계</td><td>{$total_sum}</td><td>-</td></tr>";

?>
</table>
</body>
</html>
