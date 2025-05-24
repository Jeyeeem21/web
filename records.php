<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
</head>
<body class="bg-neutral-light font-body">
<?php
// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'D:/xampp/htdocs/Clinic/logs/php_errors.log');

// Start output buffering
ob_start();

// Include database connection
try {
    require_once 'config/db.php';
} catch (Exception $e) {
    ob_clean();
    echo '<div class="bg-red-100 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm m-4 animate-fade-in">';
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new patient
        if ($_POST['action'] == 'add') {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $address = $_POST['address'] ?? '';
            $birthdate = $_POST['birthdate'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $other_gender = $_POST['other_gender'] ?? '';
            $status = 1;

            // Calculate age
            try {
                $birthDate = new DateTime($birthdate);
                $today = new DateTime();
                $age = $birthDate->diff($today)->y;
            } catch (Exception $e) {
                $error_message = "Invalid birthdate: " . $e->getMessage();
                header("Location: index.php?page=records&error=" . urlencode($error_message));
                exit();
            }

            $photo = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $target_dir = "Uploads/patients/";
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

                $sql = "INSERT INTO patients (name, email, phone, address, birthdate, age, photo, gender, status, created_at) 
                        VALUES (:name, :email, :phone, :address, :birthdate, :age, :photo, :gender, :status, CURRENT_TIMESTAMP)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':birthdate' => $birthdate,
                    ':age' => $age,
                    ':photo' => $photo,
                    ':gender' => $gender,
                    ':status' => $status
                ]);

                $patient_id = $pdo->lastInsertId();
                $pdo->commit();

                if ($patient_id) {
                    header("Location: index.php?page=records&action=show_credentials&patient_id=" . $patient_id);
                    exit();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error adding patient: " . $e->getMessage();
                header("Location: index.php?page=records&error=" . urlencode($error_message));
                exit();
            }
        }

        // Add user credentials
        if ($_POST['action'] == 'add_credentials') {
            $patient_id = $_POST['patient_id'] ?? '';
            $username = htmlspecialchars(trim($_POST['username'] ?? ''));
            $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
            $status = 1;

            try {
                // Check for duplicate username
                $check_sql = "SELECT COUNT(*) FROM users WHERE username = :username";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':username' => $username]);

                if ($check_stmt->fetchColumn() > 0) {
                    header("Location: index.php?page=records&action=show_credentials&patient_id=" . $patient_id . "&error=" . urlencode("Username already exists. Please choose a different username."));
                    exit();
                }

                $sql = "INSERT INTO users (username, pass, patient_id, status) 
                        VALUES (:username, :password, :patient_id, :status)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $password,
                    ':patient_id' => $patient_id,
                    ':status' => $status
                ]);

                header("Location: index.php?page=records&success=added");
                exit();
            } catch (PDOException $e) {
                $error_message = "Error creating user account: " . $e->getMessage();
                header("Location: index.php?page=records&action=show_credentials&patient_id=" . $patient_id . "&error=" . urlencode($error_message));
                exit();
            }
        }

        // Edit patient
        if ($_POST['action'] == 'edit') {
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $address = $_POST['address'] ?? '';
            $birthdate = $_POST['birthdate'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $other_gender = $_POST['other_gender'] ?? '';

            try {
                $birthDate = new DateTime($birthdate);
                $today = new DateTime();
                $age = $birthDate->diff($today)->y;
            } catch (Exception $e) {
                $error_message = "Invalid birthdate: " . $e->getMessage();
                header("Location: index.php?page=records&error=" . urlencode($error_message));
                exit();
            }

            $photo = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $target_dir = "Uploads/patients/";
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

                $sql = "UPDATE patients SET 
                        name = :name,
                        email = :email,
                        phone = :phone,
                        address = :address,
                        birthdate = :birthdate,
                        age = :age,
                        gender = :gender,
                        updated_at = CURRENT_TIMESTAMP";
                
                if (!empty($photo)) {
                    $sql .= ", photo = :photo";
                }

                $sql .= " WHERE id = :id";

                $params = [
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':birthdate' => $birthdate,
                    ':age' => $age,
                    ':gender' => $gender,
                    ':id' => $id
                ];

                if (!empty($photo)) {
                    $params[':photo'] = $photo;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $pdo->commit();
                header("Location: index.php?page=records&success=updated");
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error updating patient: " . $e->getMessage();
                header("Location: index.php?page=records&error=" . urlencode($error_message));
                exit();
            }
        }

        // Edit user credentials
        if ($_POST['action'] == 'edit_credentials') {
            $user_id = $_POST['user_id'] ?? '';
            $username = htmlspecialchars(trim($_POST['username'] ?? ''));
            $patient_id = $_POST['patient_id'] ?? '';
            $password = $_POST['password'] ?? '';

            try {
                $pdo->beginTransaction();

                // Check for duplicate username (excluding current user)
                $check_sql = "SELECT COUNT(*) FROM users WHERE username = :username AND id != :user_id";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([
                    ':username' => $username,
                    ':user_id' => $user_id
                ]);

                if ($check_stmt->fetchColumn() > 0) {
                    throw new Exception("Username already exists. Please choose a different username.");
                }

                // Update user
                $sql = "UPDATE users SET username = :username, patient_id = :patient_id";
                $params = [
                    ':username' => $username,
                    ':patient_id' => $patient_id,
                    ':user_id' => $user_id
                ];

                // Only update password if a new one is provided
                if (!empty($password)) {
                    $sql .= ", pass = :password";
                    $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE id = :user_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $pdo->commit();

                header("Location: index.php?page=records&success=updated");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Error updating user account: " . $e->getMessage();
                header("Location: index.php?page=records&error=" . urlencode($error_message));
                exit();
            }
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    // Clear any existing output and set headers
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();
        $check_user_sql = "SELECT COUNT(*) FROM users WHERE patient_id = :patient_id AND status = 1";
        $check_user_stmt = $pdo->prepare($check_user_sql);
        $check_user_stmt->execute([':patient_id' => $id]);
        if ($check_user_stmt->fetchColumn() > 0) {
            throw new Exception("Cannot delete patient with an active user account. Please delete or deactivate the user account first.");
        }
        $stmt = $pdo->prepare("UPDATE patients SET status = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pdo->commit();
        $response = ['success' => true];
        error_log("Delete response for ID $id: " . json_encode($response));
        echo json_encode($response);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $response = ['success' => false, 'error' => $e->getMessage()];
        error_log("Delete error for ID $id: " . json_encode($response));
        echo json_encode($response);
        exit();
    }
}

// Handle delete user action
if (isset($_GET['action']) && $_GET['action'] == 'delete_user' && isset($_GET['id'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE users SET status = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Get all active patients
try {
    $stmt = $pdo->query("SELECT * FROM patients WHERE status = 1 ORDER BY created_at DESC");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_clean();
    echo '<div class="bg-red-100 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm m-4 animate-fade-in">';
    echo 'Error fetching patients: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
    $patients = [];
}
?>

<div id="records" class="space-y-8 bg-neutral-light p-6 md:p-8 animate-fade-in">
    <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Patient Records</h2>

    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div id="successAlert" class="bg-success-light border border-success text-success px-4 py-3 rounded-xl text-sm flex justify-between items-center animate-slide-up">
        <span>
            <?php 
            if ($_GET['success'] == 'added') echo 'Patient added successfully!';
            if ($_GET['success'] == 'updated') echo 'Patient updated successfully!';
            ?>
        </span>
       ckt type="button" onclick="document.getElementById('successAlert').style.display = 'none'" class="text-success hover:text-success-dark">
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
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (isset($_GET['error'])): ?>
    <div id="errorAlert" class="bg-red-100 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex justify-between items-center animate-slide-up">
        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        <button type="button" onclick="document.getElementById('errorAlert').style.display = 'none'" class="text-red-600 hover:text-red-800">
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
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- Patients Table -->
    <div class="bg-white rounded-xl shadow-sm border border-primary-100 p-6 animate-slide-up">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-base font-medium text-neutral-dark">Patient List</h3>
            <button type="button" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 hover:scale-105 transition-all duration-200" onclick="openAddModal()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Patient
            </button>
        </div>

        <div class="overflow-x-auto">
            <table id="patientsTable" class="min-w-full divide-y divide-primary-100 mobile-card-view">
                <thead class="bg-primary-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Photo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Last Visit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    <?php foreach ($patients as $patient): ?>
                    <tr class="hover:bg-primary-50" id="patient-row-<?php echo $patient['id']; ?>">
                        <td class="px-4 py-3" data-label="Photo">
                            <?php if (!empty($patient['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($patient['photo']); ?>" alt="Patient Photo" class="h-10 w-10 rounded-full object-cover">
                            <?php else: ?>
                            <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center">
                                <span class="text-primary-500 text-xs">No photo</span>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3" data-label="Name">
                            <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($patient['name']); ?></div>
                            <div class="text-xs text-secondary"><?php echo htmlspecialchars($patient['email']); ?></div>
                        </td>
                        <td class="px-4 py-3" data-label="Phone">
                            <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($patient['phone']); ?></div>
                        </td>
                        <td class="px-4 py-3" data-label="Last Visit">
                            <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($patient['updated_at'] ? date('M d, Y', strtotime($patient['updated_at'])) : 'N/A'); ?></div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm" data-label="Actions">
                            <div class="flex space-x-3">
                                <button type="button" class="text-primary-500 hover:text-primary-700 transition-all duration-200" onclick="openEditModal(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['name']); ?>', '<?php echo htmlspecialchars($patient['email']); ?>', '<?php echo htmlspecialchars($patient['phone']); ?>', '<?php echo htmlspecialchars($patient['address']); ?>', '<?php echo htmlspecialchars($patient['birthdate']); ?>', '<?php echo htmlspecialchars($patient['gender']); ?>', '<?php echo htmlspecialchars($patient['photo']); ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" class="text-red-600 hover:text-red-800 transition-all duration-200" onclick="deletePatient(<?php echo $patient['id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

    <!-- Patient Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-primary-100 p-6 animate-slide-up">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-base font-medium text-neutral-dark">Patient User Accounts</h3>
            <button type="button" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 hover:scale-105 transition-all duration-200" onclick="openAddUserModal()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add User Account
            </button>
        </div>

        <div class="overflow-x-auto">
            <table id="patientUsersTable" class="min-w-full divide-y divide-primary-100 mobile-card-view">
                <thead class="bg-primary-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Username</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Patient Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-primary-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary-100">
                    <?php
                    // Get all patient users
                    $stmt = $pdo->query("
                        SELECT u.*, p.name as patient_name 
                        FROM users u 
                        JOIN patients p ON u.patient_id = p.id 
                        WHERE u.patient_id IS NOT NULL 
                        ORDER BY u.id DESC
                    ");
                    $patient_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($patient_users as $user): 
                    ?>
                    <tr class="hover:bg-primary-50">
                        <td class="px-4 py-3" data-label="Username">
                            <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($user['username']); ?></div>
                        </td>
                        <td class="px-4 py-3" data-label="Patient Name">
                            <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($user['patient_name']); ?></div>
                        </td>
                        <td class="px-4 py-3" data-label="Status">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['status'] ? 'bg-success-light text-success' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3" data-label="Actions">
                            <div class="flex space-x-3">
                                <button type="button" class="text-primary-500 hover:text-primary-700 transition-all duration-200" onclick="openEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['patient_id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" class="text-red-600 hover:text-red-800 transition-all duration-200" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

    <!-- Patient Modal -->
    <div id="patientModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-neutral-dark" id="modalTitle">Add Patient</h3>
                <form id="patientForm" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" id="patientId">

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Name</label>
                        <input type="text" name="name" id="name" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Email</label>
                        <input type="email" name="email" id="email" class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Phone</label>
                        <input type="text" name="phone" id="phone" class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Address</label>
                        <textarea name="address" id="address" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Birthdate</label>
                        <input type="date" name="birthdate" id="birthdate" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Gender</label>
                        <select name="gender" id="gender" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3" onchange="toggleOtherGender()">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div id="otherGenderDiv" class="hidden">
                        <label class="block text-sm font-medium text-neutral-dark">Specify Gender</label>
                        <input type="text" name="other_gender" id="other_gender" class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Photo</label>
                        <input type="file" name="photo" id="photo" accept="image/*" class="mt-1 block w-full text-sm text-neutral-dark file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-500 hover:file:bg-primary-100">
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 bg-primary-50 text-primary-500 rounded-lg text-sm hover:bg-primary-100 transition-all duration-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg text-sm hover:scale-105 transition-all duration-200">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add User Account Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-neutral-dark">Add User Account</h3>
                <form id="addUserForm" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="add_credentials">
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Patient</label>
                        <select name="patient_id" id="addUserPatientId" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                            <option value="">Select Patient</option>
                            <?php
                            // Get patients without user accounts
                            $stmt = $pdo->query("
                                SELECT p.* 
                                FROM patients p 
                                LEFT JOIN users u ON p.id = u.patient_id 
                                WHERE p.status = 1 AND u.id IS NULL
                            ");
                            $patients_without_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($patients_without_users as $patient): 
                            ?>
                            <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Username</label>
                        <input type="text" name="username" id="addUserUsername" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>

                    <div class="relative">
                        <label class="block text-sm font-medium text-neutral-dark">Password</label>
                        <input type="password" name="password" id="addUserPassword" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3 pr-10">
                        <button type="button" id="toggleAddUserPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 mt-6 text-primary-500 hover:text-primary-700">
                            <svg id="addUserEyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddUserModal()" class="px-4 py-2 bg-primary-50 text-primary-500 rounded-lg text-sm hover:bg-primary-100 transition-all duration-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg text-sm hover:scale-105 transition-all duration-200">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Account Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md md:w-[90%] shadow-lg rounded-xl bg-white border-primary-100">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-neutral-dark">Edit User Account</h3>
                <form id="editUserForm" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="edit_credentials">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Patient</label>
                        <select name="patient_id" id="editUserPatientId" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                            <option value="">Select Patient</option>
                            <?php
                            // Get all active patients
                            $stmt = $pdo->query("SELECT * FROM patients WHERE status = 1 ORDER BY name");
                            $all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($all_patients as $patient): 
                            ?>
                            <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-neutral-dark">Username</label>
                        <input type="text" name="username" id="editUserUsername" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3">
                    </div>
                    
                    <div class="relative">
                        <label class="block text-sm font-medium text-neutral-dark">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" id="editUserPassword" class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 text-sm py-2 px-3 pr-10">
                        <button type="button" id="toggleEditUserPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 mt-6 text-primary-500 hover:text-primary-700">
                            <svg id="editUserEyeIcon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 bg-primary-50 text-primary-500 rounded-lg text-sm hover:bg-primary-100 transition-all duration-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg text-sm hover:scale-105 transition-all duration-200">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Patient';
    document.getElementById('patientForm').reset();
    document.querySelector('#patientForm input[name="action"]').value = 'add';
    document.getElementById('patientId').value = '';
    document.getElementById('patientModal').classList.remove('hidden');
    toggleOtherGender();
}

function openEditModal(id, name, email, phone, address, birthdate, gender, photo) {
    document.getElementById('modalTitle').textContent = 'Edit Patient';
    document.querySelector('#patientForm input[name="action"]').value = 'edit';
    document.getElementById('patientId').value = id;
    document.getElementById('name').value = name;
    document.getElementById('email').value = email;
    document.getElementById('phone').value = phone;
    document.getElementById('address').value = address;
    document.getElementById('birthdate').value = birthdate;
    document.getElementById('gender').value = ['Male', 'Female'].includes(gender) ? gender : 'Other';
    document.getElementById('otherGenderDiv').classList.toggle('hidden', !['Male', 'Female'].includes(gender) ? false : true);
    document.getElementById('other_gender').value = ['Male', 'Female'].includes(gender) ? '' : gender;
    document.getElementById('patientModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('patientModal').classList.add('hidden');
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.add('hidden');
}

function toggleOtherGender() {
    const gender = document.getElementById('gender').value;
    const otherGenderDiv = document.getElementById('otherGenderDiv');
    otherGenderDiv.classList.toggle('hidden', gender !== 'Other');
}

function deletePatient(id) {
    if (confirm('Are you sure you want to mark this patient as inactive?')) {
        fetch(`index.php?page=records&action=delete&id=${id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', Object.fromEntries(response.headers.entries()));
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    document.getElementById(`patient-row-${id}`).remove();
                    alert('Patient marked as inactive successfully');
                } else {
                    alert('Error marking patient as inactive: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Failed to parse JSON. Raw response:', text);
                console.error('Parse error:', e);
                alert('Error marking patient as inactive: Server returned invalid JSON. Check console for details.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error marking patient as inactive: ' + error.message);
        });
    }
}

<?php if (isset($_GET['action']) && $_GET['action'] == 'show_credentials' && isset($_GET['patient_id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('patient_id').value = '<?php echo htmlspecialchars($_GET['patient_id']); ?>';
    document.getElementById('credentialsModal').classList.remove('hidden');
});
<?php endif; ?>

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

$(document).ready(function() {
    // Common DataTable configuration
    const commonConfig = {
        responsive: true,
        language: {
            search: "",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "«",
                last: "»",
                next: "›",
                previous: "‹"
            }
        },
        dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"<"mb-4 md:mb-0"l><"flex items-center"f>>rtip',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        pageLength: 10,
        scrollX: false,
        autoWidth: false,
        drawCallback: function() {
            // Custom pagination styling
            $('.dataTables_paginate').addClass('flex justify-center mt-4');
            $('.paginate_button').addClass('px-2 py-1 mx-0.5 rounded text-xs cursor-pointer');
            $('.paginate_button.current').addClass('bg-primary-600 text-white');
            $('.paginate_button:not(.current)').addClass('bg-primary-50 text-primary-500 hover:bg-primary-100');
            $('.paginate_button.disabled').addClass('opacity-50 cursor-not-allowed');
            
            // Ensure clickable area for next/previous buttons
            $('.paginate_button.next, .paginate_button.previous').addClass('px-3');
            
            // Custom length menu styling
            $('.dataTables_length select').addClass('rounded-lg border-primary-100 text-sm py-2 px-3 focus:border-primary-500 focus:ring-2 focus:ring-primary-500');
            
            // Custom search box styling
            $('.dataTables_filter input').addClass('rounded-lg border-primary-100 text-sm py-2 px-3 focus:border-primary-500 focus:ring-2 focus:ring-primary-500');
        }
    };

    // Initialize DataTables with common configuration
    $('#patientsTable').DataTable({
        ...commonConfig,
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });

    $('#patientUsersTable').DataTable({
        ...commonConfig,
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });
});

// Add User Modal Functions
function openAddUserModal() {
    document.getElementById('addUserModal').classList.remove('hidden');
}

// Password toggle for add user modal
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

// Delete user function
function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user account?')) {
        fetch(`index.php?page=records&action=delete_user&id=${id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting user account: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                alert('Error deleting user account: Invalid server response');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting user account: ' + error.message);
        });
    }
}

// Edit User Modal Functions
function openEditUserModal(id, username, patientId) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserUsername').value = username;
    document.getElementById('editUserPatientId').value = patientId;
    document.getElementById('editUserPassword').value = ''; // Clear password field
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

// Password toggle for edit user modal
document.getElementById('toggleEditUserPassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('editUserPassword');
    const eyeIcon = document.getElementById('editUserEyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
    }
});
</script>

<style>
    /* Tailwind custom fonts */
    .font-heading {
        font-family: 'Poppins', sans-serif;
    }
    .font-body {
        font-family: 'Inter', sans-serif;
    }

    /* Custom animations */
    .animate-fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    .animate-slide-up {
        animation: slideUp 0.5s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    ::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb {
        background: #ccfbf1;
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #99f6e4;
    }

    /* Smooth transitions */
    .transition-all {
        transition: all 0.3s ease;
    }

    /* Hover lift effect */
    .hover-lift:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* DataTable styles */
    .dataTables_wrapper {
        width: 100%;
    }
    #patientsTable, #patientUsersTable {
        width: 100% !important;
    }
    #patientsTable th, #patientsTable td,
    #patientUsersTable th, #patientUsersTable td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 12px;
    }
    #patientsTable tbody tr,
    #patientUsersTable tbody tr {
        transition: background-color 0.2s ease;
    }
    .dataTables_scrollBody {
        overflow-x: hidden !important;
    }

    /* Mobile card view adjustments */
    @media (max-width: 640px) {
        #records {
            padding: 4px;
        }
        .mobile-card-view thead {
            display: none;
        }
        .mobile-card-view tbody tr {
            display: block;
            margin-bottom: 1rem;
            background: #ffffff;
            border: 1px solid #ccfbf1;
            border-radius: 0.5rem;
            padding: 0.75rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .mobile-card-view tbody td {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .mobile-card-view tbody td:last-child {
            border-bottom: none;
        }
        .mobile-card-view tbody td:before {
            content: attr(data-label);
            font-weight: 600;
            width: 40%;
            color: #475569;
            font-size: 0.75rem;
        }
        .mobile-card-view tbody td > div {
            width: 60%;
            font-size: 0.875rem;
        }
        .mobile-card-view .px-2.py-1.inline-flex {
            margin-left: auto;
        }
        .dataTables_length, 
        .dataTables_filter, 
        .dataTables_info, 
        .dataTables_paginate {
            width: 100%;
            margin-bottom: 0.75rem;
            text-align: center;
        }
        .fixed.inset-0 > div {
            width: 90% !important;
            max-width: none !important;
            margin: 0 auto;
        }
    }
</style>
</body>
</html>