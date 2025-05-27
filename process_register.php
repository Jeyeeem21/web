<?php

//process register
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $birthdate = $_POST['birthdate'];
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $status = 1; // Set status to 1 by default

    // Log received data
    error_log("Registration attempt - Name: $name, Email: $email, Phone: $phone");

    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($birthdate) || empty($gender)) {
        $_SESSION['error'] = "Please fill in all required fields";
        error_log("Registration failed - Missing required fields");
        header("Location: login.php");
        exit();
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Email already registered";
            error_log("Registration failed - Email already exists: $email");
            header("Location: login.php");
            exit();
        }

        // Handle photo upload
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/patients/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory: $upload_dir");
                }
            }

            $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png'];

            if (!in_array($file_extension, $allowed_extensions)) {
                $_SESSION['error'] = "Invalid file type. Please upload JPG, JPEG, or PNG files only.";
                error_log("Registration failed - Invalid file type: $file_extension");
                header("Location: login.php");
                exit();
            }

            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $photo_path = $upload_dir . $new_filename;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                throw new Exception("Failed to upload photo to: $photo_path");
            }
        }

        // Calculate age from birthdate
        try {
            $birthdate_obj = new DateTime($birthdate);
            $today = new DateTime();
            $age = $birthdate_obj->diff($today)->y;
        } catch (Exception $e) {
            throw new Exception("Invalid birthdate format: $birthdate");
        }

        // Insert new patient
        $stmt = $pdo->prepare("
            INSERT INTO patients (name, email, phone, address, birthdate, age, gender, photo, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $params = [
            $name,
            $email,
            $phone,
            $address,
            $birthdate,
            $age,
            $gender,
            $photo_path,
            $status
        ];

        // Log the SQL parameters
        error_log("Registration SQL parameters: " . print_r($params, true));

        if (!$stmt->execute($params)) {
            throw new Exception("Failed to execute patient insert: " . implode(", ", $stmt->errorInfo()));
        }

        $patient_id = $pdo->lastInsertId();
        error_log("Registration successful - Patient ID: $patient_id");

        if ($patient_id) {
            $_SESSION['success'] = "Registration successful! Please create a user account for the patient.";
            $_SESSION['show_credentials_modal'] = true;
            $_SESSION['new_patient_id'] = $patient_id;
            header("Location: login.php");
            exit();
        } else {
            throw new Exception("Failed to get last insert ID");
        }

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred during registration: " . $e->getMessage();
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
} 