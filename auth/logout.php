<?php
session_start();
$_SESSION = [];
session_unset();
session_destroy();
header("Location: /badminton-manager/auth/login.php");
exit();