<?php
require_once '../includes/admin_auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/loyalty.php';

ensureLoyaltyTables($conn);

$id = (int) ($_GET['id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));

if ($id <= 0 || !in_array($action, ['fulfill', 'cancel'], true)) {
    header('Location: /badminton-manager/admin/rewards.php');
    exit();
}

mysqli_begin_transaction($conn);

try {
    $sql = "SELECT * FROM loyalty_redemptions WHERE id = ? LIMIT 1 FOR UPDATE";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $redemption = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$redemption || $redemption['status'] !== 'pending') {
        throw new RuntimeException('Yêu cầu không hợp lệ.');
    }

    if ($action === 'fulfill') {
        $updateSql = "UPDATE loyalty_redemptions SET status = 'fulfilled' WHERE id = ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, 'i', $id);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    } else {
        $userSql = "SELECT loyalty_points FROM users WHERE id = ? LIMIT 1 FOR UPDATE";
        $userStmt = mysqli_prepare($conn, $userSql);
        mysqli_stmt_bind_param($userStmt, 'i', $redemption['user_id']);
        mysqli_stmt_execute($userStmt);
        $userResult = mysqli_stmt_get_result($userStmt);
        $user = mysqli_fetch_assoc($userResult);
        mysqli_stmt_close($userStmt);

        if (!$user) {
            throw new RuntimeException('Không tìm thấy khách hàng.');
        }

        $newBalance = (int) ($user['loyalty_points'] ?? 0) + (int) $redemption['points_used'];

        $updateUserSql = "UPDATE users SET loyalty_points = ? WHERE id = ?";
        $updateUserStmt = mysqli_prepare($conn, $updateUserSql);
        mysqli_stmt_bind_param($updateUserStmt, 'ii', $newBalance, $redemption['user_id']);
        mysqli_stmt_execute($updateUserStmt);
        mysqli_stmt_close($updateUserStmt);

        $updateRedemptionSql = "UPDATE loyalty_redemptions SET status = 'cancelled' WHERE id = ?";
        $updateRedemptionStmt = mysqli_prepare($conn, $updateRedemptionSql);
        mysqli_stmt_bind_param($updateRedemptionStmt, 'i', $id);
        mysqli_stmt_execute($updateRedemptionStmt);
        mysqli_stmt_close($updateRedemptionStmt);

        $description = 'Hoàn điểm do hủy yêu cầu đổi quà #' . $id;
        $insertTransactionSql = "INSERT INTO loyalty_point_transactions (
                                    user_id, booking_id, redemption_id, points_change, balance_after, description
                                 ) VALUES (?, NULL, ?, ?, ?, ?)";
        $insertTransactionStmt = mysqli_prepare($conn, $insertTransactionSql);
        $pointsChange = (int) $redemption['points_used'];
        mysqli_stmt_bind_param(
            $insertTransactionStmt,
            'iiiis',
            $redemption['user_id'],
            $id,
            $pointsChange,
            $newBalance,
            $description
        );
        mysqli_stmt_execute($insertTransactionStmt);
        mysqli_stmt_close($insertTransactionStmt);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
}

header('Location: /badminton-manager/admin/rewards.php');
exit();
