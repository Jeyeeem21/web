<?php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload for home picture
        $home_pic_path = null;
        if (isset($_FILES['homePic']) && $_FILES['homePic']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/home/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['homePic']['name'], PATHINFO_EXTENSION));
            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['homePic']['tmp_name'], $upload_path)) {
                $home_pic_path = $upload_path;
            }
        }

        // Prepare the update query
        $sql = "UPDATE home SET maintext = :maintext, secondtext = :secondtext, thirdtext = :thirdtext";
        
        // Add home picture to update if a new one was uploaded
        if ($home_pic_path) {
            $sql .= ", homePic = :homePic";
        }
        
        $sql .= " WHERE id = 1"; // Assuming there's only one record

        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':maintext', $_POST['maintext']);
        $stmt->bindParam(':secondtext', $_POST['secondtext']);
        $stmt->bindParam(':thirdtext', $_POST['thirdtext']);
        
        if ($home_pic_path) {
            $stmt->bindParam(':homePic', $home_pic_path);
        }

        if ($stmt->execute()) {
            header('Location: index.php?page=information&success=1');
            exit;
        } else {
            header('Location: index.php?page=information&error=1');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error updating home content: " . $e->getMessage());
        header('Location: index.php?page=information&error=1');
        exit;
    }
} else {
    header('Location: index.php?page=information');
    exit;
} 