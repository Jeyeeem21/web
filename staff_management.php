<?php
// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'D:/xampp/htdocs/Clinic/logs/php_errors.log');

// Start output buffering
ob_start();

// Include database connection
require_once 'config/db.php';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new staff
        if ($_POST['action'] == 'add') {
            $name = $_POST['name'] ?? '';
            $address = $_POST['address'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $gmail = $_POST['gmail'] ?? '';
            $birthdate = $_POST['birthdate'] ?? '';
            
            $birthDate = new DateTime($birthdate);
            $today = new DateTime();
            $age = $birthDate->diff($today)->y;
            
            $gender = $_POST['gender'] ?? '';
            $other_gender = $_POST['other_gender'] ?? '';
            $role = $_POST['role'] ?? '';
            $doctor_position_id = null;
            $assistant_id = null;
            $status = 1;
            
            if ($role === 'doctor' && !empty($_POST['doctor_position_id'])) {
                $doctor_position_id = $_POST['doctor_position_id'];
            }
            
            $photo = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $target_dir = "Uploads/staff/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $photo = $target_dir . time() . '_' . basename($_FILES["photo"]["name"]);
                move_uploaded_file($_FILES["photo"]["tmp_name"], $photo);
            }
            
            if ($gender === 'Other') {
                $gender = $other_gender;
            }
            
            try {
                $pdo->beginTransaction();
                
                $sql = "INSERT INTO staff (name, address, contact, gmail, birthdate, age, photo, gender, role, 
                        doctor_position_id, assistant_id, status) 
                        VALUES (:name, :address, :contact, :gmail, :birthdate, :age, :photo, :gender, :role, 
                        :doctor_position_id, :assistant_id, :status)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':address' => $address,
                    ':contact' => $contact,
                    ':gmail' => $gmail,
                    ':birthdate' => $birthdate,
                    ':age' => $age,
                    ':photo' => $photo,
                    ':gender' => $gender,
                    ':role' => $role,
                    ':doctor_position_id' => $doctor_position_id,
                    ':assistant_id' => $assistant_id,
                    ':status' => $status
                ]);
                
                $staff_id = $pdo->lastInsertId();
                
                if ($role === 'assistant' && $staff_id) {
                    $update_sql = "UPDATE staff SET assistant_id = :staff_id WHERE id = :staff_id";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([':staff_id' => $staff_id]);
                }
                
                if ($role === 'doctor' && $staff_id) {
                    $doctor_sql = "INSERT INTO doctor (doctor_id, assistant_id, created_at) 
                                VALUES (:doctor_id, NULL, CURRENT_TIMESTAMP)";
                    $doctor_stmt = $pdo->prepare($doctor_sql);
                    $doctor_stmt->execute([':doctor_id' => $staff_id]);
                }
                
                $pdo->commit();
                
                if ($staff_id) {
                    header("Location: index.php?page=staff_management&action=show_credentials&staff_id=" . $staff_id);
                    exit();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error adding staff: " . $e->getMessage();
                header("Location: index.php?page=staff_management&error=" . urlencode($error_message));
                exit();
            }
        }
        
        // Add user credentials
        if ($_POST['action'] == 'add_credentials') {
            $staff_id = $_POST['staff_id'];
            $username = htmlspecialchars(trim($_POST['username']));
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $status = 1;
            
            try {
                // Check for duplicate username
                $check_sql = "SELECT COUNT(*) FROM users WHERE username = :username";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':username' => $username]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    header("Location: index.php?page=staff_management&action=show_credentials&staff_id=" . $staff_id . "&error=" . urlencode("Username already exists. Please choose a different username."));
                    exit();
                }
                
                $sql = "INSERT INTO users (username, pass, staff_id, status) 
                        VALUES (:username, :password, :staff_id, :status)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $password,
                    ':staff_id' => $staff_id,
                    ':status' => $status
                ]);
                
                header("Location: index.php?page=staff_management&success=added");
                exit();
            } catch (PDOException $e) {
                $error_message = "Error creating user account: " . $e->getMessage();
                header("Location: index.php?page=staff_management&action=show_credentials&staff_id=" . $staff_id . "&error=" . urlencode($error_message));
                exit();
            }
        }
        
        // Assign assistant
        if ($_POST['action'] == 'assign_assistant') {
            $doctor_id = $_POST['doctor_id'];
            $assistant_id = $_POST['assistant_id'] ? $_POST['assistant_id'] : null;
            
            try {
                $sql = "UPDATE doctor SET assistant_id = :assistant_id WHERE doctor_id = :doctor_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':assistant_id' => $assistant_id,
                    ':doctor_id' => $doctor_id
                ]);
                
                header("Location: index.php?page=staff_management&success=assistant_assigned");
                exit();
            } catch (PDOException $e) {
                $error_message = "Error assigning assistant: " . $e->getMessage();
                header("Location: index.php?page=staff_management&error=" . urlencode($error_message));
                exit();
            }
        }
        
        // Edit staff
        if ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $name = $_POST['name'] ?? '';
            $address = $_POST['address'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $gmail = $_POST['gmail'] ?? '';
            $birthdate = $_POST['birthdate'] ?? '';
            
            $birthDate = new DateTime($birthdate);
            $today = new DateTime();
            $age = $birthDate->diff($today)->y;
            
            $gender = $_POST['gender'] ?? '';
            $other_gender = $_POST['other_gender'] ?? '';
            $role = $_POST['role'] ?? '';
            $doctor_position_id = null;
            $assistant_id = null;
            
            if ($role === 'doctor' && !empty($_POST['doctor_position_id'])) {
                $doctor_position_id = $_POST['doctor_position_id'];
            }
            
            $photo = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $target_dir = "Uploads/staff/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $photo = $target_dir . time() . '_' . basename($_FILES["photo"]["name"]);
                move_uploaded_file($_FILES["photo"]["tmp_name"], $photo);
            }
            
            if ($gender === 'Other') {
                $gender = $other_gender;
            }
            
            try {
                $pdo->beginTransaction();
                
                $sql = "UPDATE staff SET 
                        name = :name,
                        address = :address,
                        contact = :contact,
                        gmail = :gmail,
                        birthdate = :birthdate,
                        age = :age,
                        gender = :gender,
                        role = :role,
                        doctor_position_id = :doctor_position_id,
                        assistant_id = :assistant_id";
                
                if (!empty($photo)) {
                    $sql .= ", photo = :photo";
                }
                
                $sql .= " WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':name' => $name,
                    ':address' => $address,
                    ':contact' => $contact,
                    ':gmail' => $gmail,
                    ':birthdate' => $birthdate,
                    ':age' => $age,
                    ':gender' => $gender,
                    ':role' => $role,
                    ':doctor_position_id' => $doctor_position_id,
                    ':assistant_id' => $assistant_id,
                    ':id' => $id
                ];
                
                if (!empty($photo)) {
                    $params[':photo'] = $photo;
                }
                
                $stmt->execute($params);
                
                if ($role === 'assistant') {
                    $update_sql = "UPDATE staff SET assistant_id = :staff_id WHERE id = :staff_id";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([':staff_id' => $id]);
                }
                
                if ($role === 'doctor') {
                    $check_sql = "SELECT COUNT(*) FROM doctor WHERE doctor_id = :doctor_id";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([':doctor_id' => $id]);
                    if ($check_stmt->fetchColumn() == 0) {
                        $doctor_sql = "INSERT INTO doctor (doctor_id, assistant_id, created_at) 
                                    VALUES (:doctor_id, NULL, CURRENT_TIMESTAMP)";
                        $doctor_stmt = $pdo->prepare($doctor_sql);
                        $doctor_stmt->execute([':doctor_id' => $id]);
                    }
                }
                
                $pdo->commit();
                
                header("Location: index.php?page=staff_management&success=updated");
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error updating staff: " . $e->getMessage();
                header("Location: index.php?page=staff_management&error=" . urlencode($error_message));
                exit();
            }
        }

        // Edit user
        if ($_POST['action'] == 'edit_user') {
            $user_id = $_POST['user_id'];
            $username = htmlspecialchars(trim($_POST['username']));
            $password = $_POST['password'];
            
            try {
                // Check for duplicate username
                $check_sql = "SELECT COUNT(*) FROM users WHERE username = :username AND id != :id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':username' => $username, ':id' => $user_id]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    header("Location: index.php?page=staff_management&error=" . urlencode("Username already exists. Please choose a different username."));
                    exit();
                }
                
                if (!empty($password)) {
                    $sql = "UPDATE users SET username = :username, pass = :password WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':username' => $username,
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':id' => $user_id
                    ]);
                } else {
                    $sql = "UPDATE users SET username = :username WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':username' => $username,
                        ':id' => $user_id
                    ]);
                }
                
                header("Location: index.php?page=staff_management&success=user_updated");
                exit();
            } catch (PDOException $e) {
                $error_message = "Error updating user: " . $e->getMessage();
                header("Location: index.php?page=staff_management&error=" . urlencode($error_message));
                exit();
            }
        }

        // Add new schedule
        if ($_POST['action'] == 'add_schedule') {
            $doctor_id = $_POST['doctor_id'] ?? '';
            $rest_day = $_POST['rest_day'] ?? '';

            try {
                // Check if doctor already has a rest day
                $check_sql = "SELECT COUNT(*) FROM doctor_schedule WHERE doctor_id = :doctor_id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':doctor_id' => $doctor_id]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    header("Location: index.php?page=staff_management&error=" . urlencode("This doctor already has a rest day assigned"));
                    exit();
                }

                // Set working hours based on rest day
                $start_time = '';
                $end_time = '';
                
                switch($rest_day) {
                    case 'Monday':
                    case 'Tuesday':
                    case 'Wednesday':
                    case 'Thursday':
                    case 'Friday':
                        $start_time = '08:00:00';
                        $end_time = '17:00:00';
                        break;
                    case 'Saturday':
                        $start_time = '09:00:00';
                        $end_time = '14:00:00';
                        break;
                    default:
                        header("Location: index.php?page=staff_management&error=" . urlencode("Invalid rest day selected"));
                        exit();
                }

                $pdo->beginTransaction();

                $sql = "INSERT INTO doctor_schedule (doctor_id, rest_day, start_time, end_time, created_at) 
                        VALUES (:doctor_id, :rest_day, :start_time, :end_time, CURRENT_TIMESTAMP)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':doctor_id' => $doctor_id,
                    ':rest_day' => $rest_day,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time
                ]);

                $pdo->commit();
                header("Location: index.php?page=staff_management&success=added");
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error adding schedule: " . $e->getMessage();
                header("Location: index.php?page=staff_management&error=" . urlencode($error_message));
                exit();
            }
        }

        // Edit schedule
        if ($_POST['action'] == 'edit_schedule') {
            $schedule_id = $_POST['schedule_id'] ?? '';
            $doctor_id = $_POST['doctor_id'] ?? '';
            $rest_day = $_POST['rest_day'] ?? '';

            try {
                // Check if doctor already has a rest day (excluding current schedule)
                $check_sql = "SELECT COUNT(*) FROM doctor_schedule WHERE doctor_id = :doctor_id AND id != :schedule_id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([
                    ':doctor_id' => $doctor_id,
                    ':schedule_id' => $schedule_id
                ]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    header("Location: index.php?page=staff_management&error=" . urlencode("This doctor already has a rest day assigned"));
                    exit();
                }

                // Set working hours based on rest day
                $start_time = '';
                $end_time = '';
                
                switch($rest_day) {
                    case 'Monday':
                    case 'Tuesday':
                    case 'Wednesday':
                    case 'Thursday':
                    case 'Friday':
                        $start_time = '08:00:00';
                        $end_time = '17:00:00';
                        break;
                    case 'Saturday':
                        $start_time = '09:00:00';
                        $end_time = '14:00:00';
                        break;
                    default:
                        header("Location: index.php?page=staff_management&error=" . urlencode("Invalid rest day selected"));
                        exit();
                }

                $pdo->beginTransaction();

                $sql = "UPDATE doctor_schedule SET 
                        doctor_id = :doctor_id,
                        rest_day = :rest_day,
                        start_time = :start_time,
                        end_time = :end_time
                        WHERE id = :schedule_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':schedule_id' => $schedule_id,
                    ':doctor_id' => $doctor_id,
                    ':rest_day' => $rest_day,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time
                ]);

                $pdo->commit();
                header("Location: index.php?page=staff_management&success=updated");
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error updating schedule: " . $e->getMessage();
                header("Location: index.php?page=staff_management&error=" . urlencode($error_message));
                exit();
            }
        }
    }
}

