<?php
session_start();
require_once '../config/database.php';
require_once '../includes/customer_auth.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';

ensureLoyaltyTables($conn);
syncPendingLoyaltyAwards($conn);

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user']['id'] ?? 0;
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($userId <= 0 || $bookingId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu dữ liệu'
    ]);
    exit;
}

$sql = "SELECT id, payment_status, status, payment_method
        FROM bookings
        WHERE id = ? AND user_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $bookingId, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy booking'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'payment_status' => $booking['payment_status'],
    'payment_status_label' => getPaymentStatusDisplayLabel($booking['payment_status'], $booking['payment_method'] ?? 'cash'),
    'status' => $booking['status']
]);
