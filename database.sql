-- Drop database if exists and create new one
DROP DATABASE IF EXISTS qlsv;
CREATE DATABASE qlsv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qlsv;

-- Create users table (replaces accounts table)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create departments (khoa) table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(10) UNIQUE NOT NULL,
    department_name VARCHAR(100) NOT NULL
);

-- Create classes (lớp) table
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_code VARCHAR(20) UNIQUE NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    department_id INT,
    academic_year INT NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Create students table (enhanced version of sinhvien)
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('M', 'F', 'O') NOT NULL,
    class_id INT,
    department_id INT,
    academic_year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Create teachers table
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Create subjects table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    credits INT NOT NULL,
    teacher_id INT,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Create course registration table
CREATE TABLE course_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    subject_id INT,
    semester INT NOT NULL,
    academic_year INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    UNIQUE KEY unique_registration (student_id, subject_id, semester, academic_year)
);

-- Create grades table
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    subject_id INT,
    semester INT NOT NULL,
    academic_year INT NOT NULL,
    midterm_grade FLOAT,
    final_grade FLOAT,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

-- Create schedule table (enhanced version of thoikhoabieu)
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT,
    teacher_id INT,
    class_id INT,
    day_of_week INT NOT NULL,
    start_period INT NOT NULL,
    num_periods INT NOT NULL,
    room VARCHAR(20) NOT NULL,
    semester INT NOT NULL,
    academic_year INT NOT NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

-- Create exam schedule table (enhanced version of lichthi)
CREATE TABLE exam_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT,
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    room VARCHAR(20) NOT NULL,
    supervisor_id INT,
    semester INT NOT NULL,
    academic_year INT NOT NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (supervisor_id) REFERENCES teachers(id)
);

-- Create tuition fees table
CREATE TABLE tuition_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    semester INT NOT NULL,
    academic_year INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    payment_date TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Create payment history table
CREATE TABLE payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tuition_fee_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50),
    FOREIGN KEY (tuition_fee_id) REFERENCES tuition_fees(id)
);

-- Create news table (keeping existing tintuc table with enhancements)
CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT,
    publish_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id)
);

-- Insert sample data
-- Insert departments
INSERT INTO departments (department_code, department_name) VALUES
('CNTT', 'Công nghệ Thông tin'),
('KT', 'Kế toán'),
('QT', 'Quản trị Kinh doanh'),
('NN', 'Ngoại ngữ');

-- Insert classes
INSERT INTO classes (class_code, class_name, department_id, academic_year) VALUES
('CT1', 'Công nghệ Thông tin 1', 1, 2024),
('CT2', 'Công nghệ Thông tin 2', 1, 2024),
('KT1', 'Kế toán 1', 2, 2024),
('QT1', 'Quản trị 1', 3, 2024);

-- Insert default admin account (password: admin123)
-- Note: You should run setup_admin.php after creating the database to set the correct password
INSERT INTO users (username, password, role) VALUES 
('admin', '123456', 'admin');

-- Insert sample teacher
INSERT INTO users (username, password, role) VALUES 
('gv001', '123456', 'teacher');

INSERT INTO teachers (teacher_code, user_id, full_name, department_id) VALUES
('GV001', 2, 'Nguyễn Văn Giảng', 1);

-- Insert sample students
INSERT INTO users (username, password, role) VALUES 
('sv001', '123456', 'student'),
('sv002', '123456', 'student'),
('sv003', '123456', 'student');

INSERT INTO students (student_code, user_id, full_name, date_of_birth, gender, class_id, department_id, academic_year) VALUES
('SV001', 3, 'Trần Thị Học', '2003-05-15', 'F', 1, 1, 2024),
('SV002', 4, 'Lê Văn An', '2003-08-20', 'M', 1, 1, 2024),
('SV003', 5, 'Phạm Thị Bình', '2003-03-10', 'F', 2, 1, 2024);

-- Insert sample subjects
INSERT INTO subjects (subject_code, subject_name, credits, teacher_id) VALUES
('CTDL001', 'Cấu trúc dữ liệu và giải thuật', 3, 1),
('CSDL001', 'Cơ sở dữ liệu', 3, 1),
('LTW001', 'Lập trình Web', 4, 1),
('NMCNPM', 'Nhập môn Công nghệ Phần mềm', 3, 1),
('MMT001', 'Mạng máy tính', 3, 1);

-- Insert course registrations
INSERT INTO course_registrations (student_id, subject_id, semester, academic_year) VALUES
(1, 1, 1, 2024),
(1, 2, 1, 2024),
(1, 3, 1, 2024),
(2, 1, 1, 2024),
(2, 2, 1, 2024),
(3, 3, 1, 2024),
(3, 4, 1, 2024);

-- Insert schedules (Thời khóa biểu)
INSERT INTO schedules (subject_id, teacher_id, class_id, day_of_week, start_period, num_periods, room, semester, academic_year) VALUES
-- Thứ 2
(1, 1, 1, 2, 1, 3, 'A101', 1, 2024),
(2, 1, 1, 2, 4, 3, 'B201', 1, 2024),
-- Thứ 3
(3, 1, 1, 3, 1, 4, 'C301', 1, 2024),
(4, 1, 2, 3, 4, 3, 'A102', 1, 2024),
-- Thứ 4
(1, 1, 1, 4, 1, 3, 'A101', 1, 2024),
(5, 1, 1, 4, 4, 3, 'D401', 1, 2024),
-- Thứ 5
(2, 1, 1, 5, 1, 3, 'B201', 1, 2024),
(3, 1, 1, 5, 4, 4, 'C301', 1, 2024),
-- Thứ 6
(4, 1, 1, 6, 1, 3, 'A102', 1, 2024);

-- Insert exam schedules (Lịch thi)
INSERT INTO exam_schedules (subject_id, exam_date, start_time, room, supervisor_id, semester, academic_year) VALUES
(1, '2024-12-15', '07:30:00', 'A101', 1, 1, 2024),
(2, '2024-12-17', '07:30:00', 'B201', 1, 1, 2024),
(3, '2024-12-19', '13:30:00', 'C301', 1, 1, 2024),
(4, '2024-12-20', '07:30:00', 'A102', 1, 1, 2024),
(5, '2024-12-22', '13:30:00', 'D401', 1, 1, 2024);
