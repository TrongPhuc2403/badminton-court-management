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
    phone VARCHAR(20) UNIQUE,
    email VARCHAR(120) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(64) DEFAULT NULL,
    verification_expires_at DATETIME DEFAULT NULL,
    verification_sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_users_contact CHECK (phone IS NOT NULL OR email IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    image_path VARCHAR(255),
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
    payment_method ENUM('cash', 'bank_transfer') NOT NULL DEFAULT 'cash',
    payment_reference VARCHAR(50) DEFAULT NULL,
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

INSERT INTO users (full_name, phone, email, password, role, is_verified) VALUES
(
    'Quản trị viên',
    'admin',
    'admin@badminton.local',
    '$2y$12$ZpwFJQZSyn7cXlAGlAO7BeqJpgr6UFz.NWxv0Fx2J7nWj3rYhjX5a',
    'admin',
    1
);

INSERT INTO courts (name, image_path, status) VALUES
('Sân 1', 'image/san-cau.jpg', 'active'),
('Sân 2', 'image/san-cau.jpg', 'active'),
('Sân 3', 'image/san-cau.jpg', 'active'),
('Sân 4', 'image/san-cau.jpg', 'active'),
('Sân 5', 'image/san-cau.jpg', 'active'),
('Sân 6', 'image/san-cau.jpg', 'active'),
('Sân 7', 'image/san-cau.jpg', 'active'),
('Sân 8', 'image/san-cau.jpg', 'active');

INSERT INTO users (full_name, phone, email, password, role, is_verified) VALUES
(
    'Nguyễn Văn A',
    '0900000001',
    'khach1@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.ogZTlR6D4jV0L1Wy',
    'customer',
    1
),
(
    'Trần Thị B',
    '0900000002',
    'khach2@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.ogZTlR6D4jV0L1Wy',
    'customer',
    1
);

INSERT INTO bookings (
    user_id,
    court_id,
    booking_date,
    start_time,
    end_time,
    total_price,
    payment_method,
    payment_reference,
    payment_status,
    status
) VALUES
(2, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '10:00:00', 180000, 'cash', 'BOOKING-0001', 'unpaid', 'confirmed'),
(3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:00:00', '19:00:00', 240000, 'bank_transfer', 'BOOKING-0002', 'paid', 'confirmed'),
(2, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '06:00:00', '08:00:00', 180000, 'cash', 'BOOKING-0003', 'unpaid', 'confirmed'),
(3, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '19:00:00', '21:00:00', 240000, 'bank_transfer', 'BOOKING-0004', 'paid', 'confirmed');
