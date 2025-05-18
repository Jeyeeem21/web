<?php
// Prevent any output buffering
ob_clean();

// Include database connection
require_once 'config/db.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($data) {
    ob_clean(); // Clear any output buffers
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
}

// If no action is specified or action is invalid
sendJsonResponse(['error' => 'Invalid request']);
?> 