<?php

$servername = "localhost";  
$username = "team15";       
$password = "team15";       
$dbname = "team15";         

// === DB 연결 시도 ===
$conn = new mysqli($servername, $username, $password, $dbname);

// === 연결 성공/실패 확인 ===
if ($conn->connect_error) {
    die("DB 연결 실패: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");