// Delete staff
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Check if staff has an associated user account
        $check_user_sql = "SELECT id FROM users WHERE staff_id = :staff_id AND status = 1";
        $check_user_stmt = $pdo->prepare($check_user_sql);
        $check_user_stmt->execute([':staff_id' => $id]);
        if ($check_user_stmt->fetchColumn() > 0) {
            throw new Exception("Cannot delete staff with an active user account. Please delete or deactivate the user account first.");
        }

        // Remove from doctor table if exists
        $delete_doctor_sql = "DELETE FROM doctor WHERE doctor_id = :staff_id";
        $delete_doctor_stmt = $pdo->prepare($delete_doctor_sql);
        $delete_doctor_stmt->execute([':staff_id' => $id]);

        // Remove assistant assignments
        $update_doctor_sql = "UPDATE doctor SET assistant_id = NULL WHERE assistant_id = :staff_id";
        $update_doctor_stmt = $pdo->prepare($update_doctor_sql);
        $update_doctor_stmt->execute([':staff_id' => $id]);

        // Mark staff as inactive
        $stmt = $pdo->prepare("UPDATE staff SET status = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle delete schedule action
if (isset($_GET['action']) && $_GET['action'] == 'delete_schedule' && isset($_GET['id'])) {
    // Prevent any output before this point
    if (ob_get_level()) ob_end_clean();
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM doctor_schedule WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pdo->commit();
        die(json_encode(['success' => true]));
    } catch (Exception $e) {
        $pdo->rollBack();
        die(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
}

// Get all active staff
$stmt = $pdo->query("SELECT s.*, dp.doctor_position 
                    FROM staff s 
                    LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                    WHERE s.status = 1 
                    ORDER BY s.createdDate DESC");
$staff = $stmt->fetchAll();

// Get all active doctor positions
$stmt = $pdo->query("SELECT * FROM doctor_position WHERE status = 1 ORDER BY doctor_position ASC");
$positions = $stmt->fetchAll();

// Get all active assistants
$stmt = $pdo->query("SELECT * FROM staff WHERE role = 'assistant' AND status = 1 ORDER BY name ASC");
$assistants = $stmt->fetchAll();

// Get all active doctors
$stmt = $pdo->query("SELECT d.doctor_id, s.name as doctor_name, dp.doctor_position, 
                        a.id as assistant_id, a.name as assistant_name
                    FROM doctor d
                    JOIN staff s ON d.doctor_id = s.id
                    LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id
                    LEFT JOIN staff a ON d.assistant_id = a.id
                    WHERE s.status = 1
                    ORDER BY s.name ASC");
$doctors = $stmt->fetchAll();

// Get all doctor schedules
$stmt = $pdo->query("
    SELECT s.*, d.name as doctor_name 
    FROM doctor_schedule s 
    JOIN staff d ON s.doctor_id = d.id 
    WHERE d.status = 1
    ORDER BY s.created_at DESC
");
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff without user accounts
$stmt = $pdo->query("SELECT s.id, s.name 
                    FROM staff s 
                    LEFT JOIN users u ON s.id = u.staff_id 
                    WHERE s.status = 1 AND u.id IS NULL 
                    ORDER BY s.name ASC");
$staff_without_users = $stmt->fetchAll();

// Get clinic details
$stmt = $pdo->query("SELECT * FROM clinic_details LIMIT 1");
$clinic = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div id="staff_management" class="space-y-6 bg-neutral-light p-6 md:p-8 animate-fade-in">
    <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Staff Management</h2>
    
    <!-- Tabs -->
    <div class="border-b border-primary-100">
        <ul class="flex flex-wrap -mb-px text-sm overflow-x-auto">
            <li class="mr-2">
                <a href="index.php?page=information" class="inline-block p-3 text-secondary hover:text-primary-500 hover:bg-primary-50 hover:shadow-sm hover:scale-105 rounded-t-lg transition-all duration-200">Overview</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=home_management" class="inline-block p-3 text-secondary hover:text-primary-500 hover:bg-primary-50 hover:shadow-sm hover:scale-105 rounded-t-lg transition-all duration-200">Data</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=doctor_position_management" class="inline-block p-3 text-secondary hover:text-primary-500 hover:bg-primary-50 hover:shadow-sm hover:scale-105 rounded-t-lg transition-all duration-200">Services</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=staff_management" class="inline-block p-3 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-t-lg hover:brightness-110 hover:scale-105 transition-all duration-200">Staff</a>
            </li>
        </ul>
    </div>
    
    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div id="successAlert" class="bg-success-50 border border-success-200 text-success-800 px-3 py-2 rounded-md text-sm flex justify-between items-center">
        <span>
            <?php 
            if ($_GET['success'] == 'added') echo 'Staff added successfully!';
            if ($_GET['success'] == 'updated') echo 'Staff updated successfully!';
            if ($_GET['success'] == 'user_updated') echo 'User account updated successfully!';
            if ($_GET['success'] == 'assistant_assigned') echo 'Assistant assigned successfully!';
            ?>
        </span>
        <button type="button" onclick="document.getElementById('successAlert').style.display = 'none'" class="text-success-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <script>
        setTimeout(function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                successAlert.style.display = 'none';
            }
        }, 5000);
    </script>
    <?php endif; ?>
    
    <!-- Error Message -->
    <?php if (isset($_GET['error'])): ?>
    <div id="errorAlert" class="bg-red-50 border border-red-200 text-red-800 px-3 py-2 rounded-md text-sm flex justify-between items-center">
        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        <button type="button" onclick="document.getElementById('errorAlert').style.display = 'none'" class="text-red-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <script>
        setTimeout(function() {
            const errorAlert = document.getElementById('errorAlert');
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }
        }, 5000);
    </script>
    <?php endif; ?>
    
    <!-- Staff Section -->
    <div class="bg-white rounded-xl border border-primary-100 shadow-sm hover:shadow-md transition-all duration-200 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-neutral-dark">Staff List</h3>
            <button type="button" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-3 py-1.5 rounded-lg text-sm flex items-center hover:scale-105 transition-all duration-200" onclick="openAddModal()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Staff
            </button>
        </div>
        
        <!-- Staff Table -->
        <div class="overflow-x-auto">
            <table id="staffTable" class="min-w-full divide-y divide-primary-100 border-separate border-spacing-0 mobile-card-view">
                <thead class="bg-neutral-light">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Photo</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Role</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Contact</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-primary-100">
                    <?php foreach ($staff as $member): ?>
                    <tr class="hover:bg-primary-50 transition-colors duration-200" id="staff-row-<?php echo $member['id']; ?>">
                        <td class="px-4 py-2">
                            <?php if (!empty($member['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($member['photo']); ?>" alt="Staff Photo" class="h-12 w-12 rounded-full object-cover">
                            <?php else: ?>
                            <div class="h-12 w-12 rounded-full bg-neutral-light flex items-center justify-center">
                                <span class="text-secondary text-xs">No photo</span>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm font-medium text-neutral-dark"><?php echo htmlspecialchars($member['name']); ?></div>
                            <div class="text-xs text-secondary"><?php echo htmlspecialchars($member['gmail']); ?></div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm font-medium text-neutral-dark"><?php echo ucfirst($member['role']); ?></div>
                            <?php if ($member['role'] == 'doctor' && !empty($member['doctor_position'])): ?>
                            <div class="text-xs text-primary-500"><?php echo htmlspecialchars($member['doctor_position']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($member['contact']); ?></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                            <div class="flex space-x-2">
                                <button type="button" class="text-primary-500 hover:text-primary-600 transition-colors duration-200" onclick="openEditModal(<?php echo $member['id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" class="text-accent-500 hover:text-accent-600 transition-colors duration-200" onclick="deleteStaff(<?php echo $member['id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Doctors Section -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Doctors and Assistants</h3>
        </div>
        
        <!-- Doctors Table -->
        <div class="overflow-x-auto">
            <table id="doctorsTable" class="min-w-full divide-y divide-gray-200 mobile-card-view">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assistant</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($doctors as $doctor): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doctor['doctor_name']); ?></div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm font-medium text-primary-600"><?php echo htmlspecialchars($doctor['doctor_position'] ?: 'N/A'); ?></div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($doctor['assistant_name'] ?: 'None'); ?></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                            <div class="flex space-x-1">
                                <button type="button" class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-primary-700 bg-primary-100 hover:bg-primary-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" onclick="openAssignAssistantModal(<?php echo $doctor['doctor_id']; ?>, '<?php echo htmlspecialchars($doctor['assistant_id']); ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Assign
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Users Section -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">User Accounts</h3>
            <button type="button" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm flex items-center" onclick="openAddUserModal()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add User Account
            </button>
        </div>
        
        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table id="usersTable" class="min-w-full divide-y divide-gray-200 mobile-card-view">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php 
                    $stmt = $pdo->query("SELECT u.*, s.name as staff_name, s.role as staff_role 
                                    FROM users u 
                                    JOIN staff s ON u.staff_id = s.id 
                                    WHERE u.staff_id IS NOT NULL AND u.status = 1 
                                    ORDER BY u.id DESC");
                    $users = $stmt->fetchAll();
                    
                    foreach ($users as $user): 
                    ?>
                    <tr class="hover:bg-gray-50" id="user-row-<?php echo $user['id']; ?>">
                        <td class="px-4 py-2">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['staff_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo ucfirst($user['staff_role']); ?></div>
                        </td>
                        <td class="px-4 py-2">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Active
                            </span>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                            <div class="flex space-x-1">
                                <button type="button" class="text-blue-600 hover:text-blue-800" onclick="openEditUserModal(<?php echo $user['id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Doctor Schedules Section -->
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Doctor Schedules</h3>
            <button type="button" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm flex items-center" onclick="openAddScheduleModal()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Schedule
            </button>
        </div>

        <div class="overflow-x-auto">
            <table id="scheduleTable" class="min-w-full divide-y divide-gray-200 mobile-card-view">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rest Day</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">No schedules found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $schedule): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2" data-label="Doctor">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($schedule['doctor_name']); ?></div>
                            </td>
                            <td class="px-4 py-2" data-label="Rest Day">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($schedule['rest_day']); ?></div>
                            </td>
                            <td class="px-4 py-2" data-label="Start Time">
                                <div class="text-sm text-gray-900"><?php echo date('h:i A', strtotime($schedule['start_time'])); ?></div>
                            </td>
                            <td class="px-4 py-2" data-label="End Time">
                                <div class="text-sm text-gray-900"><?php echo date('h:i A', strtotime($schedule['end_time'])); ?></div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm" data-label="Actions">
                                <div class="flex space-x-2">
                                    <button type="button" class="text-blue-600 hover:text-blue-800" onclick="openEditScheduleModal(<?php echo $schedule['id']; ?>, <?php echo $schedule['doctor_id']; ?>, '<?php echo htmlspecialchars($schedule['rest_day']); ?>', '<?php echo $schedule['start_time']; ?>', '<?php echo $schedule['end_time']; ?>')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="flex items-center justify-between p-4 border-b border-primary-100">
                <h3 class="text-lg font-medium text-neutral-dark">Add New Staff</h3>
                <button type="button" class="text-secondary hover:text-neutral-dark transition-colors duration-200" onclick="closeAddModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="addStaffForm" class="p-4" enctype="multipart/form-data">
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-neutral-dark">Name</label>
                        <input type="text" id="name" name="name" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="gmail" class="block text-sm font-medium text-neutral-dark">Email</label>
                        <input type="email" id="gmail" name="gmail" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="contact" class="block text-sm font-medium text-neutral-dark">Contact</label>
                        <input type="text" id="contact" name="contact" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-neutral-dark">Role</label>
                        <select id="role" name="role" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Role</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div id="doctorPositionField" class="hidden">
                        <label for="doctor_position" class="block text-sm font-medium text-neutral-dark">Doctor Position</label>
                        <select id="doctor_position" name="doctor_position" class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Position</option>
                            <?php foreach ($doctorPositions as $position): ?>
                            <option value="<?php echo $position['id']; ?>"><?php echo htmlspecialchars($position['position_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="photo" class="block text-sm font-medium text-neutral-dark">Photo</label>
                        <input type="file" id="photo" name="photo" accept="image/*" class="mt-1 block w-full text-sm text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-500 hover:file:bg-primary-100">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 text-sm font-medium text-secondary bg-white border border-primary-100 rounded-md hover:bg-neutral-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-primary-500 to-primary-600 rounded-md hover:from-primary-600 hover:to-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                        Add Staff
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="flex items-center justify-between p-4 border-b border-primary-100">
                <h3 class="text-lg font-medium text-neutral-dark">Edit Staff</h3>
                <button type="button" class="text-secondary hover:text-neutral-dark transition-colors duration-200" onclick="closeEditModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="editStaffForm" class="p-4" enctype="multipart/form-data">
                <input type="hidden" id="edit_id" name="id">
                <div class="space-y-4">
                    <div>
                        <label for="edit_name" class="block text-sm font-medium text-neutral-dark">Name</label>
                        <input type="text" id="edit_name" name="name" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="edit_gmail" class="block text-sm font-medium text-neutral-dark">Email</label>
                        <input type="email" id="edit_gmail" name="gmail" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="edit_contact" class="block text-sm font-medium text-neutral-dark">Contact</label>
                        <input type="text" id="edit_contact" name="contact" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="edit_role" class="block text-sm font-medium text-neutral-dark">Role</label>
                        <select id="edit_role" name="role" required class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Role</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div id="editDoctorPositionField" class="hidden">
                        <label for="edit_doctor_position" class="block text-sm font-medium text-neutral-dark">Doctor Position</label>
                        <select id="edit_doctor_position" name="doctor_position" class="mt-1 block w-full rounded-md border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Position</option>
                            <?php foreach ($doctorPositions as $position): ?>
                            <option value="<?php echo $position['id']; ?>"><?php echo htmlspecialchars($position['position_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit_photo" class="block text-sm font-medium text-neutral-dark">Photo</label>
                        <input type="file" id="edit_photo" name="photo" accept="image/*" class="mt-1 block w-full text-sm text-secondary file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-500 hover:file:bg-primary-100">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-sm font-medium text-secondary bg-white border border-primary-100 rounded-md hover:bg-neutral-light focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-primary-500 to-primary-600 rounded-md hover:from-primary-600 hover:to-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-200">
                        Update Staff
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Credentials Modal -->
    <div id="credentialsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Create User Account</h3>
                <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-3 py-2 rounded-md text-sm mb-4">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>
                <form id="credentialsForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="add_credentials">
                    <input type="hidden" name="staff_id" id="staff_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="username" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    
                    <div class="mb-4 relative">
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pr-10">
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3 mt-6 text-gray-500 hover:text-gray-700">
                            <svg id="eyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeCredentialsModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Skip</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add User Account Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Add User Account</h3>
                <form id="addUserForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="add_credentials">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Staff Member</label>
                        <select name="staff_id" id="addUserStaffId" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Staff</option>
                            <?php foreach ($staff_without_users as $staff_member): ?>
                            <option value="<?php echo $staff_member['id']; ?>"><?php echo htmlspecialchars($staff_member['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="addUserUsername" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    
                    <div class="mb-4 relative">
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="addUserPassword" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pr-10">
                        <button type="button" id="toggleAddUserPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 mt-6 text-gray-500 hover:text-gray-700">
                            <svg id="addUserEyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeAddUserModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Assistant Modal -->
    <div id="assignAssistantModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Assign Assistant</h3>
                <form id="assignAssistantForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="assign_assistant">
                    <input type="hidden" name="doctor_id" id="doctorId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Assistant</label>
                        <select name="assistant_id" id="assignAssistantId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">None</option>
                            <?php foreach ($assistants as $assistant): ?>
                            <option value="<?php echo $assistant['id']; ?>"><?php echo htmlspecialchars($assistant['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeAssignAssistantModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Edit Modal -->
    <div id="userEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Edit User Account</h3>
                <form id="userEditForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="editUsername" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    
                    <div class="mb-4 relative">
                        <label class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" name="password" id="editPassword" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 pr-10">
                        <button type="button" id="toggleEditPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 mt-6 text-gray-500 hover:text-gray-700">
                            <svg id="editEyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <p class="mt-1 text-xs text-gray-500">Leave blank to keep current password</p>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeUserEditModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div id="addScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Add Rest Day</h3>
                <form id="addScheduleForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="add_schedule">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Doctor</label>
                        <select name="doctor_id" id="addScheduleDoctorId" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Doctor</option>
                            <?php
                            // Get all doctors without a rest day
                            $stmt = $pdo->query("
                                SELECT s.* 
                                FROM staff s 
                                LEFT JOIN doctor_schedule ds ON s.id = ds.doctor_id 
                                WHERE s.role = 'doctor' 
                                AND s.status = 1 
                                AND ds.id IS NULL 
                                ORDER BY s.name
                            ");
                            $available_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($available_doctors as $doctor): 
                            ?>
                            <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Rest Day</label>
                        <select name="rest_day" id="addScheduleRestDay" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Day</option>
                            <option value="Monday">Monday (8:00 AM - 5:00 PM)</option>
                            <option value="Tuesday">Tuesday (8:00 AM - 5:00 PM)</option>
                            <option value="Wednesday">Wednesday (8:00 AM - 5:00 PM)</option>
                            <option value="Thursday">Thursday (8:00 AM - 5:00 PM)</option>
                            <option value="Friday">Friday (8:00 AM - 5:00 PM)</option>
                            <option value="Saturday">Saturday (9:00 AM - 2:00 PM)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Note: Sunday is not available as a rest day since the clinic is closed</p>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeAddScheduleModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Add Rest Day</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div id="editScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Edit Rest Day</h3>
                <form id="editScheduleForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="edit_schedule">
                    <input type="hidden" name="schedule_id" id="editScheduleId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Doctor</label>
                        <select name="doctor_id" id="editScheduleDoctorId" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['doctor_id']; ?>"><?php echo htmlspecialchars($doctor['doctor_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Rest Day</label>
                        <select name="rest_day" id="editScheduleRestDay" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Select Day</option>
                            <option value="Monday">Monday (8:00 AM - 5:00 PM)</option>
                            <option value="Tuesday">Tuesday (8:00 AM - 5:00 PM)</option>
                            <option value="Wednesday">Wednesday (8:00 AM - 5:00 PM)</option>
                            <option value="Thursday">Thursday (8:00 AM - 5:00 PM)</option>
                            <option value="Friday">Friday (8:00 AM - 5:00 PM)</option>
                            <option value="Saturday">Saturday (9:00 AM - 2:00 PM)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Note: Sunday is not available as a rest day since the clinic is closed</p>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeEditScheduleModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables only if they haven't been initialized
    if (!$.fn.DataTable.isDataTable('#staffTable')) {
        $('#staffTable').DataTable({
            responsive: true,
            order: [[1, 'asc']],
            pageLength: 10,
            language: {
                search: "",
                searchPlaceholder: "Search staff..."
            }
        });
    }

    if (!$.fn.DataTable.isDataTable('#doctorsTable')) {
        $('#doctorsTable').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            pageLength: 10,
            language: {
                search: "",
                searchPlaceholder: "Search doctors..."
            }
        });
    }

    if (!$.fn.DataTable.isDataTable('#usersTable')) {
        $('#usersTable').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            pageLength: 10,
            language: {
                search: "",
                searchPlaceholder: "Search users..."
            }
        });
    }

    // Handle role change to show/hide doctor position field
    document.getElementById('role')?.addEventListener('change', function() {
        const doctorPositionField = document.getElementById('doctorPositionField');
        doctorPositionField.classList.toggle('hidden', this.value !== 'doctor');
    });

    document.getElementById('edit_role')?.addEventListener('change', function() {
        const doctorPositionField = document.getElementById('editDoctorPositionField');
        doctorPositionField.classList.toggle('hidden', this.value !== 'doctor');
    });

    // Handle add staff form submission
    document.getElementById('addStaffForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add');

        fetch('staff_management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                closeAddModal();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('error', 'An error occurred while adding staff.');
        });
    });

    // Handle edit staff form submission
    document.getElementById('editStaffForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'edit');

        fetch('staff_management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                closeEditModal();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('error', 'An error occurred while updating staff.');
        });
    });
});

function openAddModal() {
    document.getElementById('addStaffModal').classList.remove('hidden');
    document.getElementById('addStaffModal').classList.add('flex');
    document.getElementById('addStaffForm').reset();
    document.getElementById('doctorPositionField').classList.add('hidden');
}

function closeAddModal() {
    document.getElementById('addStaffModal').classList.add('hidden');
    document.getElementById('addStaffModal').classList.remove('flex');
}

function openEditModal(id) {
    fetch(`staff_management.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const staff = data.staff;
                document.getElementById('edit_id').value = staff.id;
                document.getElementById('edit_name').value = staff.name;
                document.getElementById('edit_gmail').value = staff.gmail;
                document.getElementById('edit_contact').value = staff.contact;
                document.getElementById('edit_role').value = staff.role;
                
                const doctorPositionField = document.getElementById('editDoctorPositionField');
                if (staff.role === 'doctor') {
                    doctorPositionField.classList.remove('hidden');
                    document.getElementById('edit_doctor_position').value = staff.doctor_position_id;
                } else {
                    doctorPositionField.classList.add('hidden');
                }

                document.getElementById('editStaffModal').classList.remove('hidden');
                document.getElementById('editStaffModal').classList.add('flex');
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('error', 'An error occurred while fetching staff details.');
        });
}

function closeEditModal() {
    document.getElementById('editStaffModal').classList.add('hidden');
    document.getElementById('editStaffModal').classList.remove('flex');
}

function deleteStaff(id) {
    if (confirm('Are you sure you want to delete this staff member?')) {
        fetch('staff_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message);
                document.getElementById(`staff-row-${id}`).remove();
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('error', 'An error occurred while deleting staff.');
        });
    }
}

function showMessage(type, message) {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = `p-4 mb-4 rounded-lg ${type === 'success' ? 'bg-success-50 text-success-700' : 'bg-error-50 text-error-700'}`;
    messageDiv.classList.remove('hidden');
    
    setTimeout(() => {
        messageDiv.classList.add('hidden');
    }, 5000);
}

<?php if (isset($_GET['action']) && $_GET['action'] == 'show_credentials' && isset($_GET['staff_id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('staff_id').value = '<?php echo $_GET['staff_id']; ?>';
    document.getElementById('credentialsModal').classList.remove('hidden');
});
<?php endif; ?>

function openAddUserModal() {
    document.getElementById('addUserForm').reset();
    document.getElementById('addUserModal').classList.remove('hidden');
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.add('hidden');
}

function openEditUserModal(id) {
    fetch(`ajax_handler.php?action=get_user&id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            document.getElementById('userId').value = id;
            document.getElementById('editUsername').value = data.username || '';
            document.getElementById('editPassword').value = '';
            document.getElementById('userEditModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading user details: ' + error.message);
        });
}

function closeUserEditModal() {
    document.getElementById('userEditModal').classList.add('hidden');
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user account?')) {
        fetch(`ajax_handler.php?action=delete_user&id=${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById(`user-row-${id}`).remove();
                    alert('User account deleted successfully');
                } else {
                    alert('Error deleting user account: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting user account: ' + error.message);
            });
    }
}

function openAssignAssistantModal(doctorId, currentAssistantId) {
    document.getElementById('doctorId').value = doctorId;
    document.getElementById('assignAssistantId').value = currentAssistantId || '';
    document.getElementById('assignAssistantModal').classList.remove('hidden');
}

function closeAssignAssistantModal() {
    document.getElementById('assignAssistantModal').classList.add('hidden');
}

// Password toggle functionality
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
    }
});

document.getElementById('toggleEditPassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('editPassword');
    const eyeIcon = document.getElementById('editEyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
    }
});

document.getElementById('toggleAddUserPassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('addUserPassword');
    const eyeIcon = document.getElementById('addUserEyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
    }
});

// Schedule Management Functions
function openAddScheduleModal() {
    document.getElementById('addScheduleModal').classList.remove('hidden');
}

function closeAddScheduleModal() {
    document.getElementById('addScheduleModal').classList.add('hidden');
}

function openEditScheduleModal(id, doctorId, restDay, startTime, endTime) {
    document.getElementById('editScheduleId').value = id;
    document.getElementById('editScheduleDoctorId').value = doctorId;
    document.getElementById('editScheduleRestDay').value = restDay;
    document.getElementById('editScheduleStartTime').value = startTime;
    document.getElementById('editScheduleEndTime').value = endTime;
    document.getElementById('editScheduleModal').classList.remove('hidden');
}

function closeEditScheduleModal() {
    document.getElementById('editScheduleModal').classList.add('hidden');
}

function deleteSchedule(id) {
    if (confirm('Are you sure you want to delete this rest day?')) {
        fetch(`ajax_handler.php?action=delete_schedule&id=${id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting rest day: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting rest day: ' + error.message);
});
    }
}
</script>

<style>
/* Base styles */
:root {
    --primary-color: #2563eb;
    --primary-hover: #1d4ed8;
    --secondary-color: #64748b;
    --success-color: #22c55e;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
    --info-color: #3b82f6;
    --light-bg: #f8fafc;
    --border-color: #e2e8f0;
}

/* Card styles */
.card {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Doctor position badge */
.doctor-position {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    background-color: var(--primary-color);
    color: white;
    margin-top: 0.25rem;
}

.doctor-position.specialist {
    background-color: var(--info-color);
}

.doctor-position.consultant {
    background-color: var(--warning-color);
}

.doctor-position.general {
    background-color: var(--success-color);
}

/* Table styles */
.table-container {
    overflow-x: auto;
    border-radius: 0.5rem;
    background: white;
}

.custom-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.custom-table th {
    background-color: var(--light-bg);
    padding: 1rem;
    font-weight: 600;
    text-align: left;
    color: var(--secondary-color);
    border-bottom: 2px solid var(--border-color);
}

.custom-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.custom-table tr:hover {
    background-color: var(--light-bg);
}

/* Button styles */
.btn {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: var(--primary-hover);
}

.btn-icon {
    padding: 0.5rem;
    border-radius: 0.375rem;
}

/* Modal styles */
.modal {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Form styles */
.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--secondary-color);
}

.form-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.form-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Mobile styles */
@media (max-width: 640px) {
    .mobile-card-view thead {
        display: none;
    }
    
    .mobile-card-view tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1rem;
        background: white;
    }
    
    .mobile-card-view tbody td {
        display: flex;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .mobile-card-view tbody td:last-child {
        border-bottom: none;
    }
    
    .mobile-card-view tbody td:before {
        content: attr(data-label);
        font-weight: 600;
        width: 40%;
        color: var(--secondary-color);
    }
    
    .mobile-card-view tbody td > div {
        width: 60%;
    }

    .doctor-position {
        margin-top: 0.5rem;
    }
}

/* DataTables customization */
.dataTables_wrapper {
    padding: 1rem;
}

.dataTables_length select,
.dataTables_filter input {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 0.375rem;
    margin: 0 0.5rem;
}

.dataTables_paginate .paginate_button {
    padding: 0.5rem 1rem;
    margin: 0 0.25rem;
    border-radius: 0.375rem;
    background: var(--light-bg);
    color: var(--secondary-color);
}

.dataTables_paginate .paginate_button.current {
    background: var(--primary-color);
    color: white;
}

/* Alert styles */
.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: #dcfce7;
    border: 1px solid #86efac;
    color: #166534;
}

.alert-error {
    background-color: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}
</style>
