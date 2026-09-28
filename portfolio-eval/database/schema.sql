CREATE TABLE IF NOT EXISTS branches (
  branch_id INT AUTO_INCREMENT PRIMARY KEY,
  branch_code VARCHAR(20) NOT NULL UNIQUE,
  branch_name VARCHAR(100) NOT NULL
) CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('ADMIN','TEACHER') NOT NULL,
  branch_id INT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  student_code VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  branch_id INT NOT NULL,
  file_type ENUM('PDF','LINK') NOT NULL DEFAULT 'PDF',
  file_path VARCHAR(500) NOT NULL DEFAULT '',
  final_score DECIMAL(5,2) NULL,
  final_status ENUM('PASS','FAIL','PENDING') NOT NULL DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  teacher_id INT NOT NULL,
  slot TINYINT NOT NULL,
  UNIQUE KEY uq_student_teacher (student_id, teacher_id),
  UNIQUE KEY uq_student_slot (student_id, slot),
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES users(user_id)
) CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS evaluations (
  evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL UNIQUE,
  personal_statement_score TINYINT NULL,
  personal_statement_comment TEXT,
  activity_score TINYINT NULL,
  activity_comment TEXT,
  recommendation_score TINYINT NULL,
  recommendation_comment TEXT,
  english_score TINYINT NULL,
  english_comment TEXT,
  total_score DECIMAL(5,2) NULL,
  overall_comment TEXT,
  is_submitted TINYINT(1) NOT NULL DEFAULT 0,
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE
) CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rubric (
  rubric_id INT AUTO_INCREMENT PRIMARY KEY,
  criterion_key ENUM('personal','activity','rec','english') NOT NULL,
  score TINYINT NOT NULL,
  description TEXT NOT NULL,
  UNIQUE KEY uq_crit_score (criterion_key, score)
) CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4;

INSERT IGNORE INTO branches (branch_code, branch_name) VALUES
('SPEECH_THERAPY','สาขาแก้ไขการพูด'),
('AUDIOLOGY','สาขาแก้ไขการได้ยิน')
