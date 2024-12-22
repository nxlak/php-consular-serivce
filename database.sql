CREATE DATABASE consular_service;
USE consular_service;

-- Таблица для заявителей
CREATE TABLE applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    citizenship VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Таблица для контактной информации
CREATE TABLE contact_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL UNIQUE,
    phone_number VARCHAR(20),
    email VARCHAR(100) NOT NULL UNIQUE,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Таблица для сотрудников
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Таблица для заявок
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    visa_category VARCHAR(100) NOT NULL,
    submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Submitted', 'Approved', 'Rejected', 'Scheduled Interview', 'Interview Completed', 'Visa Issued', 'Visa Denied', 'Cancelled') DEFAULT 'Submitted',
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
);

-- Таблица для документов
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expiration_date DATE,
    document_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Таблица для графика собеседований
CREATE TABLE interview_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_schedule (employee_id, date, time_slot)
);

-- Таблица для дел
CREATE TABLE cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    application_id INT NOT NULL,
    status ENUM('Assigned', 'Interview Scheduled', 'Interview Completed', 'Decision Made') DEFAULT 'Assigned',
    interview_date DATE,
    interview_time TIME,
    interview_result TEXT,
    final_decision ENUM('Visa Issued', 'Visa Denied'),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Таблица для виз
CREATE TABLE visas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    visa_type VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
);
