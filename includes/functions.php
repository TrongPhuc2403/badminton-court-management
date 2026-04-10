<?php

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($number)
{
    return number_format((float)$number, 0, ',', '.') . ' VNĐ';
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

    if ($endHour <= $startHour) {
        return false;
    }

    return true;
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

function normalizeSessionUser()
{
    if (!isset($_SESSION['user'])) {
        return false;
    }

    if (!is_array($_SESSION['user'])) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        return false;
    }

    if (
        !isset($_SESSION['user']['id']) ||
        !isset($_SESSION['user']['full_name']) ||
        !isset($_SESSION['user']['phone']) ||
        !isset($_SESSION['user']['role'])
    ) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        return false;
    }

    return true;
}

function redirectByRole()
{
    if (!isset($_SESSION['user'])) {
        header("Location: /badminton-manager/auth/login.php");
        exit();
    }

    if (!normalizeSessionUser()) {
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
?>