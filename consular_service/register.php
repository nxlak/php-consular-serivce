<?php
// register.php
include 'config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $citizenship = trim($_POST['citizenship']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone_number = trim($_POST['phone_number']);
    $email = trim($_POST['email']);

    // Валидация
    if (empty($full_name) || empty($date_of_birth) || empty($citizenship) || empty($username) || empty($password) || empty($confirm_password) || empty($phone_number) || empty($email)) {
        $errors[] = "Все поля обязательны для заполнения.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Пароли не совпадают.";
    }

    // Проверка уникальности username
    $stmt = $conn->prepare("SELECT id FROM applicants WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Имя пользователя уже используется.";
    }
    $stmt->close();

    // Проверка уникальности email
    $stmt = $conn->prepare("SELECT id FROM contact_info WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Электронная почта уже используется.";
    }
    $stmt->close();

    if (empty($errors)) {
        // Рекомендация: Используйте password_hash() для хеширования паролей
        // Пример:
        // $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Вставка пароля напрямую (небезопасно)
        $stmt = $conn->prepare("INSERT INTO applicants (full_name, date_of_birth, citizenship, username, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $date_of_birth, $citizenship, $username, $password);
        if ($stmt->execute()) {
            $applicant_id = $stmt->insert_id;

            // Вставка в таблицу contact_info
            $stmt2 = $conn->prepare("INSERT INTO contact_info (applicant_id, phone_number, email) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $applicant_id, $phone_number, $email);
            if ($stmt2->execute()) {
                $_SESSION['user_id'] = $applicant_id;
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = 'applicant';
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Ошибка при сохранении контактной информации.";
            }
            $stmt2->close();
        } else {
            $errors[] = "Ошибка при регистрации.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация - Консульская служба</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header>
        <h1>Консульская служба</h1>
        <nav>
            <a href="index.php">Главная</a>
            <a href="login.php">Вход</a>
        </nav>
    </header>

    <div class="container">
        <h2>Регистрация</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <label for="full_name">ФИО:</label>
            <input type="text" id="full_name" name="full_name" required>

            <label for="date_of_birth">Дата рождения:</label>
            <input type="date" id="date_of_birth" name="date_of_birth" required>

            <label for="citizenship">Гражданство:</label>
            <input type="text" id="citizenship" name="citizenship" required>

            <label for="username">Логин:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Подтвердите пароль:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <label for="phone_number">Номер телефона:</label>
            <input type="text" id="phone_number" name="phone_number" required>

            <label for="email">Электронная почта:</label>
            <input type="email" id="email" name="email" required>

            <input type="submit" value="Зарегистрироваться">
        </form>
    </div>
</body>
</html>