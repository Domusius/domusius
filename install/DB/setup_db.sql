SET FOREIGN_KEY_CHECKS=0;

-- =====================================================
-- USERS
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
  last_login DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reset_token VARCHAR(64) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ADMIN LOGS
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT DEFAULT NULL,
  action VARCHAR(50) NOT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- SITE SETTINGS
-- =====================================================
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MODULES (OPDATERET MED DASHBOARD KOLONNER)
-- =====================================================
CREATE TABLE IF NOT EXISTS modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL,
  module_icon VARCHAR(50) DEFAULT 'fas fa-box',
  version VARCHAR(10) NOT NULL DEFAULT '0.00',
  db_version VARCHAR(10) NOT NULL DEFAULT '0.00',
  is_installed TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  installed_at DATETIME DEFAULT NULL,
  -- Her er de nye dashboard kolonner:
  show_on_dashboard TINYINT(1) DEFAULT 0,
  dashboard_widget_type VARCHAR(255) DEFAULT 'dashboard_view.php',
  dashboard_size ENUM('small', 'medium', 'full') DEFAULT 'medium',
  dashboard_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MODULE SETTINGS
-- =====================================================
CREATE TABLE IF NOT EXISTS module_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(50) NOT NULL UNIQUE,
  menu_title VARCHAR(50) DEFAULT NULL,
  admin_menu_title VARCHAR(50) DEFAULT NULL,
  version VARCHAR(10) DEFAULT '0.00',
  display_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- PRODUKTION
-- =====================================================
CREATE TABLE IF NOT EXISTS produktion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  Year INT DEFAULT NULL,
  Month INT DEFAULT NULL,
  Day INT DEFAULT NULL,
  dato DATE DEFAULT NULL,
  system_production DECIMAL(10,2) DEFAULT NULL,
  self_consumption DECIMAL(10,2) DEFAULT NULL,
  load_consumption DECIMAL(10,2) DEFAULT NULL,
  bat_charging DECIMAL(10,2) DEFAULT NULL,
  bat_discharging DECIMAL(10,2) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- DB MIGRATIONS LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS db_migrations_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  from_version VARCHAR(10) NOT NULL,
  to_version VARCHAR(10) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  status ENUM('success','failed') NOT NULL DEFAULT 'success',
  message TEXT DEFAULT NULL,
  executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CORE DATA
-- =====================================================
INSERT INTO modules (module_key, name, description, module_icon, version, is_installed, is_active) VALUES
('core', 'Kerne-system', 'Grundlæggende systemfunktioner', 'fas fa-cog', '0.00', 1, 1),
('users', 'Brugerstyring', 'Admin og brugerhåndtering', 'fas fa-users', '0.00', 1, 1),
('system', 'System', 'Database og moduler', 'fas fa-tools', '0.00', 1, 1)
ON DUPLICATE KEY UPDATE module_key = module_key;

SET FOREIGN_KEY_CHECKS=1;