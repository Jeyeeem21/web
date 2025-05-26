<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields";
        header("Location: login.php");
        exit();
    }

    try {
        // First check if user exists in users table
        $stmt = $pdo->prepare("SELECT u.*, s.role, s.id as staff_id, p.id as patient_id 
                              FROM users u 
                              LEFT JOIN staff s ON u.staff_id = s.id 
                              LEFT JOIN patients p ON u.patient_id = p.id 
                              WHERE u.username = ? AND u.status = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['pass'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
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
                        $_SESSION['error'] = "No doctor assigned to this assistant";
                        header("Location: login.php");
                    }
                }
            }
            exit();
        } else {
            $_SESSION['error'] = "Invalid username or password";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "An error occurred. Please try again later.";
        error_log($e->getMessage());
    }

    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
} 