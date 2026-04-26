<?php
$host = "localhost";
$username = "root";
$password = "123456";
$database = "badminton_manager";

$conn = mysqli_connect("127.0.0.1:3306", "root", "123456", "badminton_manager");

if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>