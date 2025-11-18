<?php
// player_profile.php
session_start();
require_once 'db_connect.php';   // 여기서 $conn (mysqli) 사용

// 1) 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];    // 현재 로그인한 사용자
$my_team_id = (int)$_SESSION['team_id'];    // 내가 감독하는 팀 (작성자 소속팀)

// 2) 대상 팀 ID 결정 (GET/POST → 없으면 내 팀)
$target_team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : $my_team_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['team_id'])) {
        $target_team_id = (int)$_POST['team_id'];
    }
}

// 🔹 대상 팀 이름 조회 (Prepared Statement 사용)
$sql_team = "SELECT team_Name FROM Team WHERE team_ID = ?";
$stmt = $conn->prepare($sql_team);
if (!$stmt) {
    die('팀 조회 쿼리 준비 실패: ' . $conn->error);
}
$stmt->bind_param('i', $target_team_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    // 잘못된 team_id가 온 경우, 안전하게 내 팀으로 되돌림
    $target_team_id = $my_team_id;
    $stmt->bind_param('i', $target_team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
}
$stmt->close();

$target_team_name = $row ? $row['team_Name'] : ($target_team_id . '번 팀');

// 3) 대상 팀 선수 목록 조회 (드롭다운용)
$sql_players = "
    SELECT player_ID, player_name
    FROM Player
    WHERE current_team_ID = ?
    ORDER BY player_name
";
$stmt = $conn->prepare($sql_players);
if (!$stmt) {
    die('선수 목록 쿼리 준비 실패: ' . $conn->error);
}
$stmt->bind_param('i', $target_team_id);
$stmt->execute();
$result = $stmt->get_result();
$team_players = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 팀에 선수가 하나도 없을 때
if (empty($team_players)) {
    die('선택한 팀에 등록된 선수가 없습니다.');
}

// 4) 어떤 선수를 볼지 결정 (GET 또는 POST에서 player_id 가져오기)
$player_id = $_GET['player_id'] ?? ($_POST['player_id'] ?? null);
if ($player_id === null) {
    // 아무것도 없으면 팀 선수 중 첫 번째
    $player_id = $team_players[0]['player_ID'];
}
$player_id = (int)$player_id;

// 5) 노트 작성/수정/삭제 처리
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $player_id = (int)($_POST['player_id'] ?? $player_id);

    if ($action === 'save_note') {
        // 새 노트 작성 또는 내가 쓴 노트 수정
        $note_content = trim($_POST['note_content'] ?? '');

        if ($note_content === '') {
            $message = '메모 내용을 입력해 주세요.';
        } else {
            // 이미 내가 이 선수에 대해 쓴 노트가 있는지 확인
            $sql_check = "
                SELECT report_ID
                FROM Scouting_Report
                WHERE user_ID = ?
                  AND team_ID = ?
                  AND player_ID = ?
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql_check);
            if (!$stmt) {
                die('노트 조회 쿼리 준비 실패: ' . $conn->error);
            }
            $stmt->bind_param('iii', $user_id, $my_team_id, $player_id);
            $stmt->execute();
            $result   = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            if ($existing) {
                // 있으면 UPDATE
                $sql_update = "
                    UPDATE Scouting_Report
                    SET note_content = ?,
                        note_date    = NOW()
                    WHERE report_ID = ?
                ";
                $stmt = $conn->prepare($sql_update);
                if (!$stmt) {
                    die('노트 수정 쿼리 준비 실패: ' . $conn->error);
                }
                $report_id = (int)$existing['report_ID'];
                $stmt->bind_param('si', $note_content, $report_id);
                $stmt->execute();
                $stmt->close();
                $message = '기존 노트가 수정되었습니다.';
            } else {
                // 없으면 INSERT
                $sql_insert = "
                    INSERT INTO Scouting_Report (user_ID, team_ID, player_ID, note_date, note_content)
                    VALUES (?, ?, ?, NOW(), ?)
                ";
                $stmt = $conn->prepare($sql_insert);
                if (!$stmt) {
                    die('노트 저장 쿼리 준비 실패: ' . $conn->error);
                }
                $stmt->bind_param('iiis', $user_id, $my_team_id, $player_id, $note_content);
                $stmt->execute();
                $stmt->close();
                $message = '새 노트가 저장되었습니다.';
            }
        }
    } elseif ($action === 'delete_note') {
        // 노트 삭제 (내가 쓴 것만)
        $report_id = (int)($_POST['report_id'] ?? 0);
        if ($report_id > 0) {
            $sql_delete = "
                DELETE FROM Scouting_Report
                WHERE report_ID = ?
                  AND user_ID   = ?
                  AND team_ID   = ?
            ";
            $stmt = $conn->prepare($sql_delete);
            if (!$stmt) {
                die('노트 삭제 쿼리 준비 실패: ' . $conn->error);
            }
            $stmt->bind_param('iii', $report_id, $user_id, $my_team_id);
            $stmt->execute();
            $stmt->close();
            $message = '노트가 삭제되었습니다.';
        }
    }
}

// 6) 선택된 선수의 기본 정보 조회 (대상 팀 기준)
$sql_player = "
    SELECT P.player_ID,
           P.player_name,
           P.salary,
           T.team_Name,
           PP.position_Name
    FROM Player P
    JOIN Team T ON P.current_team_ID = T.team_ID
    JOIN Player_Position PP ON P.position_ID = PP.position_ID
    WHERE P.player_ID = ?
      AND P.current_team_ID = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql_player);
