<?php

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($number)
{
    return number_format((float) $number, 0, ',', '.') . ' VNĐ';
}

function isWeekend($date)
{
    $day = (int) date('N', strtotime($date));
    return $day >= 6;
}

function getHourPrice($date, $hour)
{
    if (isWeekend($date)) {
        return 120000;
    }

    if ($hour >= 4 && $hour <= 16) {
        return 90000;
    }

    if ($hour >= 17 && $hour <= 21) {
        return 120000;
    }

    return 0;
}

function calculateBookingPrice($bookingDate, $startTime, $endTime)
{
    $startHour = (int) date('H', strtotime($startTime));
    $endHour = (int) date('H', strtotime($endTime));
    $total = 0;

    for ($hour = $startHour; $hour < $endHour; $hour++) {
        $total += getHourPrice($bookingDate, $hour);
    }

    return $total;
}

function isValidHourStep($time)
{
    return preg_match('/^\d{2}:00$/', $time) === 1;
}

function isValidBookingTime($startTime, $endTime)
{
    if (!isValidHourStep($startTime) || !isValidHourStep($endTime)) {
        return false;
    }

    $startHour = (int) date('H', strtotime($startTime));
    $endHour = (int) date('H', strtotime($endTime));

    if ($startHour < 4 || $endHour > 22) {
        return false;
    }

    return $endHour > $startHour;
}

function isPastDate($date)
{
    return $date < date('Y-m-d');
}

function checkCourtAvailable($conn, $courtId, $bookingDate, $startTime, $endTime)
{
    $sql = "SELECT id
            FROM bookings
            WHERE court_id = ?
              AND booking_date = ?
              AND status = 'confirmed'
              AND (? < end_time AND ? > start_time)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isss", $courtId, $bookingDate, $startTime, $endTime);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) === 0;
}

function bookingCanBeCancelled($bookingDate, $startTime)
{
    $bookingDateTime = strtotime($bookingDate . ' ' . $startTime);
    return $bookingDateTime > time();
}

function getCurrentPath()
{
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}

function isActiveMenu($path)
{
    return getCurrentPath() === $path ? 'active' : '';
}

function normalizeContact($contact)
{
    return trim((string) $contact);
}

function detectContactType($contact)
{
    if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        return 'email';
    }

    if (preg_match('/^(0|\+84)\d{8,10}$/', $contact) === 1) {
        return 'phone';
    }

    return null;
}

function getUserByContact($conn, $contact)
{
    $sql = "SELECT * FROM users WHERE phone = ? OR email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $contact, $contact);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result) ?: null;
}

function getUserByField($conn, $field, $value)
{
    $allowedFields = ['phone', 'email', 'verification_token'];
    if (!in_array($field, $allowedFields, true)) {
        return null;
    }

    $sql = "SELECT * FROM users WHERE {$field} = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $value);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result) ?: null;
}

function createSessionUser(array $user)
{
    return [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'phone' => $user['phone'] ?: null,
        'email' => $user['email'] ?: null,
        'role' => $user['role'],
    ];
}

function normalizeSessionUser()
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return false;
    }

    $user = $_SESSION['user'];

    if (!isset($user['id']) || !isset($user['full_name']) || !isset($user['role'])) {
        session_unset();
        session_destroy();
        return false;
    }

    $hasPhone = isset($user['phone']) && $user['phone'] !== null && $user['phone'] !== '';
    $hasEmail = isset($user['email']) && $user['email'] !== null && $user['email'] !== '';

    if (!$hasPhone && !$hasEmail) {
        session_unset();
        session_destroy();
        return false;
    }

    $_SESSION['user']['phone'] = $hasPhone ? $user['phone'] : null;
    $_SESSION['user']['email'] = $hasEmail ? $user['email'] : null;

    return true;
}

function redirectByRole()
{
    if (!isset($_SESSION['user']) || !normalizeSessionUser()) {
        header("Location: /badminton-manager/auth/login.php");
        exit();
    }

    if ($_SESSION['user']['role'] === 'admin') {
        header("Location: /badminton-manager/admin/index.php");
        exit();
    }

    header("Location: /badminton-manager/customer/home.php");
    exit();
}

function generateVerificationToken()
{
    return bin2hex(random_bytes(32));
}

function getVerificationExpiryTime()
{
    return date('Y-m-d H:i:s', time() + 24 * 60 * 60);
}

function isVerificationExpired($expiresAt)
{
    if (empty($expiresAt)) {
        return true;
    }

    return strtotime($expiresAt) < time();
}

