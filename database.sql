-- Создание базы данных consular_service
CREATE DATABASE IF NOT EXISTS consular_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE consular_service;

-- Таблица для заявителей
CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    citizenship VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для сотрудников
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для контактной информации
CREATE TABLE IF NOT EXISTS contact_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    phone_number VARCHAR(20),
    email VARCHAR(100),
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Таблица для заявок
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    visa_category VARCHAR(100) NOT NULL,
    status ENUM('Submitted', 'In Review', 'Case Opened', 'Interview Scheduled', 'Visa Approved', 'Cancelled') DEFAULT 'Submitted',
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Таблица для документов
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    expiration_date DATE NOT NULL,
    document_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Таблица для графиков собеседований
CREATE TABLE IF NOT EXISTS interview_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot VARCHAR(20) NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE (employee_id, date, time_slot)
);

-- Таблица для дел
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    application_id INT NOT NULL UNIQUE,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    interview_schedule_id INT DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (interview_schedule_id) REFERENCES interview_schedule(id) ON DELETE SET NULL
);
