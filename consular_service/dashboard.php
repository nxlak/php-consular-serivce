<?php
// dashboard.php
include 'config.php';

// Проверка аутентификации
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'applicant') {
    header("Location: login.php");
    exit();
}

$applicant_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Обработка выбора даты и времени собеседования
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_interview'])) {
    $application_id = intval($_POST['application_id']);
    $interview_date = $_POST['interview_date'];
    $interview_time = $_POST['interview_time'];

    // Валидация
    if (empty($interview_date) || empty($interview_time)) {
        $errors[] = "Дата и время собеседования обязательны.";
    }

    // Проверка, что заявка принадлежит текущему заявителю и находится в статусе 'approved'
    $stmt = $conn->prepare("SELECT status FROM applications WHERE id = ? AND applicant_id = ?");
    $stmt->bind_param("ii", $application_id, $applicant_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($status);
        $stmt->fetch();
        if ($status != 'approved') { // Статус 'approved' означает "Ожидает назначения собеседования"
            $errors[] = "Заявка не готова для выбора даты собеседования.";
        }
    } else {
        $errors[] = "Заявка не найдена.";
    }
    $stmt->close();

    if (empty($errors)) {
        // Создание записи собеседования
        $stmt_insert = $conn->prepare("INSERT INTO interviews (location, interview_date, status) VALUES (?, ?, 'Not Conducted')");
        $location = "Консульское отделение"; // Можно сделать выбор места из списка, если необходимо
        $interview_datetime = $interview_date . ' ' . $interview_time;
        $stmt_insert->bind_param("ss", $location, $interview_datetime);
        if ($stmt_insert->execute()) {
            $interview_id = $stmt_insert->insert_id;
            $stmt_insert->close();

            // Обновление заявки с ID собеседования, датой, временем и изменением статуса
            $stmt_update = $conn->prepare("UPDATE applications SET interview_id = ?, interview_date = ?, interview_time = ?, status = 'interview_scheduled' WHERE id = ?");
            $stmt_update->bind_param("issi", $interview_id, $interview_date, $interview_time, $application_id);
            if ($stmt_update->execute()) {
                $success = "Дата и время собеседования успешно выбраны.";
            } else {
                $errors[] = "Ошибка при выборе даты и времени собеседования.";
            }
            $stmt_update->close();
        } else {
            $errors[] = "Ошибка при создании записи собеседования.";
            $stmt_insert->close();
        }
    }
}

// Получение списка заявок заявителя
$stmt = $conn->prepare("SELECT a.id, a.submission_date, a.status, e.full_name AS employee_name, a.interview_date, a.interview_time
                        FROM applications a
                        LEFT JOIN employees e ON a.assigned_employee_id = e.id
                        WHERE a.applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$result_applications = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель заявителя - Консульская служба</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Дополнительные стили для кнопки */
        .submit-button {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4CAF50; /* Зелёный цвет */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .submit-button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
<header>
    <h1>Консульская служба - Панель заявителя</h1>
    <nav>
        <a href="dashboard.php">Главная</a>
        <a href="submit_application.php">Подать заявку</a>
        <a href="logout.php">Выйти</a>
    </nav>
</header>

<div class="container">
    <h2>Мои заявки</h2>

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

    <?php if ($result_applications->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Дата подачи</th>
                <th>Статус</th>
                <th>Сотрудник</th>
                <th>Дата собеседования</th>
                <th>Время собеседования</th>
                <th>Действие</th>
            </tr>
            <?php while ($row = $result_applications->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['submission_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php
                            switch ($row['status']) {
                                case 'new':
                                    echo "Новая";
                                    break;
                                case 'in_progress':
                                    echo "В работе";
                                    break;
                                case 'approved':
                                    echo "Ожидает назначения собеседования";
                                    break;
                                case 'denied':
                                    echo "Отклонена";
                                    break;
                                case 'interview_scheduled':
                                    echo "Собеседование назначено";
                                    break;
                                case 'visa_issued':
                                    echo "Виза выдана";
                                    break;
                                case 'visa_denied':
                                    echo "Виза отклонена";
                                    break;
                                default:
                                    echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8');
                            }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['employee_name'] ?? 'Не назначен', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['interview_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['interview_time'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($row['status'] == 'approved' && empty($row['interview_date']) && empty($row['interview_time'])): ?>
                            <form method="POST" action="dashboard.php">
                                <input type="hidden" name="application_id" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <label for="interview_date">Дата:</label>
                                <input type="date" name="interview_date" required>
                                <label for="interview_time">Время:</label>
                                <input type="time" name="interview_time" required>
                                <input type="submit" name="select_interview" value="Выбрать">
                            </form>
                        <?php elseif ($row['status'] == 'interview_scheduled' && !empty($row['interview_date']) && !empty($row['interview_time'])): ?>
                            <span>Назначено на <?= htmlspecialchars($row['interview_date'], ENT_QUOTES, 'UTF-8') ?> в <?= htmlspecialchars($row['interview_time'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <span>Нет действий</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>У вас нет заявок.</p>
    <?php endif; ?>

    <!-- Кнопка для подачи новой заявки -->
    <a href="submit_application.php"><button type="button" class="submit-button">Подать новую заявку</button></a>
</div>
</body>
</html>