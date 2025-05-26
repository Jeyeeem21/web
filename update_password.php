<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if all required fields are present
if (!isset($_POST['user_id']) || !isset($_POST['current_password']) || 
    !isset($_POST['new_password']) || !isset($_POST['confirm_password'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

$userId = $_POST['user_id'];
$currentPassword = $_POST['current_password'];
$newPassword = $_POST['new_password'];
$confirmPassword = $_POST['confirm_password'];

// Validate passwords match
if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match']);
    exit();
}

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND staff_id = ?");
    $stmt->execute([$userId, $_SESSION['staff_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    // Verify current password
    if (!password_verify($currentPassword, $user['pass'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE users SET pass = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);

    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} catch (PDOException $e) {
    error_log("Password update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating password. Please try again.']);
}
?> 