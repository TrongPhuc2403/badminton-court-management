<?php
session_start();
require_once 'includes/functions.php';

if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])) {
    redirectByRole();
}

header("Location: /badminton-manager/auth/login.php");
exit();