<?php
// submit_application.php
include 'config.php';

// Проверка аутентификации
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'applicant') {
    header("Location: login.php");
    exit();
}

$applicant_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Список категорий виз
$visa_categories = [
    'Студенческая',
    'Туристическая',
    'Рабочая',
    'Деловая',
    'Транзитная'
];

// Список типов документов
$document_types = [
    'Паспорт',
    'Загранпаспорт',
    'Справка с места работы',
    'Справка с места учебы',
    'Фотография',
    'Медицинская страховка'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visa_category = trim($_POST['visa_category'] ?? '');
    $contact_phone = trim($_POST['phone_number'] ?? '');
    $contact_email = trim($_POST['email'] ?? '');

    // Валидация
    if (empty($visa_category) || empty($contact_phone) || empty($contact_email)) {
        $errors[] = "Все поля обязательны для заполнения.";
    }

    // Обработка документов
    $documents = [];
    if (!empty($_FILES['documents']['name'])) {
        foreach ($_FILES['documents']['name'] as $key => $name) {
            // Проверяем, не было ли ошибки при загрузке
            if ($_FILES['documents']['error'][$key] === 0) {
                $tmp_name = $_FILES['documents']['tmp_name'][$key];
                $size     = $_FILES['documents']['size'][$key];
                $type     = $_FILES['documents']['type'][$key];

                // Проверка типа файла
                $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($type, $allowed_types)) {
                    $errors[] = "Неподдерживаемый тип файла для документа " . ($key + 1) . ".";
                    continue;
                }

                // Проверка размера файла (например, не более 5 МБ)
                if ($size > 5 * 1024 * 1024) {
                    $errors[] = "Файл документа " . ($key + 1) . " превышает допустимый размер (5 МБ).";
                    continue;
                }

                // Безопасное чтение файла
                $content = file_get_contents($tmp_name);
                if ($content === false) {
                    $errors[] = "Не удалось прочитать файл документа " . ($key + 1) . ".";
                    continue;
                }

                // Проверка срока действия документа
                $expiration_date = $_POST['expiration_date'][$key] ?? '';
                if (strtotime($expiration_date) < time()) {
                    $errors[] = "Срок действия документа " . ($key + 1) . " не может быть меньше текущей даты.";
                    continue;
                }

                // Сохраняем документ в массив
                $documents[] = [
                    'type'            => trim($_POST['document_type'][$key]),
                    'scan'            => $content,
                    'expiration_date' => $expiration_date
                ];
            }
        }
    }

    // Проверяем, что хотя бы один документ загружен
    if (empty($documents)) {
        $errors[] = "Необходимо загрузить хотя бы один документ.";
    }

    // Если нет ошибок, работаем с БД
    if (empty($errors)) {
        // Начало транзакции
        $conn->begin_transaction();

        try {
            // 1) Вставка заявки
            $stmt = $conn->prepare("INSERT INTO applications (applicant_id, visa_category, status) VALUES (?, ?, 'new')");
            if (!$stmt) {
                throw new Exception("Ошибка подготовки запроса: " . $conn->error);
            }
            $stmt->bind_param("is", $applicant_id, $visa_category);
            if (!$stmt->execute()) {
                throw new Exception("Ошибка при подаче заявки: " . $stmt->error);
            }
            $application_id = $stmt->insert_id;
            $stmt->close();

            // 2) Обновление контактной информации
            $stmt2 = $conn->prepare("UPDATE contact_info SET phone_number = ?, email = ? WHERE applicant_id = ?");
            if (!$stmt2) {
                throw new Exception("Ошибка подготовки запроса для контактной информации: " . $conn->error);
            }
            $stmt2->bind_param("ssi", $contact_phone, $contact_email, $applicant_id);
            if (!$stmt2->execute()) {
                throw new Exception("Ошибка при обновлении контактной информации: " . $stmt2->error);
            }
            $stmt2->close();

            // 3) Вставка документов
            $stmt3 = $conn->prepare("
                INSERT INTO documents (application_id, document_type, expiration_date, document_scan)
                VALUES (?, ?, ?, ?)
            ");
            if (!$stmt3) {
                throw new Exception("Ошибка подготовки запроса для документов: " . $conn->error);
            }
            foreach ($documents as $doc) {
                $stmt3->bind_param(
                    "isss",
                    $application_id,
                    $doc['type'],
                    $doc['expiration_date'],
                    $doc['scan']
                );
                if (!$stmt3->execute()) {
                    throw new Exception("Ошибка при вставке документа: " . $stmt3->error);
                }
            }
            $stmt3->close();

            // Если всё прошло успешно, коммит
            $conn->commit();

            // Сообщение об успехе
            $success = "Заявка успешно подана.";
        } catch (Exception $e) {
            // Откат транзакции при ошибке
            $conn->rollback();
            $errors[] = $e->getMessage();
        }

        // Если заявка подана успешно — записываем событие в cookie
        if ($success) {
            // Прочитаем старый cookie
            $recent_actions = [];
            if (isset($_COOKIE['recent_actions'])) {
                $json_data = json_decode($_COOKIE['recent_actions'], true);
                if (is_array($json_data)) {
                    $recent_actions = $json_data;
                }
            }

            // Добавляем новое действие
            $recent_actions[] = [
                'action'         => 'Подана новая заявка',
                'application_id' => $application_id,
                'time'           => date('Y-m-d H:i:s')
            ];

            // Ограничим список до 5 последних действий
            if (count($recent_actions) > 5) {
                array_shift($recent_actions);
            }

            // Кодируем в JSON
            $recent_actions_json = json_encode($recent_actions, JSON_UNESCAPED_UNICODE);

            // Устанавливаем cookie на 5 минут (300 секунд)
            setcookie('recent_actions', $recent_actions_json, time() + 300, '/');
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
            // Получаем количество уже добавленных блоков документов
            const index = container.children.length + 1;
            const docDiv = document.createElement('div');
            
            // Встраиваемый HTML для нового блока документа
            docDiv.innerHTML = `
                <h4>Документ ${index}</h4>
                <label>Тип документа:</label>
                <select name="document_type[]" required>
                    <?php foreach ($document_types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>">
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Срок действия:</label>
                <input type="date" name="expiration_date[]" min="<?= date('Y-m-d') ?>" required>

                <label>Скан документа:</label>
                <input type="file" name="documents[]" accept="image/*,application/pdf" required>
                <hr>
            `;
            container.appendChild(docDiv);
        }

        // Проверка корректности дат перед отправкой формы
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                const expirationDates = document.querySelectorAll('input[type="date"]');
                let isValid = true;

                expirationDates.forEach(dateInput => {
                    const selectedDate = new Date(dateInput.value);
                    const currentDate = new Date();

                    if (selectedDate < currentDate) {
                        alert('Дата "Срок действия" не может быть меньше текущей даты.');
                        isValid = false;
                    }
                });

                if (!isValid) {
                    event.preventDefault(); // Остановить отправку формы
                }
            });
        });
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
            <select id="visa_category" name="visa_category" required>
                <option value="">-- Выберите категорию --</option>
                <?php foreach ($visa_categories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>">
                        <?= htmlspecialchars($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>

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
                    <select name="document_type[]" required>
                        <?php foreach ($document_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>">
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Срок действия:</label>
                    <input type="date" name="expiration_date[]" min="<?= date('Y-m-d') ?>" required>

                    <label>Скан документа:</label>
                    <input type="file" name="documents[]" accept="image/*,application/pdf" required>
                    <hr>
                </div>
            </div>
            <button type="button" onclick="addDocumentField()">
                Добавить ещё документ
            </button>

            <input type="submit" value="Подать заявку">
        </form>
    </div>
</body>
</html>