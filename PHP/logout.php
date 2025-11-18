<?php
// logout.php
session_start();
session_unset();
session_destroy();

// 로그인 페이지로 보내기
header('Location: login.php');
exit;