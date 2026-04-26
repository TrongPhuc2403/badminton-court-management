<?php

function ensureColumnExists($conn, $table, $column, $definition)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);

    if ($table === '' || $column === '') {
        return;
    }

    $checkSql = "SELECT 1
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, 'ss', $table, $column);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($checkStmt);

    if (!$exists) {
        mysqli_query($conn, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function ensureLoyaltyTables($conn)
{
    ensureColumnExists($conn, 'users', 'loyalty_points', 'INT NOT NULL DEFAULT 0');
    ensureColumnExists($conn, 'bookings', 'loyalty_points_credited', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumnExists($conn, 'bookings', 'loyalty_points_awarded', 'INT NOT NULL DEFAULT 0');

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS loyalty_point_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            booking_id INT DEFAULT NULL,
            redemption_id INT DEFAULT NULL,
            points_change INT NOT NULL,
            balance_after INT NOT NULL,
            description VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_loyalty_transactions_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS loyalty_redemptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reward_type ENUM('free_hour', 'shuttlecock') NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            points_used INT NOT NULL,
            status ENUM('pending', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_loyalty_redemptions_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function calculateLoyaltyPointsFromAmount($amount)
{
    return max(0, (int) floor(((float) $amount) / 1000));
}

function getLoyaltyRewardCatalog()
{
    return [
        'free_hour' => [
            'label' => '1 giờ chơi miễn phí',
            'points_cost' => 1000,
        ],
        'shuttlecock' => [
            'label' => '1 trái cầu',
            'points_cost' => 400,
        ],
    ];
}

function getLoyaltyRewardOption($rewardType)
{
    $catalog = getLoyaltyRewardCatalog();
    return $catalog[$rewardType] ?? null;
}

function getLoyaltyRewardLabel($rewardType)
{
    $option = getLoyaltyRewardOption($rewardType);
    return $option['label'] ?? 'Quà đổi điểm';
}

function getLoyaltyRewardPointsCost($rewardType)
{
    $option = getLoyaltyRewardOption($rewardType);
    return (int) ($option['points_cost'] ?? 0);
}

function getLoyaltyRedemptionStatusLabel($status)
{
    if ($status === 'fulfilled') {
        return 'Đã hoàn tất';
    }

    if ($status === 'cancelled') {
        return 'Đã hủy';
    }

    return 'Đang chờ xử lý';
}

function getLoyaltyRedemptionDescription($rewardType, $quantity)
{
    $quantity = max(1, (int) $quantity);
    return $quantity . ' x ' . getLoyaltyRewardLabel($rewardType);
}

function getPaymentStatusDisplayLabel($paymentStatus, $paymentMethod)
{
    if ($paymentMethod === 'cash') {
        return $paymentStatus === 'paid' ? 'Đã cọc 30%' : 'Chưa cọc';
    }

    return $paymentStatus === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán';
}

function awardBookingLoyaltyPointsIfEligible($conn, $bookingId)
{
    $bookingId = (int) $bookingId;
    if ($bookingId <= 0) {
        return 0;
    }

    ensureLoyaltyTables($conn);
    mysqli_begin_transaction($conn);

    try {
        $bookingSql = "SELECT id, user_id, total_price, payment_method, payment_status,
                              loyalty_points_credited, loyalty_points_awarded, payment_reference
                       FROM bookings
                       WHERE id = ?
                       LIMIT 1
                       FOR UPDATE";
        $bookingStmt = mysqli_prepare($conn, $bookingSql);
        mysqli_stmt_bind_param($bookingStmt, 'i', $bookingId);
        mysqli_stmt_execute($bookingStmt);
        $bookingResult = mysqli_stmt_get_result($bookingStmt);
        $booking = mysqli_fetch_assoc($bookingResult);
        mysqli_stmt_close($bookingStmt);

        if (!$booking || ($booking['payment_status'] ?? '') !== 'paid' || (int) ($booking['loyalty_points_credited'] ?? 0) === 1) {
            mysqli_commit($conn);
            return 0;
        }

        $paidAmount = getBookingImmediatePaymentAmount(
            (float) ($booking['total_price'] ?? 0),
            (string) ($booking['payment_method'] ?? '')
        );
        $pointsToAward = calculateLoyaltyPointsFromAmount($paidAmount);

        $userSql = "SELECT loyalty_points FROM users WHERE id = ? LIMIT 1 FOR UPDATE";
        $userStmt = mysqli_prepare($conn, $userSql);
        mysqli_stmt_bind_param($userStmt, 'i', $booking['user_id']);
        mysqli_stmt_execute($userStmt);
        $userResult = mysqli_stmt_get_result($userStmt);
        $user = mysqli_fetch_assoc($userResult);
        mysqli_stmt_close($userStmt);

        if (!$user) {
            mysqli_rollback($conn);
            return 0;
        }

        $newBalance = (int) ($user['loyalty_points'] ?? 0) + $pointsToAward;

        $updateUserSql = "UPDATE users SET loyalty_points = ? WHERE id = ?";
        $updateUserStmt = mysqli_prepare($conn, $updateUserSql);
        mysqli_stmt_bind_param($updateUserStmt, 'ii', $newBalance, $booking['user_id']);
        mysqli_stmt_execute($updateUserStmt);
        mysqli_stmt_close($updateUserStmt);

        $updateBookingSql = "UPDATE bookings
                             SET loyalty_points_credited = 1, loyalty_points_awarded = ?
                             WHERE id = ? AND loyalty_points_credited = 0";
        $updateBookingStmt = mysqli_prepare($conn, $updateBookingSql);
        mysqli_stmt_bind_param($updateBookingStmt, 'ii', $pointsToAward, $bookingId);
        mysqli_stmt_execute($updateBookingStmt);
        $updatedRows = mysqli_stmt_affected_rows($updateBookingStmt);
        mysqli_stmt_close($updateBookingStmt);

        if ($updatedRows !== 1) {
            mysqli_rollback($conn);
            return 0;
        }

        $description = 'Cộng điểm từ thanh toán booking ' . ($booking['payment_reference'] ?: '#' . $bookingId);
        $transactionSql = "INSERT INTO loyalty_point_transactions (
                                user_id, booking_id, redemption_id, points_change, balance_after, description
                           ) VALUES (?, ?, NULL, ?, ?, ?)";
        $transactionStmt = mysqli_prepare($conn, $transactionSql);
        mysqli_stmt_bind_param(
            $transactionStmt,
            'iiiis',
            $booking['user_id'],
            $bookingId,
            $pointsToAward,
            $newBalance,
            $description
        );
        mysqli_stmt_execute($transactionStmt);
        mysqli_stmt_close($transactionStmt);

        mysqli_commit($conn);
        return $pointsToAward;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return 0;
    }
}

function syncPendingLoyaltyAwards($conn, $limit = 50)
{
    ensureLoyaltyTables($conn);
    $limit = max(1, (int) $limit);

    $sql = "SELECT id
            FROM bookings
            WHERE payment_status = 'paid'
              AND loyalty_points_credited = 0
            ORDER BY id ASC
            LIMIT {$limit}";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        awardBookingLoyaltyPointsIfEligible($conn, (int) $row['id']);
    }
}
