<?php
// Prevent any output before JSON response
ob_start();

// Ensure JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0); // Suppress on-screen errors to avoid HTML output
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

try {
    // Database connection
    require_once 'config/db.php'; // Ensure this file exists and defines $pdo

    // Get parameters
    $period = $_GET['period'] ?? 'daily';
    $date = $_GET['date'] ?? date('Y-m-d');

    // Prepare date range
    switch ($period) {
        case 'daily':
            $startDate = $date;
            $endDate = $date;
            break;
        case 'monthly':
            $startDate = "$date-01";
            $endDate = date('Y-m-t', strtotime($startDate));
            break;
        case 'yearly':
            $startDate = "$date-01-01";
            $endDate = "$date-12-31";
            break;
        default:
            throw new Exception('Invalid period');
    }

    // Fetch appointments
    $query = "
        SELECT a.*, p.name as patient_name, p.email as patient_email, p.photo as patient_image, s.service_name as treatment
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.appointment_date BETWEEN ? AND ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for DataTables
    $data = [];
    foreach ($appointments as $row) {
        $data[] = [
            'patient' => $row['patient_name'],
            'patient_name' => $row['patient_name'],
            'patient_email' => $row['patient_email'],
            'patient_image' => $row['patient_image'],
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'treatment' => $row['treatment'],
            'status' => $row['status']
        ];
    }

    // Clear any output buffer
    ob_clean();
    
    // Return valid JSON response
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    // Log error
    error_log('Error in get_appointments.php: ' . $e->getMessage());
    
    // Return JSON error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// End output buffering and send
ob_end_flush();
exit;
?>