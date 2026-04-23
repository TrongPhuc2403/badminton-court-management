<?php
require_once __DIR__ . '/auth.php';

if ($_SESSION['user']['role'] !== 'customer') {
    header("Location: /badminton-manager/auth/login.php");
    exit();
}
?>