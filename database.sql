-- 1. Создать базу данных (если её нет)
CREATE DATABASE IF NOT EXISTS consular_service
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE consular_service;

-- 2. Создать таблицу для сотрудников (employees)
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- 3. Создать таблицу для заявителей (applicants)
CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    citizenship VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- 4. Создать таблицу для контактной информации (contact_info)
CREATE TABLE IF NOT EXISTS contact_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    phone_number VARCHAR(50),
    email VARCHAR(100),
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- 5. Создать таблицу для собеседований (interviews)
CREATE TABLE IF NOT EXISTS interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Not Conducted', -- Not Conducted, Passed, Failed, etc.
    interview_date DATETIME NOT NULL
);

-- 6. Создать таблицу для заявок (applications)
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    assigned_employee_id INT NULL,
    visa_category VARCHAR(100) NOT NULL,
    submission_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    interview_id INT NULL,
    interview_date DATE NULL,
    interview_time TIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'new',  -- new, in_progress, approved, denied, interview_scheduled, visa_issued, visa_denied и т.д.
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_employee_id) REFERENCES employees(id),
    FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE SET NULL
);

-- 7. Создать таблицу для документов (documents)
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    expiration_date DATE NOT NULL,
    document_scan LONGBLOB NOT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- 8. Создать таблицу для расписания (schedule) - при необходимости
CREATE TABLE IF NOT EXISTS schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot TIME NOT NULL,
    is_free TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);

-- Пример наполнения тестовыми сотрудниками и заявителями:
INSERT INTO employees (full_name, username, password)
VALUES ('Иван Петров', 'ivan_petrov', '12345'),
       ('Мария Сидорова', 'mariya_sidorova', '12345');

INSERT INTO applicants (full_name, date_of_birth, citizenship, username, password)
VALUES ('Андрей Андреев', '1990-01-10', 'Россия', 'andrey', '12345'),
       ('Елена Александрова', '1985-05-20', 'Беларусь', 'elena', '12345');

-- Расписание сотруднкиа:
INSERT INTO schedule (employee_id, date, time_slot, is_free)
VALUES 
(1, '2025-01-10', '10:00:00', 1),
(1, '2025-01-10', '11:30:00', 1),
(1, '2025-01-10', '13:00:00', 1),
(1, '2025-01-10', '14:30:00', 1),
(1, '2025-01-10', '16:00:00', 1),
(1, '2025-01-10', '17:30:00', 1);


DELIMITER //
CREATE TRIGGER trg_applications_update_timestamp
BEFORE UPDATE
ON applications
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//
DELIMITER ;
