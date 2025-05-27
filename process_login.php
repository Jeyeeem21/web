<?php
session_start();
require_once 'config/db.php';
//process login register
// Function to log audit events
function logAudit($pdo, $user_id, $username, $action, $status, $ip_address) {
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $action, $status, $ip_address]);
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields";
        // Log failed attempt due to missing fields
        logAudit($pdo, null, $username, 'Login Attempt', 'Failure: Missing Fields', $ip_address);
        header("Location: login.php");
        exit();
    }

    try {
        // Check if user exists first
        $stmt = $pdo->prepare("SELECT u.*, s.role, s.id as staff_id, p.id as patient_id 
                              FROM users u 
                              LEFT JOIN staff s ON u.staff_id = s.id 
                              LEFT JOIN patients p ON u.patient_id = p.id 
                              WHERE u.username = ? AND u.status = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            // User found, now verify password
            if (password_verify($password, $user['pass'])) {
                // Password is correct
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Log successful login
                logAudit($pdo, $user['id'], $user['username'], 'Login', 'Success', $ip_address);

                // Set role-specific session variables and redirect
                if ($user['patient_id']) {
                    // Patient login
                    $_SESSION['user_role'] = 'patient';
                    $_SESSION['patient_id'] = $user['patient_id'];
                    header("Location: patient_dashboard.php");
                } else if ($user['staff_id']) {
                    // Staff login (doctor or assistant)
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['staff_id'] = $user['staff_id'];
                    
                    if ($user['role'] === 'doctor') {
                        header("Location: doctor_dashboard.php");
                    } else if ($user['role'] === 'assistant') {
                        // Get the doctor this assistant is assigned to
                        $stmt = $pdo->prepare("SELECT d.doctor_id, s.name as doctor_name 
                                             FROM doctor d 
                                             JOIN staff s ON d.doctor_id = s.id 
                                             WHERE d.assistant_id = ?");
                        $stmt->execute([$user['staff_id']]);
                        $doctor = $stmt->fetch();
                        
                        if ($doctor) {
                            $_SESSION['assigned_doctor_id'] = $doctor['doctor_id'];
                            $_SESSION['assigned_doctor_name'] = $doctor['doctor_name'];
                            header("Location: doctor_dashboard.php");
                        } else {
                            // Log failed attempt due to no doctor assigned
                            logAudit($pdo, $user['id'], $user['username'], 'Login Attempt', 'Failure: No Doctor Assigned', $ip_address);
                            // Set generic error for user display
                            $_SESSION['error'] = "Invalid username or password";
                            header("Location: login.php");
                        }
                    }
                }
                exit();
            } else {
                // User found, but incorrect password
                // Set generic error for user display
                $_SESSION['error'] = "Invalid username or password";
                // Log failed attempt due to incorrect password
                logAudit($pdo, $user['id'], $user['username'], 'Login Attempt', 'Failure: Incorrect Password', $ip_address);
            }
        } else {
            // Username not found
            // Set generic error for user display
            $_SESSION['error'] = "Invalid username or password";
            // Log failed attempt due to username not found
            logAudit($pdo, null, $username, 'Login Attempt', 'Failure: Username Not Found', $ip_address);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "An error occurred. Please try again later."; // Keep specific for technical errors
        error_log($e->getMessage());
        // Log database error
        logAudit($pdo, null, $username, 'Login Attempt', 'Failure: Database Error', $ip_address);
    }

    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
} 