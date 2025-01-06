<?php
// config.php

// Запуск сессии, если она еще не запущена
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";
$username   = "root";
$password   = ""; // Для WAMP по умолчанию пустой пароль
$dbname     = "consular_service";

// Создание соединения
$conn = new mysqli($servername, $username, $password, $dbname);

// Установка кодировки соединения
$conn->set_charset("utf8");

// Проверка соединения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

/**
 * ---------------------------------------------
 * Реализация логики таймаута для заявителя
 * ---------------------------------------------
 */

if (isset($_SESSION['user_type'])) {

    // Если это заявитель, тогда ограничиваем время бездействия
    if ($_SESSION['user_type'] === 'applicant') {
        $timeout = 2 * 60; // 2 минуты

        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }

        if ((time() - $_SESSION['last_activity']) > $timeout) {
            // Таймаут истек — завершаем сессию
            session_unset();
            session_destroy();
            // Перенаправляем на страницу логина
            header("Location: login.php?timeout=1");
            exit();
        } else {
            // Если таймаут не истёк — обновляем время последней активности
            $_SESSION['last_activity'] = time();
        }
    }
}
?>