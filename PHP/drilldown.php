<?php
// drilldown.php
session_start();
require_once 'db_connect.php';   // mysqli $conn

// 로그인 체크 (롤업에서 넘어오거나 Ajax 둘 다)
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    // Ajax 호출이면 JSON으로 에러 반환
    if (isset($_GET['team']) && isset($_GET['metric'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: login.php');
    exit;
}

// ------------------------
// Ajax 요청 처리 (JSON)
// ------------------------
if (isset($_GET['team']) && isset($_GET['metric'])) {
    header('Content-Type: application/json; charset=utf-8');

    $team_id = (int)$_GET['team'];
    $metric  = $_GET['metric'] ?? 'score';

    if (!$team_id) {
        echo json_encode(['error' => '팀 ID가 전달되지 않았습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($metric) {
        case 'score':
            $sql = "
                SELECT p.player_name AS player,
                       pp.position_name AS position,
                       SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN Att_Stats a ON p.player_ID = a.player_ID
                WHERE p.current_team_ID = ?
                GROUP BY p.player_name, pp.position_name
            ";
            break;
        case 'defense':
            $sql = "
                SELECT p.player_name AS player,
                       pp.position_name AS position,
                       SUM(l.dig_suc + l.receive_good) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN L_Stats l ON p.player_ID = l.player_ID
                WHERE p.current_team_ID = ?
                GROUP BY p.player_name, pp.position_name
            ";
            break;
        case 'setting':
            $sql = "
                SELECT p.player_name AS player,
                       pp.position_name AS position,
                       SUM(s.set_suc) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN S_Stats s ON p.player_ID = s.player_ID
                WHERE p.current_team_ID = ?
                GROUP BY p.player_name, pp.position_name
            ";
            break;
        default:
            echo json_encode(['error' => '유효하지 않은 metric 파라미터'], JSON_UNESCAPED_UNICODE);
            exit;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['error' => '쿼리 준비 실패: ' . $conn->error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = [
            'player'       => $row['player'],
            'position'     => $row['position'],
            'metric_value' => (int)$row['metric_value']
        ];
    }
    $stmt->close();

    // 값 기준 내림차순 정렬
    usort($players, fn($a, $b) => $b['metric_value'] <=> $a['metric_value']);

    // DENSE_RANK
    $rank = 1;
    $prevValue = null;
    foreach ($players as $index => &$player) {
        if ($prevValue !== null && $player['metric_value'] == $prevValue) {
            $player['rank'] = $rank;
        } else {
            $rank = $index + 1;
            $player['rank'] = $rank;
        }
        $prevValue = $player['metric_value'];
    }
    unset($player);

    echo json_encode($players, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ------------------------
// HTML 출력 (일반 접근)
// ------------------------
$teamId   = $_GET['teamId']   ?? '';
$teamName = $_GET['teamName'] ?? '팀 이름 없음';
$metric   = $_GET['metric']   ?? 'score';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V-League Drilldown 분석</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { padding: 20px; font-family: 'Inter', sans-serif; }
.container { max-width: 960px; }
.btn-check:checked + .btn-outline-primary {
    background-color: var(--bs-primary);
    color: white;
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.5);
}
</style>
</head>
<body>
<div class="container">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4 shadow">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">V-League 스카우팅 툴</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">대시보드</a></li>
                <li class="nav-item"><a class="nav-link" href="player_select.php">선수 정보 (CRUD)</a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="#">Drilldown</a></li>
            </ul>
            <a href="logout.php" class="btn btn-outline-light">로그아웃</a>
        </div>
    </div>
</nav>

<h1 class="mb-4 display-6 fw-bold text-primary text-center" id="page-title">
    <?= htmlspecialchars($teamName) ?>
</h1>

<div class="mb-5 p-4 bg-light border rounded shadow-sm text-center">
    <h5 class="mb-3 text-dark">선수별 분석 영역</h5>
    <div class="btn-group" role="group" id="metric-selector">
        <input type="radio" class="btn-check" name="metric" id="metric_score" value="score"
               autocomplete="off" <?= $metric=='score'?'checked':'' ?>>
        <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_score">득점 종합</label>

        <input type="radio" class="btn-check" name="metric" id="metric_defense" value="defense"
               autocomplete="off" <?= $metric=='defense'?'checked':'' ?>>
        <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_defense">수비 도움</label>

        <input type="radio" class="btn-check" name="metric" id="metric_setting" value="setting"
               autocomplete="off" <?= $metric=='setting'?'checked':'' ?>>
        <label class="btn btn-outline-primary rounded-pill px-4" for="metric_setting">세팅 도움</label>
    </div>
</div>

<div class="table-responsive">
<table class="table table-striped table-hover align-middle shadow-lg rounded-3 overflow-hidden">
    <thead class="table-dark">
    <tr>
        <th scope="col" class="text-center">순위</th>
        <th scope="col" class="text-center">선수</th>
        <th scope="col" class="text-center">포지션</th>
        <th scope="col" class="text-center" id="metric-header">득점</th>
    </tr>
    </thead>
    <tbody id="table-body">
    <tr>
        <td colspan="4" class="text-center py-4 text-muted">
            팀을 선택하고 메트릭을 지정하면 데이터가 로드됩니다.
        </td>
    </tr>
    </tbody>
</table>
</div>

<button id="back-button" class="btn btn-secondary mt-3">Rollup으로 돌아가기</button>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let teamId   = "<?= htmlspecialchars($teamId) ?>";
    let metric   = "<?= $metric ?>";

    const tableBody    = document.getElementById('table-body');
    const metricSel    = document.getElementById('metric-selector');
    const metricHeader = document.getElementById('metric-header');
    const backButton   = document.getElementById('back-button');

    const metricLabelMap = {
        score:   "선수별 득점 종합",
        defense: "선수별 수비 도움",
        setting: "선수별 세팅 도움"
    };

    function fetchDrilldownData(metric) {
        if (!teamId) {
            tableBody.innerHTML =
                '<tr><td colspan="4" class="text-center text-muted">팀 정보가 없습니다.</td></tr>';
            return;
        }
        tableBody.innerHTML =
            '<tr><td colspan="4" class="text-center py-4">데이터 로드 중...</td></tr>';
        metricHeader.textContent = metricLabelMap[metric];

        fetch(`drilldown.php?team=${teamId}&metric=${metric}`)
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data)) {
                    tableBody.innerHTML =
                        '<tr><td colspan="4" class="text-center text-danger">데이터 형식 오류</td></tr>';
                    return;
                }
                if (data.length === 0) {
                    tableBody.innerHTML =
                        '<tr><td colspan="4" class="text-center text-muted">데이터가 없습니다.</td></tr>';
                    return;
                }
                let html = '';
                data.forEach(player => {
                    html += `
                        <tr>
                            <td class="text-center">${player.rank}</td>
                            <td class="text-center">${player.player}</td>
                            <td class="text-center">${player.position}</td>
                            <td class="text-center">${player.metric_value}</td>
                        </tr>`;
                });
                tableBody.innerHTML = html;
            })
            .catch(err => {
                tableBody.innerHTML =
                    `<tr><td colspan="4" class="text-center text-danger">
                        데이터 로드 실패: ${err.message}
                      </td></tr>`;
            });
    }

    // 초기 로드
    fetchDrilldownData(metric);

    metricSel.addEventListener('change', e => {
        const selected = e.target.value;
        if (selected) {
            metric = selected;
            fetchDrilldownData(metric);
        }
    });

    backButton.addEventListener('click', () => {
        window.location.href = `rollup.php?metric=${metric}`;
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>