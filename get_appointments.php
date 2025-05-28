<?php
// Start output buffering to prevent unwanted output
ob_start();

// Ensure JSON response
header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

try {
    // Database connection
    require_once 'config/db.php'; // Ensure this file defines $pdo

    // Verify PDO connection
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Database connection not initialized');
    }

    // Get and sanitize parameters
    $period = filter_input(INPUT_GET, 'period', FILTER_SANITIZE_STRING) ?? 'daily';
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?? date('Y-m-d');

    // Validate period
    if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
        throw new Exception('Invalid period parameter');
    }

    // Validate date format
    if ($period === 'daily' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid daily date format');
    } elseif ($period === 'monthly' && !preg_match('/^\d{4}-\d{2}$/', $date)) {
        throw new Exception('Invalid monthly date format');
    } elseif ($period === 'yearly' && !preg_match('/^\d{4}$/', $date)) {
        throw new Exception('Invalid yearly date format');
    }

    // Debug log
    error_log("Calling GetAppointmentsByPeriod with period: $period, date: $date");

    // Call stored procedure
    $stmt = $pdo->prepare("CALL GetAppointmentsByPeriod(?, ?, @start_date, @end_date)");
    if (!$stmt->execute([$period, $date])) {
        throw new Exception('Failed to execute stored procedure');
    }

    // Fetch all results and close cursor
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); // Close the result set to allow subsequent queries

    // Fetch output parameters
    $stmt = $pdo->query("SELECT @start_date AS start_date, @end_date AS end_date");
    $dateRange = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Date range: " . ($dateRange['start_date'] ?? 'null') . " to " . ($dateRange['end_date'] ?? 'null'));

    // Format data for DataTables
    $data = [];
    foreach ($appointments as $row) {
        $data[] = [
            'patient' => htmlspecialchars($row['patient_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'patient_name' => htmlspecialchars($row['patient_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'patient_email' => htmlspecialchars($row['patient_email'] ?? '', ENT_QUOTES, 'UTF-8'),
            'patient_image' => htmlspecialchars($row['patient_image'] ?? '', ENT_QUOTES, 'UTF-8'),
            'appointment_date' => $row['appointment_date'] ?? '',
            'appointment_time' => $row['appointment_time'] ?? '',
            'treatment' => htmlspecialchars($row['treatment'] ?? '', ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8')
        ];
    }

    // Debug log
    error_log("Appointments fetched: " . count($data) . " (using INNER JOIN, only valid patient and service records included)");

    // Clear output buffer and send JSON response
    ob_end_clean();
    echo json_encode(['data' => $data], JSON_THROW_ON_ERROR);

} catch (Exception $e) {
    // Log error
    error_log('Error in get_appointments.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());

    // Clear output buffer and send JSON error response
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_THROW_ON_ERROR);
}

exit;
?>