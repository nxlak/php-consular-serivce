<?php
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
    $interview_slot_id = intval($_POST['interview_slot']);

    $conn->begin_transaction();

    try {
        // Проверка, что заявка принадлежит текущему заявителю и находится в статусе 'approved' с блокировкой строки
        $stmt = $conn->prepare("SELECT status, assigned_employee_id FROM applications WHERE id = ? AND applicant_id = ? FOR UPDATE");
        $stmt->bind_param("ii", $application_id, $applicant_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($status, $assigned_employee_id);
            $stmt->fetch();
            if ($status != 'approved') { 
                throw new Exception("Заявка не готова для выбора даты собеседования.");
            } else {
                // Получение выбранного слота с блокировкой строки
                $stmt_slot = $conn->prepare("SELECT date, time_slot FROM schedule WHERE id = ? AND employee_id = ? AND is_free = 1 FOR UPDATE");
                $stmt_slot->bind_param("ii", $interview_slot_id, $assigned_employee_id);
                $stmt_slot->execute();
                $stmt_slot->store_result();

                if ($stmt_slot->num_rows == 1) {
                    $stmt_slot->bind_result($interview_date, $interview_time);
                    $stmt_slot->fetch();

                    // Создание записи собеседования
                    $stmt_insert = $conn->prepare("INSERT INTO interviews (location, interview_date, status) VALUES (?, ?, 'Not Conducted')");
                    $location = "Консульское отделение"; 
                    $interview_datetime = $interview_date . ' ' . $interview_time;
                    $stmt_insert->bind_param("ss", $location, $interview_datetime);
                    if ($stmt_insert->execute()) {
                        $interview_id = $stmt_insert->insert_id;
                        $stmt_insert->close();

                        // Обновление заявки
                        $stmt_update = $conn->prepare("UPDATE applications SET interview_id = ?, interview_date = ?, interview_time = ?, status = 'interview_scheduled' WHERE id = ?");
                        $stmt_update->bind_param("issi", $interview_id, $interview_date, $interview_time, $application_id);
                        if ($stmt_update->execute()) {
                            // Обновление расписания сотрудника
                            $stmt_schedule = $conn->prepare("UPDATE schedule SET is_free = 0 WHERE id = ?");
                            $stmt_schedule->bind_param("i", $interview_slot_id);
                            if ($stmt_schedule->execute()) {
                                $success = "Дата и время собеседования успешно выбраны.";
                            } else {
                                throw new Exception("Ошибка при обновлении расписания сотрудника.");
                            }
                            $stmt_schedule->close();
                        } else {
                            throw new Exception("Ошибка при выборе даты и времени собеседования.");
                        }
                        $stmt_update->close();
                    } else {
                        throw new Exception("Ошибка при создании записи собеседования.");
                    }
                } else {
                    throw new Exception("Выбранный слот недоступен.");
                }
                $stmt_slot->close();
            }
        } else {
            throw new Exception("Заявка не найдена.");
        }
        $stmt->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}

