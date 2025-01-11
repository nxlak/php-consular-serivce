<?php

include 'config.php';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Консульская служба</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header>
        <h1>Консульская служба</h1>
        <nav>
            <?php if (isset($_SESSION['user_type'])): ?>
                <?php if ($_SESSION['user_type'] == 'applicant'): ?>
                    <a href="dashboard.php">Личный кабинет</a>
                <?php elseif ($_SESSION['user_type'] == 'employee'): ?>
                    <a href="employee/employee_dashboard.php">Панель сотрудника</a>
                <?php endif; ?>
                <a href="logout.php">Выйти</a>
            <?php else: ?>
                <a href="register.php">Регистрация</a>
                <a href="login.php">Вход</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="container">
        <h2>Добро пожаловать в консульскую службу</h2>
        <p>Здесь вы можете подать заявку на визу, отслеживать её статус и получать результаты.</p>
    </div>
</body>
</html>