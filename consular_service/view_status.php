<?php
// view_status.php
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
    // Проверка принадлежности заявки текущему пользователю с блокировкой строки
    $stmt = $conn->prepare("SELECT visa_category, status, submission_date FROM applications WHERE id = ? AND applicant_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $application_id, $applicant_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows != 1) {
        throw new Exception("Заявка не найдена или вам не назначена.");
    }
    $stmt->bind_result($visa_category, $status, $submission_date);
    $stmt->fetch();
    $stmt->close();

    // Получение документов
    $stmt = $conn->prepare("SELECT document_type, expiration_date FROM documents WHERE application_id = ?");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Получение даты и времени собеседования
    $stmt = $conn->prepare("SELECT interviews.id, interviews.location, interviews.status, interviews.interview_date FROM interviews JOIN applications ON applications.interview_id = interviews.id WHERE applications.id = ?");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $stmt->store_result();
    $interview = null;
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($interview_id, $location, $interview_status, $interview_date);
        $stmt->fetch();
        $interview = [
            'id' => $interview_id,
            'location' => $location,
            'status' => $interview_status,
            'interview_date' => $interview_date
        ];
    }
    $stmt->close();

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
    <title>Статус заявки - Консульская служба</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header>
        <h1>Консульская служба</h1>
        <nav>
            <a href="dashboard.php">Личный кабинет</a>
            <a href="logout.php">Выйти</a>
        </nav>
    </header>

    <div class="container">
        <h2>Информация о заявке #<?= $application_id ?></h2>
        <p><strong>Категория визы:</strong> <?= htmlspecialchars($visa_category) ?></p>
        <p><strong>Статус:</strong> <?= htmlspecialchars($status) ?></p>
        <p><strong>Дата подачи:</strong> <?= htmlspecialchars($submission_date) ?></p>

        <h3>Документы</h3>
        <table>
            <tr>
                <th>Тип документа</th>
                <th>Срок действия</th>
            </tr>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?= htmlspecialchars($doc['document_type']) ?></td>
                    <td><?= htmlspecialchars($doc['expiration_date']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($interview): ?>
            <h3>Собеседование</h3>
            <p><strong>Местоположение:</strong> <?= htmlspecialchars($interview['location']) ?></p>
            <p><strong>Дата и время:</strong> <?= htmlspecialchars($interview['interview_date']) ?></p>
            <p><strong>Статус:</strong> <?= htmlspecialchars($interview['status']) ?></p>
        <?php else: ?>
            <p>Собеседование еще не назначено.</p>
        <?php endif; ?>

        <?php if ($status == 'Отменено'): ?>
            <p>Заявка была отменена.</p>
        <?php endif; ?>
    </div>
</body>
</html>