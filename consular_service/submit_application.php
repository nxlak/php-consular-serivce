<?php
// submit_application.php
include 'config.php';

// Проверка аутентификации
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'applicant') {
    header("Location: login.php");
    exit();
}

$applicant_id = $_SESSION['user_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $visa_category = trim($_POST['visa_category']);
    $contact_phone = trim($_POST['phone_number']);
    $contact_email = trim($_POST['email']);

    // Валидация
    if (empty($visa_category) || empty($contact_phone) || empty($contact_email)) {
        $errors[] = "Все поля обязательны для заполнения.";
    }

    // Обработка документов
    $documents = [];
    foreach ($_FILES['documents']['name'] as $key => $name) {
        if ($_FILES['documents']['error'][$key] == 0) {
            $tmp_name = $_FILES['documents']['tmp_name'][$key];
            $size = $_FILES['documents']['size'][$key];
            $type = $_FILES['documents']['type'][$key];

            // Проверка типа файла
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($type, $allowed_types)) {
                $errors[] = "Неподдерживаемый тип файла для документа " . ($key + 1) . ".";
                continue;
            }

            // Проверка размера файла (например, не более 5 МБ)
            if ($size > 5 * 1024 * 1024) {
                $errors[] = "Файл документа " . ($key + 1) . " превышает допустимый размер.";
                continue;
            }

            // Безопасное чтение файла
            $content = file_get_contents($tmp_name);
            if ($content === false) {
                $errors[] = "Не удалось прочитать файл документа " . ($key + 1) . ".";
                continue;
            }

            $documents[] = [
                'type' => trim($_POST['document_type'][$key]),
                'scan' => $content,
                'expiration_date' => $_POST['expiration_date'][$key]
            ];
        }
    }

    if (empty($documents)) {
        $errors[] = "Необходимо загрузить хотя бы один документ.";
    }

    if (empty($errors)) {
        // Вставка заявки
        $stmt = $conn->prepare("INSERT INTO applications (applicant_id, visa_category, status) VALUES (?, ?, 'new')");
        if (!$stmt) {
            $errors[] = "Ошибка подготовки запроса: " . $conn->error;
        } else {
            $stmt->bind_param("is", $applicant_id, $visa_category);
            if ($stmt->execute()) {
                $application_id = $stmt->insert_id;

                // Обновление контактной информации
                $stmt2 = $conn->prepare("UPDATE contact_info SET phone_number = ?, email = ? WHERE applicant_id = ?");
                if ($stmt2) {
                    $stmt2->bind_param("ssi", $contact_phone, $contact_email, $applicant_id);
                    if (!$stmt2->execute()) {
                        $errors[] = "Ошибка при обновлении контактной информации: " . $stmt2->error;
                    }
                    $stmt2->close();
                } else {
                    $errors[] = "Ошибка подготовки запроса для контактной информации: " . $conn->error;
                }

                // Вставка документов
                $stmt3 = $conn->prepare("INSERT INTO documents (application_id, document_type, expiration_date, document_scan) VALUES (?, ?, ?, ?)");
                if ($stmt3) {
                    foreach ($documents as $doc) {
                        $stmt3->bind_param("isss", $application_id, $doc['type'], $doc['expiration_date'], $doc['scan']);
                        if (!$stmt3->execute()) {
                            $errors[] = "Ошибка при вставке документа: " . $stmt3->error;
                        }
                    }
                    $stmt3->close();
                } else {
                    $errors[] = "Ошибка подготовки запроса для документов: " . $conn->error;
                }

                if (empty($errors)) {
                    $success = "Заявка успешно подана.";
                }
            } else {
                $errors[] = "Ошибка при подаче заявки: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подать заявку - Консульская служба</title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
        // Скрипт для добавления дополнительных полей для документов
        function addDocumentField() {
            const container = document.getElementById('documents_container');
            const index = container.children.length + 1;
            const docDiv = document.createElement('div');
            docDiv.innerHTML = `
                <h4>Документ ${index}</h4>
                <label>Тип документа:</label>
                <input type="text" name="document_type[]" required>

                <label>Срок действия:</label>
                <input type="date" name="expiration_date[]" required>

                <label>Скан документа:</label>
                <input type="file" name="documents[]" accept="image/*,application/pdf" required>
                <hr>
            `;
            container.appendChild(docDiv);
        }
    </script>
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
        <h2>Подать заявку на визу</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success">
                <p><?= htmlspecialchars($success) ?></p>
            </div>
        <?php endif; ?>
        <form action="submit_application.php" method="POST" enctype="multipart/form-data">
            <label for="visa_category">Категория визы:</label>
            <input type="text" id="visa_category" name="visa_category" required>

            <h3>Контактная информация</h3>
            <label for="phone_number">Номер телефона:</label>
            <input type="text" id="phone_number" name="phone_number" required>

            <label for="email">Электронная почта:</label>
            <input type="email" id="email" name="email" required>

            <h3>Документы</h3>
            <div id="documents_container">
                <div>
                    <h4>Документ 1</h4>
                    <label>Тип документа:</label>
                    <input type="text" name="document_type[]" required>

                    <label>Срок действия:</label>
                    <input type="date" name="expiration_date[]" required>

                    <label>Скан документа:</label>
                    <input type="file" name="documents[]" accept="image/*,application/pdf" required>
                    <hr>
                </div>
            </div>
            <button type="button" onclick="addDocumentField()">Добавить ещё документ</button>

            <input type="submit" value="Подать заявку">
        </form>
    </div>
</body>
</html>
