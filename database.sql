CREATE DATABASE IF NOT EXISTS badminton_manager
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE badminton_manager;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS courts;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    status ENUM('active', 'maintenance') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    court_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
    status ENUM('confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_court
        FOREIGN KEY (court_id) REFERENCES courts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (full_name, phone, password, role) VALUES
(
    'Quản trị viên',
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.ogZTlR6D4jV0L1Wy',
    'admin'
);

INSERT INTO courts (name, status) VALUES
('Sân 1', 'active'),
('Sân 2', 'active'),
('Sân 3', 'active'),
('Sân 4', 'active'),
('Sân 5', 'active'),
('Sân 6', 'active'),
('Sân 7', 'active'),
('Sân 8', 'active');

INSERT INTO users (full_name, phone, password, role) VALUES
(
    'Nguyễn Văn A',
    '0900000001',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.ogZTlR6D4jV0L1Wy',
    'customer'
),
(
    'Trần Thị B',
    '0900000002',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.ogZTlR6D4jV0L1Wy',
    'customer'
);

INSERT INTO bookings (
    user_id,
    court_id,
    booking_date,
    start_time,
    end_time,
    total_price,
    payment_status,
    status
) VALUES
(2, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '10:00:00', 180000, 'unpaid', 'confirmed'),
(3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:00:00', '19:00:00', 240000, 'paid', 'confirmed'),
(2, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '06:00:00', '08:00:00', 180000, 'unpaid', 'confirmed'),
(3, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '19:00:00', '21:00:00', 240000, 'paid', 'confirmed');