function maskContact($contact)
{
    $type = detectContactType($contact);

    if ($type === 'email') {
        [$local, $domain] = explode('@', $contact, 2);
        $visible = substr($local, 0, 2);
        return $visible . str_repeat('*', max(strlen($local) - 2, 2)) . '@' . $domain;
    }

    if ($type === 'phone') {
        $length = strlen($contact);
        if ($length <= 4) {
            return $contact;
        }

        return substr($contact, 0, 3) . str_repeat('*', max($length - 5, 2)) . substr($contact, -2);
    }

    return $contact;
}

function getVerificationLogPath()
{
    return dirname(__DIR__) . '/storage/email_verification.log';
}

function getEmailConfig()
{
    $configFile = dirname(__DIR__) . '/config/email.php';
    $fileConfig = [];

    if (is_file($configFile)) {
        $loadedConfig = require $configFile;
        if (is_array($loadedConfig)) {
            $fileConfig = $loadedConfig;
        }
    }

    return [
        'transport' => $fileConfig['transport'] ?? 'smtp',
        'host' => trim((string) ($fileConfig['host'] ?? '')),
        'port' => (int) ($fileConfig['port'] ?? 587),
        'encryption' => strtolower(trim((string) ($fileConfig['encryption'] ?? 'tls'))),
        'username' => trim((string) ($fileConfig['username'] ?? '')),
        'password' => (string) ($fileConfig['password'] ?? ''),
        'from_email' => trim((string) ($fileConfig['from_email'] ?? '')),
        'from_name' => trim((string) ($fileConfig['from_name'] ?? 'Badminton Manager')),
        'timeout' => (int) ($fileConfig['timeout'] ?? 20),
    ];
}