if (!$stmt) {
    die('선수 정보 쿼리 준비 실패: ' . $conn->error);
}
$stmt->bind_param('ii', $player_id, $target_team_id);
$stmt->execute();
$result = $stmt->get_result();
$player = $result->fetch_assoc();
$stmt->close();

if (!$player) {
    die('해당 선수 정보를 찾을 수 없습니다. (선택한 팀 소속 선수가 아닐 수 있음)');
}

// 7) 이 선수에 대한 "우리 팀"의 모든 노트 조회 (작성자 팀 기준)
$sql_notes = "
    SELECT report_ID, user_ID, note_date, note_content
    FROM Scouting_Report
    WHERE player_ID = ?
      AND team_ID   = ?
    ORDER BY note_date DESC
";
$stmt = $conn->prepare($sql_notes);
if (!$stmt) {
    die('노트 목록 쿼리 준비 실패: ' . $conn->error);
}
$stmt->bind_param('ii', $player_id, $my_team_id);
$stmt->execute();
$result        = $stmt->get_result();
$player_notes  = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>선수 상세 정보</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .container { max-width: 960px; }
    </style>
</head>
<body>

<div class="container">
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

    <h2>선수 상세 정보 (<?= htmlspecialchars($player['player_name']) ?>)</h2>
    <p>선택한 팀(<?= htmlspecialchars($target_team_name) ?>)의 선수 중 한 명에 대한 스카우팅 노트를 관리합니다.</p>
    <hr>

    <!-- 1) 대상 팀 및 선수 선택 -->
    <form class="card mb-4" method="get" action="player_profile.php">
        <div class="card-body">
            <h5 class="card-title">대상 선수 선택</h5>
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label">대상 팀</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($target_team_name) ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label for="player_select" class="form-label">선수</label>
                    <select class="form-select" id="player_select" name="player_id">
                        <?php foreach ($team_players as $p): ?>
                            <option value="<?= (int)$p['player_ID'] ?>"
                                <?= ($p['player_ID'] == $player_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['player_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="hidden" name="team_id" value="<?= $target_team_id ?>">
                    <button type="submit" class="btn btn-primary mt-3 mt-md-0">
                        선수 정보 보기
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- 2) 기본 정보 -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">기본 정보 (Read)</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><strong>이름:</strong> <?= htmlspecialchars($player['player_name']) ?></li>
                <li class="list-group-item"><strong>포지션:</strong> <?= htmlspecialchars($player['position_Name']) ?></li>
                <li class="list-group-item"><strong>소속팀:</strong> <?= htmlspecialchars($player['team_Name']) ?></li>
                <li class="list-group-item"><strong>연봉:</strong> <?= number_format($player['salary']) ?> 만원</li>
            </ul>
        </div>
    </div>

    <!-- 3) 스카우팅 노트 작성 (Create / Update) -->
    <form class="card mb-4" method="post"
          action="player_profile.php?team_id=<?= $target_team_id ?>&player_id=<?= $player_id ?>">
        <div class="card-body">
            <h5 class="card-title">스카우팅 노트 작성 (Create/Update)</h5>

            <?php if ($message): ?>
                <div class="alert alert-info py-2">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <input type="hidden" name="action" value="save_note">
            <input type="hidden" name="team_id" value="<?= $target_team_id ?>">
            <input type="hidden" name="player_id" value="<?= $player_id ?>">

            <div class="mb-3">
                <label for="note_content" class="form-label">메모 내용</label>
                <textarea class="form-control" id="note_content" name="note_content"
                          rows="3" placeholder="이 선수에 대한 평가를 남겨주세요..."></textarea>
                <small class="text-muted">
                    이미 내가 작성한 노트가 있으면 이 내용으로 <strong>수정</strong>됩니다. (작성자 소속팀: <?= htmlspecialchars($my_team_id) ?>)
                </small>
            </div>
            <button type="submit" class="btn btn-success">노트 저장 (INSERT/UPDATE)</button>
        </div>
    </form>

    <!-- 4) 이 선수에 대한 우리 팀의 모든 노트 목록 -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">이 선수에 대한 우리 팀 스카우팅 리포트</h5>
            <a href="mynotes.php?mode=mine" class="btn btn-outline-primary mb-3">
                내 모든 노트 보러가기 (수정/삭제) &raquo;
            </a>

            <?php if (empty($player_notes)): ?>
                <p class="text-muted">아직 이 선수에 대한 스카우팅 리포트가 없습니다.</p>
            <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">작성자 ID</th>
                        <th scope="col">작성 일시</th>
                        <th scope="col">평가 내용</th>
                        <th scope="col" style="width: 120px;">관리</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($player_notes as $n): ?>
                        <tr>
                            <td><?= htmlspecialchars($n['user_ID']) ?></td>
                            <td><?= htmlspecialchars($n['note_date']) ?></td>
                            <td><?= nl2br(htmlspecialchars($n['note_content'])) ?></td>
                            <td>
                                <?php if ((int)$n['user_ID'] === $user_id): ?>
                                    <form method="post"
                                          action="player_profile.php?team_id=<?= $target_team_id ?>&player_id=<?= $player_id ?>"
                                          onsubmit="return confirm('이 노트를 삭제하시겠습니까?');">
                                        <input type="hidden" name="action" value="delete_note">
                                        <input type="hidden" name="team_id" value="<?= $target_team_id ?>">
                                        <input type="hidden" name="player_id" value="<?= $player_id ?>">
                                        <input type="hidden" name="report_id" value="<?= (int)$n['report_ID'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>