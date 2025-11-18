<?php
// mynotes.php
session_start();

// 디버깅용(개발할 때만 켜 두기)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';   // mysqli $conn 사용

// 1) 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$team_id = (int)$_SESSION['team_id'];

// -----------------------
// 1) 팀 이름 조회 (Prepared Statement)
// -----------------------
$sql_team = "SELECT team_Name FROM Team WHERE team_ID = ?";
$stmt = $conn->prepare($sql_team);
if (!$stmt) {
    die('팀 조회 쿼리 준비 실패: ' . $conn->error);
}
$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$team_name = $row ? $row['team_Name'] : ($team_id . "번 팀");
$stmt->close();

// -----------------------
// 2) 모드 설정 (mine / team)
// -----------------------
$mode = $_GET['mode'] ?? 'mine';
$mode = ($mode === 'team') ? 'team' : 'mine';

$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$message = "";

// -----------------------
// 3) POST 처리 (update / delete)
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action    = $_POST['action'] ?? '';
    // 링크에서 들어온 mode 말고, 폼에서 넘어온 mode를 우선
    $mode      = $_POST['mode'] ?? $mode;
    $mode      = ($mode === 'team') ? 'team' : 'mine';
    $report_id = (int)($_POST['report_id'] ?? 0);

    // ===== UPDATE (내 노트만) =====
    if ($action === 'update' && $report_id > 0) {

        $note_content = trim($_POST['note_content'] ?? '');
        if ($note_content === '') {
            $message = "내용을 비울 수 없습니다.";
        } else {
            $sql = "
                UPDATE Scouting_Report
                SET note_content = ?, note_date = NOW()
                WHERE report_ID = ? AND user_ID = ?
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                die("노트 수정 쿼리 준비 실패: " . $conn->error);
            }
            $stmt->bind_param("sii", $note_content, $report_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $message = "노트가 수정되었습니다.";
            $edit_id = 0;
        }

    // ===== DELETE (트랜잭션 사용) =====
    } elseif ($action === 'delete' && $report_id > 0) {

        $author_pw = $_POST['author_pw'] ?? '';

        try {
            // 트랜잭션 시작
            $conn->begin_transaction();

            // (1) 작성자 정보 조회 + 잠금 (FOR UPDATE)
            $sql_info = "
                SELECT SR.report_ID, SR.user_ID, U.team_ID
                FROM Scouting_Report SR
                JOIN user U ON SR.user_ID = U.user_ID
                WHERE SR.report_ID = ?
                FOR UPDATE
            ";
            $stmt = $conn->prepare($sql_info);
            if (!$stmt) {
                throw new Exception("리포트 정보 조회 실패: " . $conn->error);
            }
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $info   = $result->fetch_assoc();
            $stmt->close();

            if (!$info) {
                throw new Exception("해당 리포트가 존재하지 않습니다.");
            }

            $author_id  = (int)$info['user_ID'];
            $author_tid = (int)$info['team_ID'];

            // (2) 다른 팀 리포트면 삭제 불가
            if ($author_tid !== $team_id) {
                throw new Exception("다른 팀이 작성한 노트는 삭제할 수 없습니다.");
            }

            // (3-A) 내가 쓴 글 → 비밀번호 없이 바로 삭제
            if ($author_id === $user_id) {

                $sql_del = "DELETE FROM Scouting_Report WHERE report_ID = ?";
                $stmt = $conn->prepare($sql_del);
                if (!$stmt) {
                    throw new Exception("노트 삭제 쿼리 준비 실패: " . $conn->error);
                }
                $stmt->bind_param("i", $report_id);
                $stmt->execute();
                $stmt->close();

            // (3-B) 팀원이 쓴 글 → 비밀번호 확인 후 삭제
            } else {

                if ($author_pw === '') {
                    throw new Exception("비밀번호를 입력해야 삭제할 수 있습니다.");
                }

                // 작성자 비밀번호 조회
                $sql_pw = "SELECT user_PW FROM user WHERE user_ID = ?";
                $stmt = $conn->prepare($sql_pw);
                if (!$stmt) {
                    throw new Exception("비밀번호 조회 쿼리 준비 실패: " . $conn->error);
                }
                $stmt->bind_param("i", $author_id);
                $stmt->execute();
                $pwres = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$pwres || $pwres['user_PW'] !== $author_pw) {
                    throw new Exception("비밀번호가 일치하지 않아 삭제할 수 없습니다.");
                }

                // 비밀번호 일치 → 최종 삭제
                $sql_del = "DELETE FROM Scouting_Report WHERE report_ID = ?";
                $stmt = $conn->prepare($sql_del);
                if (!$stmt) {
                    throw new Exception("노트 삭제 쿼리 준비 실패: " . $conn->error);
                }
                $stmt->bind_param("i", $report_id);
                $stmt->execute();
                $stmt->close();
            }

            // 모든 과정 OK → COMMIT
            $conn->commit();
            $message = "노트가 삭제되었습니다.";

        } catch (Exception $e) {
            // 에러 발생 시 ROLLBACK
            $conn->rollback();
            $message = $e->getMessage() ?: "삭제 처리 중 오류가 발생했습니다.";
        }

        // 삭제 후 편집 모드 해제
        $edit_id = 0;
    }
}

