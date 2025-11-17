<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB 연결
$conn = new mysqli("localhost","team15","team15","team15");
$conn->set_charset("utf8");
include 'db_connect.php';

// 결과 변수 초기화
$result = null;

// 폼 상태 유지를 위한 변수
$position_id_state = isset($_POST['position']) ? $_POST['position'] : '0';
$team_id_state = isset($_POST['team']) ? $_POST['team'] : '1000';

// '분석 실행' 버튼을 눌렀을 때만 실행 (POST 요청)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $position_id = $position_id_state;
    $team_id = $team_id_state;

    // [★★★ 핵심 SQL ★★★]
    $sql = "SELECT
                p.player_name,
                pp.position_Name,

                -- 라운드별 득점 (open_suc + backquick_suc)
                SUM(CASE WHEN g.round_ID IN (1, 2) THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS points_1_2,
                SUM(CASE WHEN g.round_ID IN (3, 4) THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS points_3_4,
                SUM(CASE WHEN g.round_ID IN (5, 6) THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS points_5_6,

                -- 누적 득점
                SUM(CASE WHEN g.round_ID <= 2 THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS cumulative_2,
                SUM(CASE WHEN g.round_ID <= 4 THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS cumulative_4,
                SUM(s.open_suc + s.backquick_suc) AS cumulative_6

            FROM
                Player p
            JOIN
                Player_Position pp ON p.position_ID = pp.position_ID
            JOIN
                Att_Stats s ON p.player_ID = s.player_ID
            JOIN
                Game g ON s.game_ID = g.game_ID
            WHERE
                p.current_team_ID = ?
                AND (? = 0 OR p.position_ID = ?)
            GROUP BY
                p.player_ID, p.player_name, pp.position_Name
            ORDER BY
                cumulative_6 DESC, player_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $team_id, $position_id, $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>라운드별 누적 득점</title>
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
                <a class="navbar-brand" href="dashboard.html">V-League 스카우팅 툴</a>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="dashboard.html">대시보드</a></li>
                        <li class="nav-item"><a class="nav-link" href="player_profile.html">선수 정보</a></li>
                        <li class="nav-item"><a class="nav-link active" href="analysis_value.php">고급 분석</a></li>
                    </ul>
                    <a href="login.html" class="btn btn-outline-light">로그아웃</a>
                </div>
            </div>
        </nav>

        <h2> [Windowing/Aggregate] 라운드별 누적 득점</h2>
        <p>팀과 포지션을 기준으로 선수들의 라운드별 득점 및 누적 득점을 분석합니다.</p>
        <hr>

        <form method="POST" action="" class="card card_body bg-light mb-4 p-4">
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label fw-bold">1. 포지션 선택</label>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_all" value="0"
                            <?php if ($position_id_state == '0') echo 'checked'; ?>>
                        <label class="form-check-label" for="pos_all">모두 함께 보기</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_oh" value="10"
                            <?php if ($position_id_state == '10') echo 'checked'; ?>>
                        <label class="form-check-label" for="pos_oh">아웃사이드 히터(OH)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_op" value="20"
                            <?php if ($position_id_state == '20') echo 'checked'; ?>>
                        <label class="form-check-label" for="pos_op">아포짓 스파이커 (OP)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_mb" value="30"
                            <?php if ($position_id_state == '30') echo 'checked'; ?>>
                        <label class="form-check-label" for="pos_mb">미들 블로커 (MB)</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">2. 팀 선택</label>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team" id="team_1000" value="1000"
                            <?php if ($team_id_state == '1000') echo 'checked'; ?>>
                        <label class="form-check-label" for="team_1000">GS칼텍스</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team" id="team_2000" value="2000"
                            <?php if ($team_id_state == '2000') echo 'checked'; ?>>
                        <label class="form-check-label" for="team_2000">정관장</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team" id="team_3000" value="3000"
                            <?php if ($team_id_state == '3000') echo 'checked'; ?>>
                        <label class="form-check-label" for="team_3000">현대건설</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team" id="team_4000" value="4000"
                            <?php if ($team_id_state == '4000') echo 'checked'; ?>>
                        <label class="form-check-label" for="team_4000">IBK기업은행</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team" id="team_5000" value="5000"
                            <?php if ($team_id_state == '5000') echo 'checked'; ?>>
                        <label class="form-check-label" for="team_5000">한국도로공사</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team" id="team_6000" value="6000"
                            <?php if ($team_id_state == '6000') echo 'checked'; ?>>
                        <label class="form-check-label" for="team_6000">페퍼저축은행</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team" id="team_7000" value="7000"
                            <?php if ($team_id_state == '7000') echo 'checked'; ?>>
                        <label class="form-check-label" for="team_7000">흥국생명</label>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg">분석 실행</button>
            </div>
        </form>

        <h3 class="mt-5"> 분석 결과</h3>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">선수이름</th>
                        <th scope="col">포지션</th>
                        <th scope="col">1-2R 득점</th>
                        <th scope="col">3-4R 득점</th>
                        <th scope="col">5-6R 득점</th>
                        <th scope="col">~2R 누계</th>
                        <th scope="col">~4R 누계</th>
                        <th scope="col">~6R 누계</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['player_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['position_Name']); ?></td>
                                <td><?php echo number_format($row['points_1_2']); ?></td>
                                <td><?php echo number_format($row['points_3_4']); ?></td>
                                <td><?php echo number_format($row['points_5_6']); ?></td>
                                <td><?php echo number_format($row['cumulative_2']); ?></td>
                                <td><?php echo number_format($row['cumulative_4']); ?></td>
                                <td><?php echo number_format($row['cumulative_6']); ?></td>
                            </tr>
                    <?php
                        }
                    } else {
                        if ($_SERVER["REQUEST_METHOD"] == "POST") {
                            echo "<tr><td colspan='8' class='text-center'>조건에 맞는 선수가 없습니다.</td></tr>";
                        } else {
                            echo "<tr><td colspan='8' class='text-center'>조건을 입력하고 '분석 실행' 버튼을 눌러주세요.</td></tr>";
                        }
                    }
                    ?>

                </tbody>
            </table>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
// DB 연결 종료
if ($conn) {
    $conn->close();
}
?>
</body>
</html>
