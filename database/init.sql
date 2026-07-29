-- =============================================================
-- Alejandro Lopez | Portal de Clientes — Base de Datos
-- =============================================================

CREATE DATABASE IF NOT EXISTS alejandro_lopez_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE alejandro_lopez_portal;

-- Tabla única de configuración: almacena todo como JSON
CREATE TABLE IF NOT EXISTS app_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) UNIQUE NOT NULL,
  `value` JSON NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
