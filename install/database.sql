-- DST Recruitment Database Schema
-- PT Digdaya Solusi Teknologi

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('user', 'hrd', 'admin') DEFAULT 'user',
    phone VARCHAR(50) NULL,
    education VARCHAR(255) NULL,
    skills TEXT NULL,
    experience_years INT DEFAULT 0,
    cv_file VARCHAR(255) NULL,
    bio TEXT NULL,
    avatar VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    department VARCHAR(255) NULL,
    location VARCHAR(255) NOT NULL,
    type ENUM('Full-time', 'Part-time', 'Contract', 'Internship') DEFAULT 'Full-time',
    salary_min VARCHAR(50) NULL,
    salary_max VARCHAR(50) NULL,
    description TEXT NOT NULL,
    requirements TEXT NULL,
    skills TEXT NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    cv_file VARCHAR(255) NULL,
    cover_letter TEXT NULL,
    status ENUM('pending', 'screening', 'interview', 'accepted', 'rejected') DEFAULT 'pending',
    score INT DEFAULT 0,
    notes TEXT NULL,
    decision_reason TEXT NULL,
    decision_saw_summary TEXT NULL,
    decision_at DATETIME NULL,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (user_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_jobs_status ON jobs(status);
CREATE INDEX idx_jobs_created_by ON jobs(created_by);
CREATE INDEX idx_applications_user ON applications(user_id);
CREATE INDEX idx_applications_job ON applications(job_id);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_messages_from_to ON messages(from_user_id, to_user_id);
CREATE INDEX idx_messages_to_read ON messages(to_user_id, is_read);

-- Default HRD/Admin accounts (password: password123)
INSERT IGNORE INTO users (email, password, full_name, role, status)
VALUES
('hrd@dst.co.id', '$2y$10$bich/LUeEufmdfOMWW2FUOMw5/RHbBjGc/C4GNgIanCnPpcEroWZ2', 'HRD DST', 'hrd', 'active'),
('admin@dst.co.id', '$2y$10$bich/LUeEufmdfOMWW2FUOMw5/RHbBjGc/C4GNgIanCnPpcEroWZ2', 'Admin DST', 'admin', 'active');

-- Sample jobs
INSERT INTO jobs (title, department, location, type, salary_min, salary_max, description, requirements, skills, status, created_by)
SELECT 'Product Designer', 'Design', 'Jakarta', 'Full-time', '9000000', '15000000',
       'Membuat desain produk digital end-to-end bersama tim produk dan engineering.',
       'Portfolio UI/UX, menguasai Figma, memahami design system.',
       'Figma,UI Design,UX Research,Design System,Prototyping',
       'open', u.id
FROM users u
WHERE u.email = 'hrd@dst.co.id'
  AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.title = 'Product Designer');

INSERT INTO jobs (title, department, location, type, salary_min, salary_max, description, requirements, skills, status, created_by)
SELECT 'Frontend Engineer', 'Engineering', 'Hybrid', 'Full-time', '10000000', '17000000',
       'Membangun antarmuka web modern yang cepat dan mudah digunakan.',
       'Menguasai JavaScript modern, pengalaman framework frontend, kolaborasi dengan API backend.',
       'JavaScript,HTML,CSS,React,API,Git',
       'open', u.id
FROM users u
WHERE u.email = 'hrd@dst.co.id'
  AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.title = 'Frontend Engineer');
