<?php
// login.php
include 'config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type']; // 'applicant' или 'employee'

    if (empty($username) || empty($password) || empty($user_type)) {
        $errors[] = "Все поля обязательны для заполнения.";
    } else {
        if ($user_type == 'applicant') {
            // Поиск в таблице applicants
            $stmt = $conn->prepare("SELECT id, password FROM applicants WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $stored_password);
                $stmt->fetch();
                // Рекомендация: Используйте password_verify() для проверки хешированных паролей
                // Пример:
                // if (password_verify($password, $stored_password)) {
                if ($password === $stored_password) { // НЕБЕЗОПАСНО
                    // Успешный вход
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = 'applicant';
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $errors[] = "Неверный пароль.";
                }
            } else {
                $errors[] = "Пользователь не найден.";
            }
            $stmt->close();
        } elseif ($user_type == 'employee') {
            // Поиск в таблице employees
            $stmt = $conn->prepare("SELECT id, password FROM employees WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $stored_password);
                $stmt->fetch();
                // Рекомендация: Используйте password_verify() для проверки хешированных паролей
                // Пример:
                // if (password_verify($password, $stored_password)) {
                if ($password === $stored_password) { // НЕБЕЗОПАСНО
                    // Успешный вход
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = 'employee';
                    header("Location: employee/employee_dashboard.php");
                    exit();
                } else {
                    $errors[] = "Неверный пароль.";
                }
            } else {
                $errors[] = "Сотрудник не найден.";
            }
            $stmt->close();
        } else {
            $errors[] = "Неверный тип пользователя.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход - Консульская служба</title>
    <link rel="stylesheet" href="css/styles.css">
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

            <label for="user_type">Тип пользователя:</label>
            <select id="user_type" name="user_type" required>
                <option value="">--Выберите--</option>
                <option value="applicant">Заявитель</option>
                <option value="employee">Сотрудник</option>
            </select>

            <input type="submit" value="Войти">
        </form>
    </div>
</body>
</html>