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

// Начало транзакции
$conn->begin_transaction();

try {
    // Проверка принадлежности заявки и текущего статуса с блокировкой строки
    $stmt = $conn->prepare("SELECT status FROM applications WHERE id = ? AND applicant_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $application_id, $applicant_id);
    $stmt->execute();
    $stmt->bind_result($status);
    $stmt->fetch();
    $stmt->close();

    if ($status != 'На рассмотрении') {
        // Нельзя отменить заявку, которая уже обработана
        throw new Exception("Заявка не может быть отменена.");
    }

    // Обновление статуса заявки
    $stmt = $conn->prepare("UPDATE applications SET status = 'Отменено' WHERE id = ?");
    $stmt->bind_param("i", $application_id);
    if ($stmt->execute()) {
        $success = "Заявка успешно отменена.";
    } else {
        throw new Exception("Ошибка при отмене заявки.");
    }
    $stmt->close();

    // Коммит транзакции
    $conn->commit();
} catch (Exception $e) {
    // Откат транзакции в случае ошибки
    $conn->rollback();
    $errors[] = $e->getMessage();
}

// Перенаправление с сообщением
header("Location: dashboard.php");
exit();
?>