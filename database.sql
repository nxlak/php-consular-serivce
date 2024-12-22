-- Создание базы данных
CREATE DATABASE IF NOT EXISTS consular_service
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

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

-- Таблица для заявок
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    visa_category VARCHAR(100) NOT NULL,
    status ENUM('Submitted', 'Case Opened', 'Interview Scheduled', 'Visa Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Submitted',
    submission_date DATE NOT NULL,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Таблица для кейсов
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    employee_id INT NOT NULL,
    interview_schedule_id INT DEFAULT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (interview_schedule_id) REFERENCES interview_schedule(id) ON DELETE SET NULL
);

-- Таблица для расписания собеседований
CREATE TABLE IF NOT EXISTS interview_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot VARCHAR(20) NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(employee_id, date, time_slot) -- Чтобы избежать дублирования временных слотов
);

-- Дополнительные индексы для оптимизации запросов
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_interview_schedule_employee ON interview_schedule(employee_id, date, time_slot);
