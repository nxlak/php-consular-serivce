<?php
// config.php

// Запуск сессии, если она еще не запущена
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";
$username = "root";
$password = ""; // По умолчанию WAMP использует пустой пароль
$dbname = "consular_service";

// Создание соединения
$conn = new mysqli($servername, $username, $password, $dbname);

// Установка кодировки соединения
$conn->set_charset("utf8");

// Проверка соединения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
?>