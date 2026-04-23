<?php
session_start();
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user']) || !normalizeSessionUser()) {
    header("Location: /badminton-manager/auth/login.php");
    exit();
}
?>