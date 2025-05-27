<?php
// Prevent any output before headers
ob_start();

// Set proper content type
header('Content-Type: application/json');

// Start session and include database
session_start();
require_once 'config/db.php';

// Disable error display but enable logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Function to send JSON response
function sendJsonResponse($success, $message) {
    ob_clean(); // Clear any previous output
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'patient') {
    sendJsonResponse(false, 'Unauthorized access');
}

// Check if all required fields are present
if (!isset($_POST['current_password']) || !isset($_POST['new_password']) || !isset($_POST['confirm_password']) || !isset($_POST['patient_id'])) {
    sendJsonResponse(false, 'All fields are required');
}

$currentPassword = trim($_POST['current_password']);
$newPassword = trim($_POST['new_password']);
$confirmPassword = trim($_POST['confirm_password']);
$patientId = trim($_POST['patient_id']);

// Validate password length
if (strlen($newPassword) < 8) {
    sendJsonResponse(false, 'New password must be at least 8 characters long');
}

// Validate passwords match
if ($newPassword !== $confirmPassword) {
    sendJsonResponse(false, 'New password and confirm password do not match');
}

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT u.* FROM users u WHERE u.patient_id = ?");
    $stmt->execute([$patientId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJsonResponse(false, 'User not found');
    }

    // Verify current password
    if (!password_verify($currentPassword, $user['pass'])) {
        sendJsonResponse(false, 'Current password is incorrect');
    }

    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE users SET pass = ? WHERE patient_id = ?");
    $result = $stmt->execute([$hashedPassword, $patientId]);

    if ($result) {
        sendJsonResponse(true, 'Password updated successfully');
    } else {
        sendJsonResponse(false, 'Failed to update password');
    }
} catch (PDOException $e) {
    error_log("Password update error: " . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred. Please try again.');
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    sendJsonResponse(false, 'An unexpected error occurred. Please try again.');
}

// Clean any remaining output buffer
ob_end_clean();
?> 