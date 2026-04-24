-- Ejecuta este script si YA tienes una base openjobs creada
USE openjobs;

ALTER TABLE users
  ADD COLUMN points INT NOT NULL DEFAULT 0 AFTER google_auth,
  ADD COLUMN level INT NOT NULL DEFAULT 1 AFTER points;

ALTER TABLE reviews
  ADD COLUMN moderation_status ENUM('approved','pending') NOT NULL DEFAULT 'approved' AFTER comment,
  ADD COLUMN moderation_reason VARCHAR(180) NULL AFTER moderation_status,
  ADD COLUMN ai_score TINYINT NOT NULL DEFAULT 0 AFTER moderation_reason;

CREATE TABLE IF NOT EXISTS notifications (
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


INSERT INTO users (name,email,password,role,avatar,google_auth,points,level,created_at)
SELECT 'Soporte OpenJobs','soporte@openjobs.local','$2y$12$9FDd6rGoopHD8XIR4vxxMOTEVAifCFqT3YKMOGj/OrfkjVXhg.jku','support',NULL,0,180,3,NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='soporte@openjobs.local');

INSERT INTO notifications (user_id,title,body,link,type,is_read,created_at)
SELECT u.id,'Soporte técnico disponible','Ya puedes escribir al equipo de soporte desde tu panel.','support.php','support',0,NOW()
FROM users u
WHERE u.email='admin@openjobs.local'
  AND NOT EXISTS (SELECT 1 FROM notifications n WHERE n.user_id=u.id AND n.link='support.php');
