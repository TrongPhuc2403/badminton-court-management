<?php
require_once '../includes/customer_auth.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /badminton-manager/customer/my_bookings.php?error=invalid_payment_confirmation');
    exit();
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);

$sql = "UPDATE bookings
        SET payment_status = 'paid'
        WHERE id = ?
          AND user_id = ?
          AND status = 'confirmed'
          AND payment_status = 'unpaid'
          AND payment_method = 'bank_transfer'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $bookingId, $_SESSION['user']['id']);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    header('Location: /badminton-manager/customer/my_bookings.php?success=payment_confirmed');
    exit();
}

header('Location: /badminton-manager/customer/my_bookings.php?error=invalid_payment_confirmation');
exit();