function isPlaceholderEmailConfigValue($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return false;
    }

    $patterns = [
        'your_',
        'example.com',
        'smtp.example.com',
        'app_password',
    ];

    foreach ($patterns as $pattern) {
        if (stripos($value, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

function writeVerificationLog($email, $verificationUrl, $channel)
{
    $logPath = getVerificationLogPath();
    $directory = dirname($logPath);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $line = sprintf(
        "[%s] channel=%s email=%s url=%s%s",
        date('Y-m-d H:i:s'),
        $channel,
        $email,
        $verificationUrl,
        PHP_EOL
    );

    file_put_contents($logPath, $line, FILE_APPEND);
}

function smtpReadResponse($socket)
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }

        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtpExpectCode($response, array $allowedCodes)
{
    $code = (int) substr(trim($response), 0, 3);
    return in_array($code, $allowedCodes, true);
}

function smtpSendCommand($socket, $command, array $allowedCodes)
{
    fwrite($socket, $command . "\r\n");
    $response = smtpReadResponse($socket);

    if (!smtpExpectCode($response, $allowedCodes)) {
        throw new RuntimeException(trim($response) !== '' ? trim($response) : 'SMTP command failed.');
    }

    return $response;
}

function smtpSendData($socket, $data)
{
    fwrite($socket, $data . "\r\n.\r\n");
    $response = smtpReadResponse($socket);

    if (!smtpExpectCode($response, [250])) {
        throw new RuntimeException(trim($response) !== '' ? trim($response) : 'SMTP DATA failed.');
    }
}

function sendSmtpMail($toEmail, $subject, $bodyText)
{
    $config = getEmailConfig();

    if ($config['transport'] !== 'smtp') {
        return [
            'success' => false,
            'error' => 'Transport email hiện tại không được hỗ trợ.',
        ];
    }

    if (
        $config['host'] === '' ||
        $config['username'] === '' ||
        $config['password'] === '' ||
        $config['from_email'] === ''
    ) {
        return [
            'success' => false,
            'error' => 'Thiếu cấu hình SMTP trong config/email.php.',
        ];
    }

    if (
        isPlaceholderEmailConfigValue($config['host']) ||
        isPlaceholderEmailConfigValue($config['username']) ||
        isPlaceholderEmailConfigValue($config['password']) ||
        isPlaceholderEmailConfigValue($config['from_email'])
    ) {
        return [
            'success' => false,
            'error' => 'config/email.php vẫn đang dùng giá trị mẫu. Hãy thay bằng cấu hình SMTP thật.',
        ];
    }

    $scheme = $config['encryption'] === 'ssl' ? 'ssl://' : '';
    $remote = $scheme . $config['host'] . ':' . $config['port'];
    $timeout = max(5, $config['timeout']);

    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return [
            'success' => false,
            'error' => 'Không kết nối được SMTP: ' . $errstr,
        ];
    }

    stream_set_timeout($socket, $timeout);

    try {
        $response = smtpReadResponse($socket);
        if (!smtpExpectCode($response, [220])) {
            throw new RuntimeException(trim($response) !== '' ? trim($response) : 'SMTP greeting failed.');
        }

        smtpSendCommand($socket, 'EHLO localhost', [250]);

        if ($config['encryption'] === 'tls') {
            smtpSendCommand($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Không bật được STARTTLS.');
            }

            smtpSendCommand($socket, 'EHLO localhost', [250]);
        }

        smtpSendCommand($socket, 'AUTH LOGIN', [334]);
        smtpSendCommand($socket, base64_encode($config['username']), [334]);
        smtpSendCommand($socket, base64_encode($config['password']), [235]);
        smtpSendCommand($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
        smtpSendCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        smtpSendCommand($socket, 'DATA', [354]);

        $headers = [
            'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
            'To: <' . $toEmail . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $bodyText;
        smtpSendData($socket, $message);
        smtpSendCommand($socket, 'QUIT', [221]);
        fclose($socket);

        return [
            'success' => true,
        ];
    } catch (Throwable $e) {
        fclose($socket);

        return [
            'success' => false,
            'error' => 'SMTP gửi mail thất bại: ' . $e->getMessage(),
        ];
    }
}

function buildVerificationUrl($email, $token)
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = '/badminton-manager/auth/verify_email.php';

    return $scheme . '://' . $host . $path . '?email=' . urlencode($email) . '&token=' . urlencode($token);
}

function sendVerificationEmail($email, $token)
{
    $verificationUrl = buildVerificationUrl($email, $token);
    $subject = 'Xác minh email tài khoản badminton';
    $message = "Chào bạn,\n\nVui lòng bấm vào liên kết sau để xác minh tài khoản:\n{$verificationUrl}\n\nLiên kết có hiệu lực trong 24 giờ.";
    $sendResult = sendSmtpMail($email, $subject, $message);

    if ($sendResult['success']) {
        return [
            'success' => true,
            'notice' => 'Email xác minh đã được gửi qua SMTP. Vui lòng kiểm tra hộp thư của bạn.',
        ];
    }

    writeVerificationLog($email, $verificationUrl, 'smtp_fallback_log');

    return [
        'success' => true,
        'notice' => ($sendResult['error'] ?? 'Không gửi được email.') . ' Liên kết xác minh đã được ghi vào storage/email_verification.log.',
    ];
}

function getPaymentConfig()
{
    $configFile = dirname(__DIR__) . '/config/payment.php';
    $fileConfig = [];

    if (is_file($configFile)) {
        $loadedConfig = require $configFile;
        if (is_array($loadedConfig)) {
            $fileConfig = $loadedConfig;
        }
    }

    return [
        'bank_id' => trim((string) ($fileConfig['bank_id'] ?? '')),
        'account_no' => trim((string) ($fileConfig['account_no'] ?? '')),
        'account_name' => trim((string) ($fileConfig['account_name'] ?? '')),
        'template' => trim((string) ($fileConfig['template'] ?? 'compact2')),
    ];
}

function isPlaceholderPaymentConfigValue($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return false;
    }

    $patterns = [
        'your_',
        'example',
        'xxxxxxxx',
    ];

    foreach ($patterns as $pattern) {
        if (stripos($value, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

function isPaymentQrConfigured()
{
    return getPaymentQrConfigIssue() === null;
}

function getPaymentQrConfigIssue()
{
    $config = getPaymentConfig();

    if (
        $config['bank_id'] === '' ||
        $config['account_no'] === '' ||
        $config['account_name'] === ''
    ) {
        return 'Thiếu thông tin nhận chuyển khoản trong config/payment.php.';
    }

    if (
        isPlaceholderPaymentConfigValue($config['bank_id']) ||
        isPlaceholderPaymentConfigValue($config['account_no']) ||
        isPlaceholderPaymentConfigValue($config['account_name'])
    ) {
        return 'config/payment.php vẫn đang dùng dữ liệu mẫu.';
    }

    if ($config['bank_id'] === $config['account_no']) {
        return 'bank_id đang trùng với số tài khoản. bank_id phải là mã ngân hàng VietQR, không phải số tài khoản.';
    }

    return null;
}

function buildPaymentQrUrl($amount, $reference)
{
    if (!isPaymentQrConfigured()) {
        return null;
    }

    $config = getPaymentConfig();
    $baseUrl = sprintf(
        'https://img.vietqr.io/image/%s-%s-%s.png',
        rawurlencode($config['bank_id']),
        rawurlencode($config['account_no']),
        rawurlencode($config['template'])
    );

    $query = http_build_query([
        'amount' => (int) round((float) $amount),
        'addInfo' => $reference,
        'accountName' => $config['account_name'],
    ]);

    return $baseUrl . '?' . $query;
}

function getPaymentMethodLabel($paymentMethod)
{
    if ($paymentMethod === 'bank_transfer') {
        return 'Chuyển khoản';
    }

    return 'Tiền mặt';
}
