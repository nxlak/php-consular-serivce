<?php
// employee/process_application.php
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

// Получение данных заявки
$stmt = $conn->prepare("SELECT a.id, a.submission_date, a.status, ap.full_name, a.visa_category
                        FROM applications a
                        JOIN applicants ap ON a.applicant_id = ap.id
                        WHERE a.id = ? AND a.assigned_employee_id = ?");
$stmt->bind_param("ii", $application_id, $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    $errors[] = "Заявка не найдена или вам не назначена.";
} else {
    $application = $result->fetch_assoc();
}

$stmt->close();

// Обработка одобрения заявки
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve'])) {
    // Проверка, что заявка в статусе 'in_progress'
    if ($application['status'] != 'in_progress') {
        $errors[] = "Заявка не находится в процессе обработки.";
    } else {
        // Обновление статуса заявки на 'approved' (Ожидает назначения собеседования)
        $stmt_update = $conn->prepare("UPDATE applications SET status = 'approved' WHERE id = ?");
        $stmt_update->bind_param("i", $application_id);
        if ($stmt_update->execute()) {
            $success = "Заявка успешно одобрена и ожидает назначения собеседования.";
        } else {
            $errors[] = "Ошибка при одобрении заявки.";
        }
        $stmt_update->close();
    }
}

// Обработка отклонения заявки
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject'])) {
    // Проверка, что заявка в статусе 'in_progress'
    if ($application['status'] != 'in_progress') {
        $errors[] = "Заявка не находится в процессе обработки.";
    } else {
        // Обновление статуса заявки на 'denied'
        $stmt_update = $conn->prepare("UPDATE applications SET status = 'denied' WHERE id = ?");
        $stmt_update->bind_param("i", $application_id);
        if ($stmt_update->execute()) {
            $success = "Заявка успешно отклонена.";
        } else {
            $errors[] = "Ошибка при отклонении заявки.";
        }
        $stmt_update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Обработка заявки - Консульская служба</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<header>
    <h1>Консульская служба - Обработка заявки</h1>
    <nav>
        <a href="employee_dashboard.php">Главная</a>
        <a href="../logout.php">Выйти</a>
    </nav>
</header>

<div class="container">
    <h2>Заявка #<?= htmlspecialchars($application_id) ?> - <?= htmlspecialchars($application['full_name']) ?></h2>
    <p><strong>Категория визы:</strong> <?= htmlspecialchars($application['visa_category']) ?></p>
    <p><strong>Дата подачи:</strong> <?= htmlspecialchars($application['submission_date']) ?></p>
    <p><strong>Статус:</strong> <?= htmlspecialchars($application['status']) ?></p>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <p><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($application) && $application['status'] == 'in_progress'): ?>
        <form method="POST" action="process_application.php?id=<?= htmlspecialchars($application_id) ?>">
            <input type="submit" name="approve" value="Одобрить заявку">
            <input type="submit" name="reject" value="Отклонить заявку">
        </form>
    <?php else: ?>
        <p>Дополнительные действия с этой заявкой недоступны.</p>
    <?php endif; ?>
</div>
</body>
</html>