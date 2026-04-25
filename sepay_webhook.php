<?php
require_once 'config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

$configFile = __DIR__ . '/config/payment.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Missing config/payment.php'
    ]);
    exit();
}

$paymentConfig = require $configFile;
$expectedApiKey = trim((string)($paymentConfig['webhook_api_key'] ?? ''));

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($authHeader === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = trim((string)$value);
            break;
        }
    }
}

$expectedAuth = 'Apikey ' . $expectedApiKey;

if ($expectedApiKey === '' || trim((string)$authHeader) !== $expectedAuth) {
    $storageDir = __DIR__ . '/storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0777, true);
    }

    file_put_contents(
        $storageDir . '/sepay_webhook_auth.log',
        "[" . date('Y-m-d H:i:s') . "]\n"
        . "EXPECTED: " . $expectedAuth . "\n"
        . "RECEIVED: " . ($authHeader === '' ? 'EMPTY' : $authHeader) . "\n\n",
        FILE_APPEND
    );

    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

file_put_contents(
    $storageDir . '/sepay_webhook.log',
    "[" . date('Y-m-d H:i:s') . "]\n"
    . "AUTH: " . ($authHeader === '' ? 'EMPTY' : $authHeader) . "\n"
    . "BODY: " . $rawBody . "\n\n",
    FILE_APPEND
);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON'
    ]);
    exit();
}

$transactionId = (int)($data['id'] ?? 0);
$paymentCode = trim((string)($data['code'] ?? ''));
$transferType = trim((string)($data['transferType'] ?? ''));
$transferAmount = (float)($data['transferAmount'] ?? 0);

if ($transactionId <= 0 || $transferType !== 'in' || $paymentCode === '' || $transferAmount <= 0) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Ignored'
    ]);
    exit();
}

$findSql = "SELECT id, total_price, payment_status, payment_method
            FROM bookings
            WHERE payment_reference = ?
            LIMIT 1";
$findStmt = mysqli_prepare($conn, $findSql);
mysqli_stmt_bind_param($findStmt, 's', $paymentCode);
mysqli_stmt_execute($findStmt);
$findResult = mysqli_stmt_get_result($findStmt);
$booking = mysqli_fetch_assoc($findResult);

if (!$booking) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Booking not found'
    ]);
    exit();
}

if (($booking['payment_method'] ?? '') !== 'bank_transfer') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Not bank transfer booking'
    ]);
    exit();
}

if (($booking['payment_status'] ?? '') === 'paid') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Already paid'
    ]);
    exit();
}

if ((float)$booking['total_price'] !== $transferAmount) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Amount mismatch'
    ]);
    exit();
}

$updateSql = "UPDATE bookings
              SET payment_status = 'paid'
              WHERE id = ?
                AND payment_status = 'unpaid'";
$updateStmt = mysqli_prepare($conn, $updateSql);
mysqli_stmt_bind_param($updateStmt, 'i', $booking['id']);
mysqli_stmt_execute($updateStmt);

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Payment confirmed'
]);