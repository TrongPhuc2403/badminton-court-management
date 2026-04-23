<?php
require_once 'config/database.php';

$password = password_hash('123456', PASSWORD_DEFAULT);

mysqli_query($conn, "DELETE FROM users WHERE phone='admin' OR email='admin@badminton.local'");

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO users (full_name, phone, email, password, role, is_verified) VALUES (?, ?, ?, ?, ?, 1)"
);

$fullName = 'Quản trị viên';
$phone = 'admin';
$email = 'admin@badminton.local';
$role = 'admin';

mysqli_stmt_bind_param($stmt, "sssss", $fullName, $phone, $email, $password, $role);
mysqli_stmt_execute($stmt);

echo "Tạo admin thành công!";
