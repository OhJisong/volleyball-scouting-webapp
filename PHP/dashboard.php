<?php
// dashboard.php
session_start();
require_once 'db_connect.php';   // 여기서 $conn (mysqli 객체) 생성됨

// 1) 로그인 여부 & 팀 정보 세션 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$team_id = (int)$_SESSION['team_id'];

// 2) 팀 이름 조회 (mysqli + prepared statement)
$sql  = "SELECT team_Name FROM team WHERE team_ID = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('팀 쿼리 준비 실패: ' . $conn->error);
}

$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$team_name = $row ? $row['team_Name'] : ($team_id . "번 팀");

$stmt->close();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>메인 대시보드</title>
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
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">대시보드</a></li>
                    <li class="nav-item"><a class="nav-link" href="player_select.php">선수 정보 (CRUD)</a></li> 
                    <li class="nav-item"><a class="nav-link" href="mynotes.php?mode=mine">내 스카우팅 노트</a></li>
                    <li class="nav-item"><a class="nav-link" href="analysis_value.php">고급 분석</a></li>                  
                </ul>
                <a href="logout.php" class="btn btn-outline-light">로그아웃</a>
            </div>
        </div>
    </nav>

    <div class="p-5 mb-4 bg-light rounded-3">
        <h1 class="display-5 fw-bold">감독님, 환영합니다.</h1>

        <p class="fs-4">
            현재 로그인한 사용자 ID:
            <strong><?= htmlspecialchars((string)$user_id, ENT_QUOTES, 'UTF-8') ?></strong><br>
            소속 팀:
            <strong><?= htmlspecialchars($team_name, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </div>

    <h3>4대 고급 분석</h3>
    <div class="row row-cols-1 row-cols-md-2 g-4">

        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[Ranking] 가성비 선수 랭킹</h5>
                    <p class="card-text">'연봉 대비 득점'으로 FA/신인 선수의 순위를 매깁니다.</p>
                    <a href="analysis_value.php" class="btn btn-primary">분석 페이지로 이동 &raquo;</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[Windowing/Aggregate] 라운드별 누적 득점</h5>
                    <p class="card-text">팀과 포지션을 기준으로 선수들의 라운드별 득점 및 누적 득점을 분석합니다.</p>
                    <!--
                    <p class="card-text">'우리 팀'을 상대로 유독 강했던 선수를 찾아냅니다.</p>
                    --> 
                    <a href="score_accumulation.php" class="btn btn-primary">분석 페이지로 이동 &raquo;</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[OLAP] 선수 폼 분석</h5>                  
                    <p class="card-text">'선수 A' vs '선수 B' vs '리그 평균'을 비교합니다.</p>
                    <a href="rollup.php?metric=score" class="btn btn-primary">분석 페이지로 이동 &raquo;</a>

                    <!-- <a href="analysis_compare.php" class="btn btn-primary">분석 페이지로 이동 &raquo;</a> -->
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[CRUD] 스카우팅 노트</h5>
                    <p class="card-text">선수에 대한 평가를 입력/수정/삭제 및 조회합니다.</p>
                    <a href="player_select.php" class="btn btn-primary">팀 및 선수 선택 페이지로 이동 &raquo;</a>
                </div>
            </div>
        </div>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>