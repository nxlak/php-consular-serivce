<?php
// view_documents.php
include '../config.php';

// Проверка аутентификации и прав доступа
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'employee') {
    header("Location: ../login.php");
    exit();
}

$employee_id = $_SESSION['user_id'];
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$documents = [];

// Начало транзакции
$conn->begin_transaction();

try {
    // Проверка, что заявка назначена текущему сотруднику и существует с блокировкой строки
    $stmt = $conn->prepare("SELECT status FROM applications WHERE id = ? AND assigned_employee_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $application_id, $employee_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows != 1) {
        throw new Exception("Заявка не найдена или вам не назначена.");
    } else {
        // Получение документов
        $stmt->close();
        $stmt_docs = $conn->prepare("SELECT id, document_type, expiration_date FROM documents WHERE application_id = ?");
        $stmt_docs->bind_param("i", $application_id);
        $stmt_docs->execute();
        $result_docs = $stmt_docs->get_result();

        while ($doc = $result_docs->fetch_assoc()) {
            $documents[] = $doc;
        }
        $stmt_docs->close();
    }

    // Коммит транзакции
    $conn->commit();
} catch (Exception $e) {
    // Откат транзакции в случае ошибки
    $conn->rollback();
    $errors[] = $e->getMessage();
}

// Обработка скачивания документа
if (isset($_GET['download'])) {
    $document_id = intval($_GET['download']);

    // Проверка, что документ принадлежит заявке, назначенной этому сотруднику
    $stmt = $conn->prepare("
        SELECT d.document_scan, d.document_type
        FROM documents d
        JOIN applications a ON d.application_id = a.id
        WHERE d.id = ? AND a.id = ? AND a.assigned_employee_id = ?
    ");
    $stmt->bind_param("iii", $document_id, $application_id, $employee_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($document_scan, $document_type);
        $stmt->fetch();

        // Определение MIME-типа
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($document_scan);

        // Установка заголовков
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($document_type) . '.pdf"');
        echo $document_scan;
        exit();
    } else {
        $errors[] = "Документ не найден или доступ к нему запрещён.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Документы заявки #<?= htmlspecialchars($application_id, ENT_QUOTES, 'UTF-8') ?> - Консульская служба</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<header>
    <h1>Консульская служба - Документы заявки</h1>
    <nav>
        <a href="employee_dashboard.php">Главная</a>
        <a href="../logout.php">Выйти</a>
    </nav>
</header>

<div class="container">
    <h2>Документы заявки #<?= htmlspecialchars($application_id, ENT_QUOTES, 'UTF-8') ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($documents)): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Тип документа</th>
                <th>Срок действия</th>
                <th>Действие</th>
            </tr>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?= htmlspecialchars($doc['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($doc['document_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($doc['expiration_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="view_documents.php?id=<?= htmlspecialchars($application_id, ENT_QUOTES, 'UTF-8') ?>&download=<?= htmlspecialchars($doc['id'], ENT_QUOTES, 'UTF-8') ?>">Скачать</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Документы не найдены.</p>
    <?php endif; ?>
</div>
</body>
</html>
