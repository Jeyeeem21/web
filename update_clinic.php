<?php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload for QR code
        $qr_code_path = null;
        if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/qr/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));
            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_path)) {
                $qr_code_path = $upload_path;
            }
        }

        // Prepare the update query
        $sql = "UPDATE clinic_details SET clinic_name = :clinic_name, address = :address, phone = :phone, email = :email";
        
        // Add QR code to update if a new one was uploaded
        if ($qr_code_path) {
            $sql .= ", qrcode = :qrcode";
        }
        
        $sql .= " WHERE id = 1"; // Assuming there's only one record

        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':clinic_name', $_POST['clinic_name']);
        $stmt->bindParam(':address', $_POST['address']);
        $stmt->bindParam(':phone', $_POST['phone']);
        $stmt->bindParam(':email', $_POST['email']);
        
        if ($qr_code_path) {
            $stmt->bindParam(':qrcode', $qr_code_path);
        }

        if ($stmt->execute()) {
            header('Location: index.php?page=information&success=1');
            exit;
        } else {
            header('Location: index.php?page=information&error=1');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error updating clinic details: " . $e->getMessage());
        header('Location: index.php?page=information&error=1');
        exit;
    }
} else {
    header('Location: index.php?page=information');
    exit;
} 