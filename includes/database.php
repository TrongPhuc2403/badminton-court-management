<?php
$host = "localhost";
$user = "root";
$pass = ""; // Để trống nếu dùng XAMPP mặc định
$dbname = "badminton_db";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}
// Thiết lập tiếng Việt
mysqli_set_charset($conn, "utf8");


/* -- Tạo cơ sở dữ liệu nếu chưa có và sử dụng nó
CREATE DATABASE IF NOT EXISTS badminton_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE badminton_db;

-- Xóa bảng cũ nếu tồn tại (để làm mới hoàn toàn dữ liệu)
DROP TABLE IF EXISTS courts;

-- Tạo bảng courts (quản lý sân)
CREATE TABLE courts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    court_name VARCHAR(50) NOT NULL,
    floor VARCHAR(20) NOT NULL,
    court_type ENUM('Thường', 'VIP') DEFAULT 'Thường',
    status ENUM('ready', 'in_use', 'maintenance') DEFAULT 'ready',
    price DECIMAL(10, 3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm chính xác 8 sân như trong hình ảnh thiết kế
INSERT INTO courts (court_name, floor, court_type, status, price) VALUES
('Sân 1', 'Tầng 1', 'Thường', 'ready', 80.000),
('Sân 2', 'Tầng 1', 'Thường', 'in_use', 80.000),
('Sân 3', 'Tầng 1', 'Thường', 'ready', 80.000),
('Sân 4', 'Tầng 2', 'VIP', 'in_use', 120.000),
('Sân 5', 'Tầng 2', 'VIP', 'ready', 120.000),
('Sân 6', 'Tầng 2', 'Thường', 'maintenance', 80.000),
('Sân 7', 'Tầng 2', 'VIP', 'ready', 120.000),
('Sân 8', 'Tầng 1', 'Thường', 'ready', 80.000);
*/
?>