// -----------------------
// 4) 노트 목록 조회 (mine / team)
// -----------------------
if ($mode === 'mine') {
    // 내가 쓴 것만
    $sql = "
        SELECT SR.report_ID, SR.user_ID, SR.note_date, SR.note_content, P.player_name
        FROM Scouting_Report SR
        JOIN Player P ON SR.player_ID = P.player_ID
        WHERE SR.user_ID = ?
        ORDER BY SR.note_date DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("내 노트 목록 쿼리 실패: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
} else {
    // 우리 팀 전체 노트
    $sql = "
        SELECT SR.report_ID, SR.user_ID, SR.note_date, SR.note_content, P.player_name
        FROM Scouting_Report SR
        JOIN Player P ON SR.player_ID = P.player_ID
        JOIN user U   ON SR.user_ID = U.user_ID
        WHERE U.team_ID = ?
        ORDER BY SR.note_date DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("팀 노트 목록 쿼리 실패: " . $conn->error);
    }
    $stmt->bind_param("i", $team_id);
}
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------
// 5) 편집 모드용 데이터
// -----------------------
$edit_note = null;
if ($edit_id > 0) {
    $sql_edit = "
        SELECT report_ID, note_content
        FROM Scouting_Report
        WHERE report_ID = ? AND user_ID = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_edit);
    if (!$stmt) {
        die("편집용 노트 조회 실패: " . $conn->error);
    }
    $stmt->bind_param("ii", $edit_id, $user_id);
    $stmt->execute();
    $edit_note = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$edit_note) {
        $edit_id = 0; // 내 노트가 아니면 편집 불가
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>내 스카우팅 노트</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .container { max-width: 1100px; }
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
                    <li class="nav-item"><a class="nav-link" href="player_select.php">선수 정보 (CRUD)</a></li>
                    <li class="nav-item"><a class="nav-link active" href="mynotes.php?mode=mine">내 스카우팅 노트</a></li>
                </ul>
                <a href="logout.php" class="btn btn-outline-light">로그아웃</a>
            </div>
        </div>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="mb-0">
            내 스카우팅 노트
            <small class="text-muted">
                (<?= $mode === 'mine' ? '내가 작성한 것만' : '팀 전체' ?>)
            </small>
        </h2>
        <div>
            <a href="mynotes.php?mode=mine"
               class="btn btn-sm <?= $mode === 'mine' ? 'btn-primary' : 'btn-outline-primary' ?>">
                내 노트만
            </a>
            <a href="mynotes.php?mode=team"
               class="btn btn-sm <?= $mode === 'team' ? 'btn-primary' : 'btn-outline-primary' ?>">
                팀 전체 노트
            </a>
        </div>
    </div>

    <p class="text-muted">
        <?php if ($mode === 'mine'): ?>
            내가 작성한 스카우팅 노트를 확인하고 수정/삭제할 수 있습니다.
        <?php else: ?>
            <?= htmlspecialchars($team_name) ?> 소속 감독들이 작성한 모든 스카우팅 노트를 확인할 수 있습니다.
            (다른 사람이 작성한 노트를 삭제하려면 작성자 계정 비밀번호를 알아야 합니다.)
        <?php endif; ?>
    </p>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- 편집 폼 -->
    <?php if ($edit_note): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">선택한 노트 수정 (리포트 ID: <?= $edit_note['report_ID'] ?>)</h5>
                <form method="post" action="mynotes.php?mode=<?= $mode ?>&edit_id=<?= $edit_note['report_ID'] ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="mode" value="<?= $mode ?>">
                    <input type="hidden" name="report_id" value="<?= $edit_note['report_ID'] ?>">
                    <div class="mb-3">
                        <textarea name="note_content" class="form-control"
                                  rows="3"><?= htmlspecialchars($edit_note['note_content']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">저장</button>
                    <a href="mynotes.php?mode=<?= $mode ?>" class="btn btn-secondary">취소</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 노트 리스트 -->
    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0 table-striped table-hover">
                <thead class="table-dark">
                <tr>
                    <th scope="col">리포트 ID</th>
                    <th scope="col">선수 이름</th>
                    <th scope="col">작성자 ID</th>
                    <th scope="col">작성 일시</th>
                    <th scope="col">내용</th>
                    <th scope="col" style="width: 150px;">관리</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($notes)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            등록된 노트가 없습니다.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($notes as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['report_ID']) ?></td>
                            <td><?= htmlspecialchars($row['player_name']) ?></td>
                            <td><?= htmlspecialchars($row['user_ID']) ?></td>
                            <td><?= htmlspecialchars($row['note_date']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['note_content'])) ?></td>
                            <td>
                                <?php if ($mode === 'mine' || (int)$row['user_ID'] === $user_id): ?>
                                    <!-- 내가 쓴 노트 -->
                                    <a href="mynotes.php?mode=<?= $mode ?>&edit_id=<?= $row['report_ID'] ?>"
                                       class="btn btn-sm btn-warning mb-1">
                                        수정
                                    </a>
                                    <form method="post"
                                          action="mynotes.php?mode=<?= $mode ?>"
                                          onsubmit="return confirm('이 노트를 삭제하시겠습니까?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="mode" value="<?= $mode ?>">
                                        <input type="hidden" name="report_id" value="<?= $row['report_ID'] ?>">
                                        <input type="hidden" name="author_pw" value="">
                                        <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                                    </form>
                                <?php elseif ($mode === 'team'): ?>
                                    <!-- 팀 모드 + 타인 노트 (비밀번호 필요) -->
                                    <form method="post"
                                          action="mynotes.php?mode=<?= $mode ?>"
                                          onsubmit="return deleteWithPassword(this);">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="mode" value="<?= $mode ?>">
                                        <input type="hidden" name="report_id" value="<?= $row['report_ID'] ?>">
                                        <input type="hidden" name="author_pw" value="">
                                        <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// 타인 노트 삭제 시 비밀번호 입력
function deleteWithPassword(form) {
    if (!confirm('정말 이 노트를 삭제하시겠습니까?')) {
        return false;
    }
    const pw = prompt('삭제하시려면 작성자 계정 비밀번호를 입력하세요.');
    if (pw === null) return false;
    if (pw.trim() === '') {
        alert('비밀번호를 입력해야 삭제할 수 있습니다.');
        return false;
    }
    form.querySelector('input[name="author_pw"]').value = pw;
    return true;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>