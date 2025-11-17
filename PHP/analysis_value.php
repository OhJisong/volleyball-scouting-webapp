<?php

include 'db_connect.php';

$result = null;

$position_id = isset($_POST['position']) ? $_POST['position'] : '10';
$min_salary_display = isset($_POST['min_salary']) ? htmlspecialchars($_POST['min_salary']) : '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $position_id_query = $_POST['position'];
    $min_salary_query = ($_POST['min_salary'] === '') ? 0 : $_POST['min_salary'];


    $sql = "SELECT
                p.player_name,
                pp.position_Name,
                p.salary,
                SUM(s.open_suc + s.backquick_suc) AS total_points,
                (p.salary / NULLIF(SUM(s.open_suc + s.backquick_suc), 0)) AS cost_per_point
            FROM
                Player p
            JOIN
                Att_Stats s ON p.player_ID = s.player_ID
            JOIN
                Player_Position pp ON p.position_ID = pp.position_ID
            WHERE
                p.position_ID = ? AND p.salary >= ?
            GROUP BY
                p.player_ID, p.player_name, pp.position_Name, p.salary
            HAVING
                total_points > 0
            ORDER BY
                cost_per_point ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $position_id_query, $min_salary_query);
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
    <title>가성비 랭킹 분석</title>
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

        <h2> [Ranking] 가성비 선수 랭킹</h2>
        <p>포지션과 최소 연봉을 기준으로 '1득점당 소요되는 연봉(비용)' 순위를 분석합니다.</p>
        <hr>

        <form method="POST" action="" class="card card-body bg-light mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">1. 포지션 선택</label>

                    <!-- --- 3. 라디오 버튼 상태 유지 (checked 속성) --- -->
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_atk" value="10"
                            <?php if ($position_id == '10') echo 'checked'; ?>>
                        <label class="form-check-label" for="pos_atk">아웃사이드 히터(OH)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_def" value="20"
                            <?php if ($position_id == '20') echo 'checked'; ?>>
                        <label class="form-check-label" for="pos_def">아포짓 스파이커 (OP)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_set" value="30"
                            <?php if ($position_id == '30') echo 'checked'; ?>>
                        <label class="form-check-label" for="pos_set">미들 블로커 (MB)</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="min_salary" class="form-label fw-bold">2. 최소 연봉 (백만원)</label>

                    <input type="text" class="form-control" id="min_salary" name="min_salary" placeholder="예: 50 (미입력 시 0원부터)"
                           value="<?php echo $min_salary_display; ?>">
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg">분석 실행</button>
            </div>
        </form>

        <h3 class="mt-5"> 분석 결과</h3>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th scope="col">순위</th>
                    <th scope="col">선수명</th>
                    <th scope="col">포지션</th>
                    <th scope="col">연봉 (백만원)</th>
                    <th scope="col">24-25 총 득점</th>
                    <th scope="col">가성비 (1점당 연봉)</th>
                </tr>
            </thead>

            <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    $rank = 1;
                    while($row = $result->fetch_assoc()) {
                ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($row['player_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['position_Name']); ?></td>
                            <td><?php echo number_format($row['salary']); ?></td>
                            <td><?php echo number_format($row['total_points']); ?></td>
                            <td><?php echo number_format($row['cost_per_point'], 1); ?> 백만원</td> <!-- 단위 '백만원' -> '만원'으로 수정 -->
                        </tr>
                <?php
                    }
                } else {
                    if ($_SERVER["REQUEST_METHOD"] == "POST") {
                        echo "<tr><td colspan='6' class='text-center'>조건에 맞는 선수가 없습니다.</td></tr>";
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>조건을 입력하고 '분석 실행' 버튼을 눌러주세요.</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    $conn->close();
    ?>
</body>
</html>
