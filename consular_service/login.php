<?php
// login.php
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
</body>
</html>
