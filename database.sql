CREATE DATABASE consular_service CHARACTER SET utf8 COLLATE utf8_general_ci;
USE consular_service;

-- Таблица 'applicants' (Заявитель)
CREATE TABLE applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    citizenship VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Таблица 'contact_info' (Контактная информация)
CREATE TABLE contact_info (
    applicant_id INT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Таблица 'applications' (Заявка)
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    visa_category VARCHAR(100) NOT NULL,
    status ENUM('На рассмотрении', 'Принято', 'Отклонено', 'Отменено') DEFAULT 'На рассмотрении',
    interview_id INT DEFAULT NULL,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Таблица 'documents' (Документы)
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expiration_date DATE NOT NULL,
    document_scan LONGBLOB,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Таблица 'schedule' (График)
CREATE TABLE schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot TIME NOT NULL,
    is_free BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Таблица 'employees' (Сотрудник)
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Таблица 'cases' (Дело)
CREATE TABLE cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    application_id INT NOT NULL,
    status ENUM('Открыто', 'Закрыто') DEFAULT 'Открыто',
    opening_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    closing_date DATETIME DEFAULT NULL,
    interview_id INT DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Таблица 'interviews' (Собеседование)
CREATE TABLE interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(255) NOT NULL,
    status ENUM('Пройдено', 'Не пройдено') DEFAULT 'Не пройдено',
    interview_date DATETIME DEFAULT NULL
);

-- Таблица 'visas' (Виза)
CREATE TABLE visas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    visa_type VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL
);