// Получение списка заявок заявителя
$stmt = $conn->prepare("
    SELECT a.id, a.submission_date, a.status, e.full_name AS employee_name, a.assigned_employee_id, a.interview_date, a.interview_time
    FROM applications a
    LEFT JOIN employees e ON a.assigned_employee_id = e.id
    WHERE a.applicant_id = ?
");
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
    <!-- Подключение Bootstrap для аккордеона -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .submit-button {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .submit-button:hover {
            background-color: #45a049;
        }

        .accordion-button:not(.collapsed) {
            background-color: #e9ecef;
            color: #000;
        }

        .accordion-body {
            padding: 10px;
        }

        .time-slot {
            margin: 5px 0;
        }

        /* Стили для модального окна */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            text-align: center;
            border-radius: 8px;
        }

        #continueSession {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        #continueSession:hover {
            background-color: #45a049;
        }

        /* Стили для блока "Недавние действия" */
        .recent-actions {
            margin-top: 30px;
            background-color: #f2f2f2;
            padding: 15px;
            border-radius: 8px;
        }
        .recent-actions h3 {
            margin-top: 0;
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
                            <?php
                                // Получение свободных слотов для сотрудника
                                $stmt_slots = $conn->prepare("
                                    SELECT id, date, time_slot 
                                    FROM schedule 
                                    WHERE employee_id = ? AND is_free = 1 AND date >= CURDATE()
                                    ORDER BY date, time_slot
                                ");
                                $stmt_slots->bind_param("i", $row['assigned_employee_id']);
                                $stmt_slots->execute();
                                $result_slots = $stmt_slots->get_result();
                                $slots = $result_slots->fetch_all(MYSQLI_ASSOC);
                                $stmt_slots->close();
                            ?>
                            <?php if (!empty($slots)): ?>
                                <form method="POST" action="dashboard.php">
                                    <input type="hidden" name="application_id" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="accordion" id="accordionExample">
                                        <?php
                                        // Группировка слотов по датам
                                        $grouped_slots = [];
                                        foreach ($slots as $slot) {
                                            $date = $slot['date'];
                                            if (!isset($grouped_slots[$date])) {
                                                $grouped_slots[$date] = [];
                                            }
                                            $grouped_slots[$date][] = $slot;
                                        }
                                        ?>
                                        <?php foreach ($grouped_slots as $date => $slots_for_date): ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading-<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" aria-expanded="true" aria-controls="collapse-<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>
                                                    </button>
                                                </h2>
                                                <div id="collapse-<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" data-bs-parent="#accordionExample">
                                                    <div class="accordion-body">
                                                        <?php foreach ($slots_for_date as $slot): ?>
                                                            <div class="time-slot">
                                                                <label>
                                                                    <input type="radio" name="interview_slot" value="<?= htmlspecialchars($slot['id'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                                    <?= htmlspecialchars($slot['time_slot'], ENT_QUOTES, 'UTF-8') ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="submit" name="select_interview" value="Выбрать">
                                </form>
                            <?php else: ?>
                                <p>Нет доступных слотов для записи.</p>
                            <?php endif; ?>
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

    <!-- Блок с недавними действиями (читаем cookie recent_actions) -->
    <div class="recent-actions">
        <h3>Недавние действия</h3>
        <?php
        if (isset($_COOKIE['recent_actions'])) {
            $recent_actions = json_decode($_COOKIE['recent_actions'], true);
            if (is_array($recent_actions) && count($recent_actions) > 0) {
                echo '<ul>';
                foreach ($recent_actions as $act) {
                    echo '<li>';
                    echo htmlspecialchars($act['action'], ENT_QUOTES, 'UTF-8');
                    if (!empty($act['application_id'])) {
                        echo ' (ID заявки: ' . htmlspecialchars($act['application_id'], ENT_QUOTES, 'UTF-8') . ')';
                    }
                    if (!empty($act['time'])) {
                        echo ' — ' . htmlspecialchars($act['time'], ENT_QUOTES, 'UTF-8');
                    }
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>Нет недавних действий.</p>';
            }
        } else {
            echo '<p>Нет недавних действий.</p>';
        }
        ?>
    </div>
</div>

<!-- Модальное окно для предупреждения об окончании сессии -->
<div id="sessionTimeoutModal" class="modal">
    <div class="modal-content">
        <h2>Внимание!</h2>
        <p>Ваша сессия скоро завершится из-за неактивности. Пожалуйста, нажмите "Продолжить", чтобы оставаться в системе.</p>
        <button id="continueSession">Продолжить</button>
    </div>
</div>

<!-- Подключение Bootstrap JS для аккордеона -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Скрипт для отслеживания активности и отображения модального окна -->
<script>
    let timeoutWarning;
    let timeoutLogout;
    const warningTime = 1.5 * 60 * 1000; // 1.5 минуты 
    const logoutTime = 2 * 60 * 1000; // 2 минуты 

    // Функция для отображения модального окна
    function showTimeoutWarning() {
        const modal = document.getElementById('sessionTimeoutModal');
        modal.style.display = 'block';
    }

    // Функция для сброса таймеров
    function resetTimers() {
        clearTimeout(timeoutWarning);
        clearTimeout(timeoutLogout);
        startTimers();
    }

    // Функция для запуска таймеров
    function startTimers() {
        timeoutWarning = setTimeout(showTimeoutWarning, warningTime);
        timeoutLogout = setTimeout(() => {
            window.location.href = 'logout.php?timeout=1';
        }, logoutTime);
    }

    // Обработчик события для кнопки "Продолжить"
    document.getElementById('continueSession').addEventListener('click', () => {
        const modal = document.getElementById('sessionTimeoutModal');
        modal.style.display = 'none';
        resetTimers();
    });

    // Отслеживание активности пользователя
    document.addEventListener('mousemove', resetTimers);
    document.addEventListener('keypress', resetTimers);
    document.addEventListener('click', resetTimers);

    // Запуск таймеров при загрузке страницы
    start[thinking]

Timers();
</script>
</body>
</html>