<?php
// player_select.php
session_start();
require_once 'db_connect.php';   // 여기서 $conn 사용

// 1) 로그인 여부 & 팀 정보 세션 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$my_team_id = (int)$_SESSION['team_id'];

// 🔹 1. 내 팀 이름 조회 (Prepared Statement)
$sql_team = "SELECT team_Name FROM team WHERE team_ID = ?";
$stmt = $conn->prepare($sql_team);
if (!$stmt) {
    die('팀 조회 쿼리 준비 실패: ' . $conn->error);
}
$stmt->bind_param('i', $my_team_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$my_team_name = $row ? $row['team_Name'] : ($my_team_id . '번 팀');

// 🔹 2. 전체 팀 목록 조회 (다른 팀 선택용)
//   → 입력값 없이 전체 조회라 Injection 위험은 없지만 그래도 prepare()로 맞춰둠
$sql_teams = "
    SELECT team_ID, team_Name
    FROM team
    ORDER BY team_Name
";
$stmt2 = $conn->prepare($sql_teams);
if (!$stmt2) {
    die('팀 목록 쿼리 준비 실패: ' . $conn->error);
}
$stmt2->execute();
$result2 = $stmt2->get_result();
$teams = $result2->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// 🔹 3. 선택 결과 처리 → player_profile.php 로 이동
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_type      = $_POST['team_type'] ?? 'my';   // my / other
    $target_team_id = null;

    if ($team_type === 'my') {
        // 내 팀
        $target_team_id = $my_team_id;
    } else {
        // 다른 팀 (숫자만 허용)
        $target_team_id = (int)($_POST['target_team_id'] ?? 0);
        if ($target_team_id <= 0) {
            $target_team_id = $my_team_id; // 안전장치
        }
    }

    // 여기서는 단순 redirect만 하고, 쿼리는 다음 페이지에서 prepared 사용
    header('Location: player_profile.php?team_id=' . $target_team_id);
    exit;
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>스카우팅 대상 팀 선택</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .container { max-width: 960px; }
    </style>
</head>
<body>
<div class="container">
    <!-- 네비게이션 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">V-League 스카우팅 툴</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">대시보드</a></li>
                    <li class="nav-item"><a class="nav-link active" href="player_select.php">선수 정보 (CRUD)</a></li>
                    <li class="nav-item"><a class="nav-link" href="mynotes.php?mode=mine">내 스카우팅 노트</a></li>
                </ul>
                <a href="logout.php" class="btn btn-outline-light">로그아웃</a>
            </div>
        </div>
    </nav>

    <h2>스카우팅 대상 팀 선택</h2>
    <p>내 팀 혹은 다른 팀을 선택한 뒤, 해당 팀 선수의 상세 정보 페이지로 이동합니다.</p>
    <hr>

    <form method="post" action="player_select.php" class="card">
        <div class="card-body">
            <h5 class="card-title">대상 팀 유형 선택</h5>

            <!-- 1) 내 팀 / 다른 팀 라디오 -->
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input"
                           type="radio"
                           name="team_type"
                           id="team_type_my"
                           value="my"
                           checked>
                    <label class="form-check-label" for="team_type_my">
                        내 팀 선수 (<?= htmlspecialchars($my_team_name, ENT_QUOTES, 'UTF-8') ?>)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input"
                           type="radio"
                           name="team_type"
                           id="team_type_other"
                           value="other">
                    <label class="form-check-label" for="team_type_other">
                        다른 팀 선수
                    </label>
                </div>
            </div>

            <!-- 2) 다른 팀 선택 드롭다운 -->
            <div class="mb-3">
                <label for="target_team_id" class="form-label">다른 팀 선택</label>
                <select class="form-select bg-light text-muted"
                        id="target_team_id"
                        name="target_team_id"
                        disabled>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?= (int)$t['team_ID'] ?>">
                            <?= htmlspecialchars($t['team_Name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">
                    "내 팀 선수"를 선택한 경우 이 값은 무시되고 자동으로 내 팀이 적용됩니다.
                </small>
            </div>

            <button type="submit" class="btn btn-primary">
                선수 선택 페이지로 이동
            </button>
        </div>
    </form>
</div>

<script>
// 라디오 선택에 따라 다른 팀 드롭다운 활성/비활성
function updateTeamSelectState() {
    const myRadio    = document.getElementById('team_type_my');
    const otherRadio = document.getElementById('team_type_other');
    const teamSelect = document.getElementById('target_team_id');

    const isOther = otherRadio.checked;

    teamSelect.disabled = !isOther;

    if (!isOther) {
        teamSelect.classList.add('bg-light', 'text-muted');
    } else {
        teamSelect.classList.remove('bg-light', 'text-muted');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    updateTeamSelectState();
    document.getElementById('team_type_my')
        .addEventListener('change', updateTeamSelectState);
    document.getElementById('team_type_other')
        .addEventListener('change', updateTeamSelectState);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>