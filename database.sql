-- database.sql
CREATE DATABASE IF NOT EXISTS openjobs CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE openjobs;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS experience_work;
DROP TABLE IF EXISTS talent_profiles;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','support','talent','company') NOT NULL,
  avatar VARCHAR(255) NULL,
  google_auth TINYINT(1) NOT NULL DEFAULT 0,
  points INT NOT NULL DEFAULT 0,
  level INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE talent_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  headline VARCHAR(255) NULL,
  bio TEXT NULL,
  skills TEXT NULL,
  experience_years INT NOT NULL DEFAULT 0,
  location VARCHAR(150) NULL,
  xp INT NOT NULL DEFAULT 0,
  cv_file VARCHAR(255) NULL,
  CONSTRAINT fk_tp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE experience_work (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  company VARCHAR(150) NOT NULL,
  position VARCHAR(150) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  description TEXT NULL,
  CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  location VARCHAR(150) NULL,
  website VARCHAR(255) NULL,
  logo VARCHAR(255) NULL,
  latitude DECIMAL(10,6) NULL,
  longitude DECIMAL(10,6) NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  active_jobs INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_company_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  technology VARCHAR(180) NULL,
  modality ENUM('Remoto','Presencial','Híbrido') DEFAULT 'Híbrido',
  employment_type VARCHAR(80) DEFAULT 'Tiempo completo',
  experience_required VARCHAR(60) DEFAULT 'Mid',
  location VARCHAR(150) NULL,
  salary_min DECIMAL(10,2) DEFAULT 0,
  salary_max DECIMAL(10,2) DEFAULT 0,
  status ENUM('active','paused','closed') DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_job_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('enviada','revision','aceptada','rechazada') DEFAULT 'enviada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_application (job_id, user_id)
);

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  company_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NOT NULL,
  moderation_status ENUM('approved','pending') NOT NULL DEFAULT 'approved',
  moderation_reason VARCHAR(180) NULL,
  ai_score TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pair (sender_id, receiver_id),
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  body VARCHAR(255) NOT NULL,
  link VARCHAR(255) NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'info',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(255) NOT NULL,
  type VARCHAR(50) DEFAULT 'info',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO users (id,name,email,password,role,avatar,google_auth,points,level,created_at) VALUES
(1,'Admin OpenJobs','admin@openjobs.local','$2y$12$7A5Gdu4f5V6X7t.U1NhRqOlh7FegzJOiHW4nJNKGZWbf67tybS8xS','admin',NULL,0,320,4,NOW()),
(2,'Ana Torres','ana@openjobs.local','$2y$12$7A5Gdu4f5V6X7t.U1NhRqOlh7FegzJOiHW4nJNKGZWbf67tybS8xS','talent','https://placehold.co/120x120',0,140,2,NOW()),
(3,'NovaTech HR','company@openjobs.local','$2y$12$7A5Gdu4f5V6X7t.U1NhRqOlh7FegzJOiHW4nJNKGZWbf67tybS8xS','company','https://placehold.co/120x120',0,80,1,NOW()),
(4,'Soporte OpenJobs','soporte@openjobs.local','$2y$12$9FDd6rGoopHD8XIR4vxxMOTEVAifCFqT3YKMOGj/OrfkjVXhg.jku','support',NULL,0,180,3,NOW());

INSERT INTO talent_profiles (user_id,headline,bio,skills,experience_years,location,xp,cv_file) VALUES
(2,'Frontend & Full Stack Developer','Desarrolladora enfocada en PHP, JavaScript y UX.','PHP, JavaScript, Bootstrap, MySQL, UX',3,'Monterrey, NL',1240,NULL);

INSERT INTO experience_work (user_id,company,position,start_date,end_date,description) VALUES
(2,'Pixel Labs','Frontend Developer','2022-01-10','2023-12-30','Desarrollo de interfaces modernas y accesibles.'),
(2,'CodeNova','Full Stack Developer','2024-01-15',NULL,'Implementación de módulos en PHP y JavaScript.');

INSERT INTO companies (id,user_id,name,description,location,website,logo,latitude,longitude,verified,active_jobs) VALUES
(1,3,'NovaTech','Empresa enfocada en productos digitales y talento remoto.','Monterrey, NL','https://novatech.example','https://placehold.co/120x120',25.6866,-100.3161,1,2);

INSERT INTO jobs (company_id,title,description,technology,modality,employment_type,experience_required,location,salary_min,salary_max,status) VALUES
(1,'Frontend Developer React','Construir interfaces modernas para plataforma de empleo y dashboard SaaS.','React, JavaScript, CSS','Remoto','Tiempo completo','Mid','Monterrey, NL',30000,45000,'active'),
(1,'PHP Developer','Mantener módulos MVC, APIs internas y funcionalidades de reclutamiento.','PHP, MySQL, JavaScript','Híbrido','Tiempo completo','Mid','Monterrey, NL',28000,42000,'active');

INSERT INTO applications (job_id,user_id,status) VALUES (1,2,'revision');
INSERT INTO reviews (user_id,company_id,rating,comment,moderation_status,moderation_reason,ai_score) VALUES (2,1,5,'Muy buena comunicación y proceso claro.','approved','Parece una reseña útil y específica.',91);
INSERT INTO messages (sender_id,receiver_id,body,created_at) VALUES
(3,2,'Hola Ana, vimos tu perfil y nos interesa tu experiencia en PHP.',NOW()),
(2,3,'Gracias. Me interesa conocer más de la vacante.',NOW()),
(4,2,'Hola, soy Soporte OpenJobs. Si detectas fallas o necesitas ayuda técnica, puedes escribirme desde el botón de soporte.',NOW());
INSERT INTO activity_logs (user_id,action,type) VALUES
(2,'Actualizó su perfil','info'),
(2,'Se postuló a una vacante','info'),
(3,'Publicó una vacante','info'),
(1,'Revisó actividad del sistema','admin');

INSERT INTO notifications (user_id,title,body,link,type) VALUES
(2,'Nuevo mensaje','NovaTech HR te envió un mensaje.','chat.php?to=3','message'),
(3,'Nueva postulación','Ana Torres se postuló a Frontend Developer React.','company_jobs.php','application');