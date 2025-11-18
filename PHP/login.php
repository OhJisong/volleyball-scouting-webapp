<?php
session_start();
require_once 'db_connect.php';  // 여기서 $conn 사용

// 이미 로그인 돼 있으면 바로 대시보드로
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// 로그인 폼 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input_id = trim($_POST['user_id'] ?? '');
    $input_pw = trim($_POST['user_pw'] ?? '');

    if ($input_id === '' || $input_pw === '') {
        $error = '아이디와 비밀번호를 모두 입력해주세요.';
    } else {
        // 아이디가 숫자라고 가정할 때만 int 변환
        $user_id_int = (int) $input_id;

        // ✅ Prepared Statement 로 SQL Injection 방지
        $sql = "
            SELECT U.user_ID, U.team_ID, T.team_Name
            FROM user AS U
            LEFT JOIN team AS T ON U.team_ID = T.team_ID
            WHERE U.user_ID = ? 
              AND U.user_PW = ?
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die('쿼리 준비 실패: ' . $conn->error);
        }

        // user_ID(INT), user_PW(VARCHAR) → (i: int, s: string)
        $stmt->bind_param('is', $user_id_int, $input_pw);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // 로그인 성공 → 세션에 저장
            $_SESSION['user_id']   = $row['user_ID'];
            $_SESSION['team_id']   = $row['team_ID'];
            $_SESSION['team_name'] = $row['team_Name'];

            header('Location: dashboard.php');
            exit;
        } else {
            $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
        }

        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>V-League 스카우팅 툴 로그인</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body {
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
        }
        .form-signin { max-width: 330px; padding: 15px; }
    </style>
</head>
<body class="text-center">

<main class="form-signin w-100 m-auto">
    <form action="login.php" method="post">
        <h1 class="h3 mb-3 fw-normal">V-League<br>스카우팅 툴</h1>
        <p>감독/스카우터 전용</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="form-floating">
            <input
                type="text"
                class="form-control"
                id="floatingInput"
                name="user_id"
                placeholder="User ID"
            >
            <label for="floatingInput">User ID</label>
        </div>

        <div class="form-floating mt-2">
            <input
                type="password"
                class="form-control"
                id="floatingPassword"
                name="user_pw"
                placeholder="Password"
            >
            <label for="floatingPassword">Password</label>
        </div>

        <button class="w-100 btn btn-lg btn-primary mt-3" type="submit">로그인</button>
        <p class="mt-5 mb-3 text-muted">&copy; Team 15 Project</p>
    </form>
</main>

</body>
</html>