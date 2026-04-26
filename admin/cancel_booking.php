<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /badminton-manager/admin/bookings.php?error=invalid_booking');
    exit();
}

$sql = "SELECT id, status
        FROM bookings
        WHERE id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking || ($booking['status'] ?? '') !== 'confirmed') {
    header('Location: /badminton-manager/admin/bookings.php?error=booking_not_cancellable');
    exit();
}

$updateSql = "UPDATE bookings
              SET status = 'cancelled'
              WHERE id = ?
                AND status = 'confirmed'";
$updateStmt = mysqli_prepare($conn, $updateSql);
mysqli_stmt_bind_param($updateStmt, 'i', $id);
mysqli_stmt_execute($updateStmt);

header('Location: /badminton-manager/admin/bookings.php?success=booking_cancelled');
exit();
