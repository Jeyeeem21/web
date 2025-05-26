<?php
session_start();
require_once 'config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log session data for debugging
error_log("Session data: " . print_r($_SESSION, true));
error_log("POST data: " . print_r($_POST, true));

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'doctor') {
    error_log("Unauthorized access attempt - User role: " . ($_SESSION['user_role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if staff_id is set in session
if (!isset($_SESSION['staff_id'])) {
    error_log("Staff ID not set in session");
    echo json_encode(['success' => false, 'message' => 'Staff ID not found in session']);
    exit();
}

// Check if required parameters are present
if (!isset($_POST['appointment_id']) || !isset($_POST['status'])) {
    error_log("Missing parameters - POST data: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$appointmentId = $_POST['appointment_id'];
$status = $_POST['status'];

// Validate status
$allowedStatuses = ['Completed', 'Cancelled', 'Scheduled', 'Pending'];
if (!in_array($status, $allowedStatuses)) {
    error_log("Invalid status attempt - Status: " . $status);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // First verify that this appointment belongs to the logged-in doctor
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND staff_id = ?");
    $stmt->execute([$appointmentId, $_SESSION['staff_id']]);
    
    if (!$stmt->fetch()) {
        error_log("Appointment not found or unauthorized - Appointment ID: " . $appointmentId . ", Staff ID: " . $_SESSION['staff_id']);
        echo json_encode(['success' => false, 'message' => 'Appointment not found or unauthorized']);
        exit();
    }
    
    // Update the appointment status
    $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $appointmentId]);
    
    if ($result) {
        error_log("Appointment status updated successfully - ID: " . $appointmentId . ", Status: " . $status);
        echo json_encode(['success' => true]);
    } else {
        error_log("Failed to update appointment status - ID: " . $appointmentId . ", Status: " . $status);
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment status']);
    }
} catch (PDOException $e) {
    error_log("Database error updating appointment status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?> 