<?php
// employee/process_interview.php
include '../config.php';

// Проверка аутентификации и прав доступа
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'employee') {
    header("Location: ../login.php");
    exit();
}

$employee_id = $_SESSION['user_id'];
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$success = '';

// Начало транзакции
$conn->begin_transaction();

try {
    // Получение данных заявки и связанного собеседования с блокировкой строки
    $stmt = $conn->prepare("
        SELECT a.id, a.submission_date, a.status, ap.full_name, a.interview_date, a.interview_time, i.id AS interview_id, i.status AS interview_status
        FROM applications a
        JOIN applicants ap ON a.applicant_id = ap.id
        LEFT JOIN interviews i ON a.interview_id = i.id
        WHERE a.id = ? AND a.assigned_employee_id = ? FOR UPDATE
    ");
    $stmt->bind_param("ii", $application_id, $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows != 1) {
        throw new Exception("Заявка не найдена или вам не назначена.");
    } else {
        $application = $result->fetch_assoc();

        // Проверка, что заявка находится в статусе 'interview_scheduled'
        if ($application['status'] != 'interview_scheduled') {
            throw new Exception("Заявка не ожидает результатов собеседования.");
        }
    }
    $stmt->close();

    // Обработка формы внесения результатов собеседования
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_results'])) {
        $interview_result = $_POST['interview_result']; // 'visa_issued' или 'visa_denied'

        if (!in_array($interview_result, ['visa_issued', 'visa_denied'])) {
            throw new Exception("Неверный результат собеседования.");
        }

        // Обновление статуса заявки
        $stmt_update = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $interview_result, $application_id);
        if ($stmt_update->execute()) {
            $success = "Результаты собеседования успешно внесены.";
        } else {
            throw new Exception("Ошибка при обновлении результатов собеседования.");
        }
        $stmt_update->close();
    }

    // Коммит транзакции
    $conn->commit();
} catch (Exception $e) {
    // Откат транзакции в случае ошибки
    $conn->rollback();
    $errors[] = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Внесение результатов собеседования - Консульская служба</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<header>
    <h1>Консульская служба - Внесение результатов собеседования</h1>
    <nav>
        <a href="employee_dashboard.php">Главная</a>
        <a href="../logout.php">Выйти</a>
    </nav>
</header>

<div class="container">
    <h2>Заявка #<?= htmlspecialchars($application_id, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($application['full_name'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p><strong>Категория визы:</strong> <?= htmlspecialchars($application['visa_category'] ?? 'Не указана', ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Дата подачи:</strong> <?= htmlspecialchars($application['submission_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Дата собеседования:</strong> <?= htmlspecialchars($application['interview_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Время собеседования:</strong> <?= htmlspecialchars($application['interview_time'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Статус:</strong> <?= htmlspecialchars($application['status'] == 'interview_scheduled' ? 'Ожидает результатов собеседования' : htmlspecialchars($application['status'], ENT_QUOTES, 'UTF-8')) ?></p>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <p><?= htmlspecialchars($success ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endif; ?>

    <?php if ($application['status'] == 'interview_scheduled'): ?>
        <form method="POST" action="process_interview.php?id=<?= htmlspecialchars($application_id, ENT_QUOTES, 'UTF-8') ?>">
            <label for="interview_result">Результат собеседования:</label>
            <select name="interview_result" required>
                <option value="">--Выберите--</option>
                <option value="visa_issued">Одобрить выдачу визы</option>
                <option value="visa_denied">Отклонить выдачу визы</option>
            </select>
            <input type="submit" name="submit_results" value="Сохранить результаты">
        </form>
    <?php else: ?>
        <p>Результаты собеседования уже внесены.</p>
    <?php endif; ?>
</div>
</body>
</html>
