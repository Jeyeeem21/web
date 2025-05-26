<?php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload for about picture
        $about_pic_path = null;
        if (isset($_FILES['aboutPic']) && $_FILES['aboutPic']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/about/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['aboutPic']['name'], PATHINFO_EXTENSION));
            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['aboutPic']['tmp_name'], $upload_path)) {
                $about_pic_path = $upload_path;
            }
        }

        // Prepare the update query
        $sql = "UPDATE about SET aboutText = :aboutText";
        
        // Add about picture to update if a new one was uploaded
        if ($about_pic_path) {
            $sql .= ", aboutPic = :aboutPic";
        }
        
        $sql .= " WHERE id = 1"; // Assuming there's only one record

        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':aboutText', $_POST['aboutText']);
        
        if ($about_pic_path) {
            $stmt->bindParam(':aboutPic', $about_pic_path);
        }

        if ($stmt->execute()) {
            header('Location: index.php?page=information&success=1');
            exit;
        } else {
            header('Location: index.php?page=information&error=1');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error updating about content: " . $e->getMessage());
        header('Location: index.php?page=information&error=1');
        exit;
    }
} else {
    header('Location: index.php?page=information');
    exit;
} 