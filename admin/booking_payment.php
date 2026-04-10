<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "UPDATE bookings SET payment_status = 'paid' WHERE id = ? AND status = 'confirmed'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

header("Location: /badminton-manager/admin/bookings.php");
exit();