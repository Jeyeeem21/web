<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_credentials') {
    $patient_id = $_POST['patient_id'] ?? '';
    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $status = 1;

    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        $_SESSION['show_credentials_modal'] = true;
        $_SESSION['new_patient_id'] = $patient_id;
        header("Location: login.php");
        exit();
    }

    try {
        // Check for duplicate username
        $check_sql = "SELECT COUNT(*) FROM users WHERE username = :username";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':username' => $username]);
        if ($check_stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Username already exists. Please choose a different username.";
            $_SESSION['show_credentials_modal'] = true;
            $_SESSION['new_patient_id'] = $patient_id;
            header("Location: login.php");
            exit();
        }

        $sql = "INSERT INTO users (username, pass, patient_id, status) 
                VALUES (:username, :password, :patient_id, :status)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':patient_id' => $patient_id,
            ':status' => $status
        ]);

        $_SESSION['success'] = "User account created successfully!";
        unset($_SESSION['show_credentials_modal']);
        unset($_SESSION['new_patient_id']);
        header("Location: login.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating user account: " . $e->getMessage();
        $_SESSION['show_credentials_modal'] = true;
        $_SESSION['new_patient_id'] = $patient_id;
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
} 