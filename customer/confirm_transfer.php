<?php
require_once '../includes/customer_auth.php';
header('Location: /badminton-manager/customer/my_bookings.php?error=payment_confirmation_disabled');
exit();
