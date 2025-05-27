<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

// Debug session information
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'doctor') {
    error_log("Unauthorized access attempt. Session role: " . ($_SESSION['user_role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    // Debug POST data
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));

    $staff_id = $_POST['staff_id'];
    $name = $_POST['name'];
    $gmail = $_POST['email'];
    $contact = $_POST['phone'];
    $address = $_POST['address'];
    $gender = $_POST['gender'];
    
    try {
        $pdo->beginTransaction();

        // Update staff information
        $stmt = $pdo->prepare("
            UPDATE staff 
            SET name = ?, gmail = ?, contact = ?, address = ?, gender = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $gmail, $contact, $address, $gender, $staff_id]);

        // Handle photo upload if provided
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['photo'];
            $photo_name = time() . '_' . $photo['name'];
            $photo_path = 'uploads/staff/' . $photo_name;
            
            if (move_uploaded_file($photo['tmp_name'], $photo_path)) {
                $stmt = $pdo->prepare("UPDATE staff SET photo = ? WHERE id = ?");
                $stmt->execute([$photo_path, $staff_id]);
            }
        }

        // Handle schedule update
        if (isset($_POST['rest_day']) && !empty($_POST['rest_day'])) {
            $rest_day = $_POST['rest_day'];
            $start_time = $_POST['start_time'] . ':00';
            $end_time = $_POST['end_time'] . ':00';

            // Check if schedule already exists
            $stmt = $pdo->prepare("SELECT id FROM doctor_schedule WHERE doctor_id = ?");
            $stmt->execute([$staff_id]);
            $existing_schedule = $stmt->fetch();

            if ($existing_schedule) {
                // Update existing schedule
                $stmt = $pdo->prepare("
                    UPDATE doctor_schedule 
                    SET rest_day = ?, start_time = ?, end_time = ?
                    WHERE doctor_id = ?
                ");
                $stmt->execute([$rest_day, $start_time, $end_time, $staff_id]);
            } else {
                // Insert new schedule
                $stmt = $pdo->prepare("
                    INSERT INTO doctor_schedule (doctor_id, rest_day, start_time, end_time, created_at)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$staff_id, $rest_day, $start_time, $end_time]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating profile: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
    }
    exit;
} else {
    error_log("Invalid request method or missing action. Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 