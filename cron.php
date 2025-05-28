<?php
require_once 'config/db.php';

// Set timezone to Philippine Standard Time (PHT, UTC+8)
date_default_timezone_set('Asia/Manila');

function cancelOverdueAppointments($pdo) {
    // Same function as above
}

$cancelledCount = cancelOverdueAppointments($pdo);
if ($cancelledCount > 0) {
    error_log("Cron: Cancelled $cancelledCount overdue appointments");
} else {
    error_log("Cron: No overdue appointments to cancel");
}