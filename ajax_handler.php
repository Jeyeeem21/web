<?php
// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'D:/xampp/htdocs/Clinic/logs/php_errors.log');

// Include database connection
require_once 'config/db.php';

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Function to send JSON response
function sendJsonResponse($data) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode($data);
    exit();
}

if (isset($_GET['action'])) {
    // Get staff details for editing
    if ($_GET['action'] == 'get' && isset($_GET['id'])) {
        $id = $_GET['id'];
        try {
            $stmt = $pdo->prepare("SELECT s.*, dp.doctor_position 
                                  FROM staff s 
                                  LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                                  WHERE s.id = ?");
            $stmt->execute([$id]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($staff) {
                sendJsonResponse([
                    'id' => $staff['id'],
                    'name' => $staff['name'],
                    'address' => $staff['address'],
                    'contact' => $staff['contact'],
                    'gmail' => $staff['gmail'],
                    'birthdate' => $staff['birthdate'],
                    'age' => $staff['age'],
                    'gender' => $staff['gender'],
                    'role' => $staff['role'],
                    'doctor_position_id' => $staff['doctor_position_id'],
                    'assistant_id' => $staff['assistant_id'],
                    'photo' => $staff['photo']
                ]);
            } else {
                sendJsonResponse(['error' => 'Staff not found']);
            }
        } catch (PDOException $e) {
            sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // Get user details for editing
    if ($_GET['action'] == 'get_user' && isset($_GET['id'])) {
        $id = $_GET['id'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                sendJsonResponse($user);
            } else {
                sendJsonResponse(['error' => 'User not found']);
            }
        } catch (PDOException $e) {
            sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // Delete user
    if ($_GET['action'] == 'delete_user' && isset($_GET['id'])) {
        $id = $_GET['id'];
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            sendJsonResponse(['success' => true]);
        } catch (PDOException $e) {
            sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Handle delete schedule action
    if ($_GET['action'] == 'delete_schedule' && isset($_GET['id'])) {
        $id = $_GET['id'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM doctor_schedule WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $pdo->commit();
            sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

// If no action is specified or action is invalid
sendJsonResponse(['error' => 'Invalid request']);
?> 