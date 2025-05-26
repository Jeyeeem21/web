<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    // Validate input
    if (empty($email) || empty($phone) || empty($address)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: patient_dashboard.php");
        exit();
    }

    // Check if email is already taken by another patient
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ? AND id != ?");
    $stmt->execute([$email, $patient_id]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Email is already taken by another patient.";
        header("Location: patient_dashboard.php");
        exit();
    }

    // Handle photo upload
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG and GIF are allowed.";
            header("Location: patient_dashboard.php");
            exit();
        }

        if ($_FILES['photo']['size'] > $max_size) {
            $_SESSION['error'] = "File is too large. Maximum size is 5MB.";
            header("Location: patient_dashboard.php");
            exit();
        }

        $upload_dir = 'uploads/patients/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('patient_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
            $photo_path = $target_path;
        }
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Update patient information
        $sql = "UPDATE patients SET email = ?, phone = ?, address = ?";
        $params = [$email, $phone, $address];

        if ($photo_path) {
            $sql .= ", photo = ?";
            $params[] = $photo_path;
        }

        $sql .= " WHERE id = ?";
        $params[] = $patient_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Update user email if it exists
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE patient_id = ?");
        $stmt->execute([$email, $patient_id]);

        $pdo->commit();
        $_SESSION['success'] = "Profile updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "An error occurred while updating your profile.";
    }

    header("Location: patient_dashboard.php");
    exit();
} else {
    header("Location: patient_dashboard.php");
    exit();
}
?> 