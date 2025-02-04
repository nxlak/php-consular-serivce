<?php
include '../config.php';

// Проверка аутентификации и прав доступа
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'employee') {
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
        exit();
    } else {
        header("Location: ../login.php");
        exit();
    }
}

$employee_id = $_SESSION['user_id'];
$errors = [];
$success = '';

//  "Взять заявку в работу"
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'take_into_work'
) {
    // Готовим ответ в формате JSON
    header('Content-Type: application/json; charset=utf-8');

    // Получаем ID заявки
    $application_id = intval($_POST['application_id'] ?? 0);

    $conn->begin_transaction();

    try {
        $conn->query("LOCK TABLES applications WRITE");

        // Проверка, что заявка в статусе 'new'
        $stmt = $conn->prepare("
            SELECT status 
            FROM applications 
            WHERE id = ? 
              AND status = 'new'
        ");
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $stmt->store_result();

        sleep(10);

        if ($stmt->num_rows === 1) {
            $stmt->close();

            $stmt_update = $conn->prepare("
                UPDATE applications 
                SET assigned_employee_id = ?, status = 'in_progress' 
                WHERE id = ?
            ");
            $stmt_update->bind_param("ii", $employee_id, $application_id);
            if ($stmt_update->execute()) {
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => "Вы успешно взяли заявку #{$application_id} в работу."
                ]);
                exit();
            } else {
                throw new Exception("Ошибка при назначении заявки.");
            }
        } else {
            throw new Exception("Заявка уже взята в работу или не существует.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
        exit();
    } finally {
        $conn->query("UNLOCK TABLES");
    }
}

// Обработка одобрения заявки
if (isset($_POST['approve_application'])) {
    $application_id = intval($_POST['application_id']);

    $conn->begin_transaction();
    try {
        // Проверка, что заявка в статусе 'in_progress' и назначена этому сотруднику
        $stmt = $conn->prepare("
            SELECT status 
            FROM applications 
            WHERE id = ? 
              AND assigned_employee_id = ? 
              AND status = 'in_progress' 
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $application_id, $employee_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->close();

            // Обновляем статус на 'approved'
            $stmt_update = $conn->prepare("
                UPDATE applications 
                SET status = 'approved' 
                WHERE id = ?
            ");
            $stmt_update->bind_param("i", $application_id);
            if ($stmt_update->execute()) {
                $success = "Заявка #$application_id успешно одобрена и ожидает назначения собеседования.";
            } else {
                throw new Exception("Ошибка при одобрении заявки.");
            }
            $stmt_update->close();
        } else {
            throw new Exception("Заявка не найдена или не может быть одобрена.");
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}

// Обработка отклонения заявки
if (isset($_POST['reject_application'])) {
    $application_id = intval($_POST['application_id']);

    $conn->begin_transaction();
    try {
        // Проверка, что заявка в статусе 'in_progress' и назначена этому сотруднику
        $stmt = $conn->prepare("
            SELECT status 
            FROM applications 
            WHERE id = ? 
              AND assigned_employee_id = ? 
              AND status = 'in_progress' 
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $application_id, $employee_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->close();

            // Обновляем статус на 'denied'
            $stmt_update = $conn->prepare("
                UPDATE applications 
                SET status = 'denied' 
                WHERE id = ?
            ");
            $stmt_update->bind_param("i", $application_id);
            if ($stmt_update->execute()) {
                $success = "Заявка #$application_id успешно отклонена.";
            } else {
                throw new Exception("Ошибка при отклонении заявки.");
            }
            $stmt_update->close();
        } else {
            throw new Exception("Заявка не найдена или не может быть отклонена.");
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}

// Получение списка заявок на обработку (статус 'new')
$stmt = $conn->prepare("
    SELECT a.id, a.submission_date, ap.full_name, a.status
    FROM applications a
    JOIN applicants ap ON a.applicant_id = ap.id
    WHERE a.status = 'new'
");
$stmt->execute();
$result_new = $stmt->get_result();
$stmt->close();

// Получение списка заявок в работе для текущего сотрудника (статусы 'in_progress' и 'approved')
$stmt = $conn->prepare("
    SELECT a.id, a.submission_date, ap.full_name, a.status
    FROM applications a
    JOIN applicants ap ON a.applicant_id = ap.id
    WHERE (a.status = 'in_progress' OR a.status = 'approved')
      AND a.assigned_employee_id = ?
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result_in_progress = $stmt->get_result();
$stmt->close();

// Получение списка заявок, ожидающих результатов собеседования (статус 'interview_scheduled')
$stmt = $conn->prepare("
    SELECT a.id, a.submission_date, ap.full_name, a.status, a.interview_date, a.interview_time
    FROM applications a
    JOIN applicants ap ON a.applicant_id = ap.id
    WHERE a.status = 'interview_scheduled'
      AND a.assigned_employee_id = ?
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result_interview_results = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель сотрудника - Консульская служба</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<header>
    <h1>Консульская служба - Панель сотрудника</h1>
    <nav>
        <a href="employee_dashboard.php">Главная</a>
        <a href="../logout.php">Выйти</a>
    </nav>
</header>

<div class="container">
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

    <h2>Список заявок на обработку</h2>

    <?php if ($result_new->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Дата подачи</th>
                <th>Заявитель</th>
                <th>Действие</th>
            </tr>
            <?php while ($row = $result_new->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['submission_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <!-- Кнопка для асинхронного "взятия заявки в работу" -->
                        <button 
                            class="take-into-work-btn"
                            data-id="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                            Взять заявку в работу
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>Нет заявок на обработку.</p>
    <?php endif; ?>

    <h2>Заявки в работе</h2>

    <?php if ($result_in_progress->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Дата подачи</th>
                <th>Заявитель</th>
                <th>Статус</th>
                <th>Документы</th>
                <th>Действие</th>
            </tr>
            <?php while ($row = $result_in_progress->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['submission_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php
                            switch ($row['status']) {
                                case 'in_progress':
                                    echo "В работе";
                                    break;
                                case 'approved':
                                    echo "Ожидает назначения собеседования";
                                    break;
                                default:
                                    echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8');
                            }
                        ?>
                    </td>
                    <td>
                        <a href="view_documents.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">Просмотреть</a>
                    </td>
                    <td>
                        <?php if ($row['status'] == 'in_progress'): ?>
                            <form method="POST" action="employee_dashboard.php" style="display:inline;">
                                <input type="hidden" name="application_id" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="submit" name="approve_application" value="Одобрить">
                            </form>
                            <form method="POST" action="employee_dashboard.php" style="display:inline;">
                                <input type="hidden" name="application_id" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="submit" name="reject_application" value="Отклонить">
                            </form>
                        <?php elseif ($row['status'] == 'approved'): ?>
                            <span>Ожидает назначения собеседования</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>У вас нет заявок в работе.</p>
    <?php endif; ?>

    <h2>Результаты собеседований</h2>

    <?php if ($result_interview_results->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Дата подачи</th>
                <th>Заявитель</th>
                <th>Документы</th>
                <th>Дата собеседования</th>
                <th>Время собеседования</th>
                <th>Действие</th>
            </tr>
            <?php while ($row = $result_interview_results->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['submission_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="view_documents.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">Просмотреть</a>
                    </td>
                    <td><?= htmlspecialchars($row['interview_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['interview_time'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="process_interview.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">Внести результаты</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>Нет результатов собеседований для обработки.</p>
    <?php endif; ?>
</div>

<!-- скрипт для асинхронной отправки данных -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Находим все кнопки "Взять заявку в работу"
    const takeButtons = document.querySelectorAll('.take-into-work-btn');
    takeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const applicationId = btn.getAttribute('data-id');
            
            // Формируем body для POST-запроса
            const formData = new URLSearchParams();
            formData.append('action', 'take_into_work');
            formData.append('application_id', applicationId);

            fetch('employee_dashboard.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Произошла ошибка при отправке запроса.');
            });
        });
    });
});
</script>
</body>
</html>