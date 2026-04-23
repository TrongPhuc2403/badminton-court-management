HƯỚNG DẪN CHẠY WEB QUẢN LÍ SÂN CẦU LÔNG

1. Cài đặt môi trường

Tải và cài đặt XAMPP:
👉 https://www.apachefriends.org/

Sau khi cài:

Mở XAMPP
Start:
Apache ✅
MySQL ✅ 2. Copy source code

Copy thư mục project vào:

C:\xampp\htdocs\

Đảm bảo đường dẫn:

C:\xampp\htdocs\badminton-manager 3. Tạo database
Bước 1:

Mở trình duyệt:

http://localhost/phpmyadmin
Bước 2:

Tạo database mới:

badminton_manager
Bước 3:
Chọn database vừa tạo
Chọn tab SQL
Copy toàn bộ nội dung file database.sql
Bấm Thực hiện 4. Cấu hình database

Mở file:

config/database.php

Kiểm tra:

$host = "localhost";
$username = "root";
$password = "";
$database = "badminton_manager"; 5. Chạy project

Mở trình duyệt:

http://localhost/badminton-manager 6. Tài khoản đăng nhập
Admin
SĐT: admin
Mật khẩu: password 7. Nếu không đăng nhập được admin

Tạo file make_admin.php trong project:

<?php
require_once 'config/database.php';

$password = password_hash('123456', PASSWORD_DEFAULT);

mysqli_query($conn, "DELETE FROM users WHERE phone='admin'");

$stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, phone, password, role) VALUES (?, ?, ?, ?)");

$full_name = 'Quản trị viên';
$phone = 'admin';
$role = 'admin';

mysqli_stmt_bind_param($stmt, "ssss", $full_name, $phone, $password, $role);
mysqli_stmt_execute($stmt);

echo "Tạo admin thành công!";

Mở:

http://localhost/badminton-manager/make_admin.php

Đăng nhập:

SĐT: admin
Mật khẩu: 123456
