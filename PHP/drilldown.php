<?php
// drilldown.php
$conn = new mysqli("localhost", "root", "", "testdb");
$conn->set_charset("utf8");

$team_id = $_GET['team'] ?? '';
if(!$team_id){
    echo "팀 ID가 전달되지 않았습니다.";
    exit;
}

$sql = "
SELECT  
    t.team_Name AS team,
    p.player_name AS player,
    SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS total_point
FROM Player p
JOIN Team t ON p.current_team_ID = t.team_ID
JOIN Att_Stats a ON p.player_ID = a.player_ID
WHERE t.team_ID = ?
GROUP BY p.player_name, t.team_Name
ORDER BY total_point DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $team_id);  // i = integer
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Drilldown: 선수별 득점</title></head>
<body>
<h2>팀별 선수 득점</h2>
<table border="1">
<tr><th>팀</th><th>선수</th><th>득점</th></tr>
<?php
while($row = $result->fetch_assoc()){
    echo "<tr>";
    echo "<td>{$row['team']}</td>";
    echo "<td>{$row['player']}</td>";
    echo "<td>{$row['total_point']}</td>";
    echo "</tr>";
}
?>
</table>
</body>
</html>
