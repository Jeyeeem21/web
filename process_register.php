<?php
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

    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($birthdate) || empty($gender)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: login.php");
        exit();
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Email already registered";
            header("Location: login.php");
            exit();
        }

        // Handle photo upload
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/patients/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png'];

            if (!in_array($file_extension, $allowed_extensions)) {
                $_SESSION['error'] = "Invalid file type. Please upload JPG, JPEG, or PNG files only.";
                header("Location: login.php");
                exit();
            }

            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $photo_path = $upload_dir . $new_filename;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                throw new Exception("Failed to upload photo");
            }
        }

        // Calculate age from birthdate
        $birthdate_obj = new DateTime($birthdate);
        $today = new DateTime();
        $age = $birthdate_obj->diff($today)->y;

        // Generate a random password
        $temp_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Insert new patient
        $stmt = $pdo->prepare("
            INSERT INTO patients (name, email, phone, address, birthdate, age, gender, photo, password, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");

        $stmt->execute([
            $name,
            $email,
            $phone,
            $address,
            $birthdate,
            $age,
            $gender,
            $photo_path,
            $hashed_password
        ]);

        // Send email with temporary password
        $to = $email;
        $subject = "Welcome to Our Clinic - Your Account Details";
        $message = "Dear " . $name . ",\n\n";
        $message .= "Welcome to our clinic! Your account has been created successfully.\n\n";
        $message .= "Your temporary password is: " . $temp_password . "\n";
        $message .= "Please login and change your password immediately.\n\n";
        $message .= "Best regards,\nClinic Team";

        $headers = "From: clinic@example.com";

        mail($to, $subject, $message, $headers);

        $_SESSION['success'] = "Registration successful! Please check your email for login details.";
        header("Location: login.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred during registration. Please try again.";
        error_log($e->getMessage());
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
} 