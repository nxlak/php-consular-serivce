<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";
$username   = "root";
$password   = ""; 
$dbname     = "consular_service";

$conn = new mysqli($servername, $username, $password, $dbname);

$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

if (isset($_SESSION['user_type'])) {

    if ($_SESSION['user_type'] === 'applicant') {
        $timeout = 2 * 60; // 2 минуты

        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }

        if ((time() - $_SESSION['last_activity']) > $timeout) {
            session_unset();
            session_destroy();

            header("Location: login.php?timeout=1");
            exit();
        } else {

            $_SESSION['last_activity'] = time();
        }
    }
}
?>