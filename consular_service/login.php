<?php
// login.php
session_start();

// Подключение к базе данных
include 'config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $errors[] = "Все поля обязательны для заполнения.";
    } else {
        // Проверяем сначала в таблице сотрудников
        $stmt = $conn->prepare("SELECT id, password FROM employees WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $stored_password);
            $stmt->fetch();
            if ($password === $stored_password) { // НЕБЕЗОПАСНО (рекомендуется использовать password_verify)
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = 'employee';
                $_SESSION['last_activity'] = time(); // Установка времени последней активности
                header("Location: employee/employee_dashboard.php");
                exit();
            } else {
                $errors[] = "Неверный пароль.";
            }
        } else {
            // Если не найден в таблице сотрудников, проверяем в таблице заявителей
            $stmt = $conn->prepare("SELECT id, password FROM applicants WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $stored_password);
                $stmt->fetch();
                if ($password === $stored_password) { // НЕБЕЗОПАСНО (рекомендуется использовать password_verify)
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = 'applicant';
                    $_SESSION['last_activity'] = time(); // Установка времени последней активности
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $errors[] = "Неверный пароль.";
                }
            } else {
                $errors[] = "Пользователь не найден.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход - Консульская служба</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
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
    </style>
</head>
<body>
    <header>
        <h1>Консульская служба</h1>
        <nav>
            <a href="index.php">Главная</a>
            <a href="register.php">Регистрация</a>
        </nav>
    </header>

    <div class="container">
        <h2>Вход</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <label for="username">Логин:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>

            <input type="submit" value="Войти">
        </form>
    </div>

    <!-- Модальное окно для предупреждения об окончании сессии -->
    <div id="sessionTimeoutModal" class="modal">
        <div class="modal-content">
            <h2>Внимание!</h2>
            <p>Ваша сессия скоро завершится из-за неактивности. Пожалуйста, нажмите "Продолжить", чтобы оставаться в системе.</p>
            <button id="continueSession">Продолжить</button>
        </div>
    </div>

    <!-- Скрипт для отслеживания активности и отображения модального окна -->
    <script>
        let timeoutWarning;
        let timeoutLogout;
        const warningTime = 1.5 * 60 * 1000; // 1.5 минуты (в миллисекундах)
        const logoutTime = 2 * 60 * 1000; // 2 минуты (в миллисекундах)

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
        startTimers();
    </script>
</body>
</html>