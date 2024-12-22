<?php
// cancel_application.php
include 'config.php';

// Проверка аутентификации
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'applicant') {
    header("Location: login.php");
    exit();
}

$applicant_id = $_SESSION['user_id'];

if (!isset($_GET['application_id'])) {
    header("Location: dashboard.php");
    exit();
}

$application_id = intval($_GET['application_id']);

// Проверка принадлежности заявки и текущего статуса
$stmt = $conn->prepare("SELECT status FROM applications WHERE id = ? AND applicant_id = ?");
$stmt->bind_param("ii", $application_id, $applicant_id);
$stmt->execute();
$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();

if ($status != 'На рассмотрении') {
    // Нельзя отменить заявку, которая уже обработана
    header("Location: dashboard.php");
    exit();
}

// Обновление статуса заявки
$stmt = $conn->prepare("UPDATE applications SET status = 'Отменено' WHERE id = ?");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$stmt->close();

// Перенаправление с сообщением
header("Location: dashboard.php");
exit();
?>