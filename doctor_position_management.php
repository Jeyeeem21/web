<?php
//doctoc_position
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection
require_once 'config/db.php';

// Initialize error variable
$error = null;

// Disable display errors to prevent JSON corruption, log errors instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Handle AJAX requests first to avoid HTML output
if (isset($_GET['action'])) {
    // Delete (soft delete) doctor position
    if ($_GET['action'] == 'delete_doctor' && isset($_GET['id'])) {
        try {
            $id = $_GET['id'];
            $sql = "UPDATE doctor_position SET status = 0 WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            // Clean output buffer and set JSON header
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Position deleted successfully']);
            exit();
        } catch (PDOException $e) {
            // Log error and return JSON error response
            error_log("Delete doctor position error: " . $e->getMessage());
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting position: ' . $e->getMessage()]);
            exit();
        }
    }

    // Delete (soft delete) service
    if ($_GET['action'] == 'delete_service' && isset($_GET['id'])) {
        try {
            $id = $_GET['id'];
            // Get service picture to delete
            $stmt = $pdo->prepare("SELECT service_picture FROM services WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $service = $stmt->fetch();
            
            // Delete image file if it exists
            if (!empty($service['service_picture']) && file_exists($service['service_picture'])) {
                unlink($service['service_picture']);
            }

            $sql = "UPDATE services SET status = 0 WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            // Clean output buffer and set JSON header
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
            exit();
        } catch (PDOException $e) {
            // Log error and return JSON error response
            error_log("Delete service error: " . $e->getMessage());
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting service: ' . $e->getMessage()]);
            exit();
        }
    }
}

// Process form submission for adding/editing doctor positions and services
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    // Doctor position actions
    if (in_array($_POST['action'], ['add_doctor', 'edit_doctor'])) {
        $doctor_position = trim($_POST['doctor_position'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if (empty($doctor_position) || empty($description)) {
            $error = "Doctor position and description are required.";
        } else {
            if ($_POST['action'] == 'add_doctor') {
                try {
                    $status = 1;
                    $created_at = date('Y-m-d H:i:s');
                    
                    // Insert into database
                    $sql = "INSERT INTO doctor_position (doctor_position, description, status, created_at) 
                            VALUES (:doctor_position, :description, :status, :created_at)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':doctor_position' => $doctor_position,
                        ':description' => $description,
                        ':status' => $status,
                        ':created_at' => $created_at
                    ]);
                    
                    // Redirect to prevent form resubmission
                    header("Location: index.php?page=doctor_position_management&success=doctor_added");
                    exit();
                } catch (PDOException $e) {
                    $error = "Error adding doctor position: " . $e->getMessage();
                }
            }
            
            if ($_POST['action'] == 'edit_doctor') {
                $id = $_POST['id'];
                
                try {
                    // Update database
                    $sql = "UPDATE doctor_position SET doctor_position = :doctor_position, 
                            description = :description WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':id' => $id,
                        ':doctor_position' => $doctor_position,
                        ':description' => $description
                    ]);
                    
                    // Redirect to prevent form resubmission
                    header("Location: index.php?page=doctor_position_management&success=doctor_updated");
                    exit();
                } catch (PDOException $e) {
                    $error = "Error updating doctor position: " . $e->getMessage();
                }
            }
        }
    }

    // Service actions
    if (in_array($_POST['action'], ['add_service', 'edit_service', 'update_price'])) {
        if ($_POST['action'] == 'add_service') {
            $service_name = trim($_POST['service_name'] ?? '');
            $service_description = trim($_POST['service_description'] ?? '');
            $price = floatval($_POST['price'] ?? 0.00);
            $time = trim($_POST['time'] ?? '');
            $kind_of_doctor = trim($_POST['kind_of_doctor'] ?? '');
            $status = 1;
            $created_at = date('Y-m-d H:i:s');
            $service_picture = '';

            // Validate required fields
            if (empty($service_name) || empty($service_description) || empty($kind_of_doctor)) {
                $error = "All fields are required.";
            } else {
            // Handle file upload
            if (isset($_FILES['service_picture']) && $_FILES['service_picture']['error'] == 0) {
                $upload_dir = 'Uploads/services/';
                $file_name = time() . '_' . basename($_FILES['service_picture']['name']);
                $target_file = $upload_dir . $file_name;
                
                // Ensure upload directory exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['service_picture']['tmp_name'], $target_file)) {
                    $service_picture = $target_file;
                }
            }

            // Insert into database
                $sql = "INSERT INTO services (service_name, service_description, service_picture, price, time, kind_of_doctor, status, created_at) 
                        VALUES (:service_name, :service_description, :service_picture, :price, :time, :kind_of_doctor, :status, :created_at)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':service_name' => $service_name,
                ':service_description' => $service_description,
                ':service_picture' => $service_picture,
                ':price' => $price,
                    ':time' => $time,
                    ':kind_of_doctor' => $kind_of_doctor,
                ':status' => $status,
                ':created_at' => $created_at
            ]);

            // Redirect to prevent form resubmission
            header("Location: index.php?page=doctor_position_management&success=service_added");
            exit();
            }
        }

        if ($_POST['action'] == 'edit_service') {
            $id = $_POST['id'];
            $service_name = trim($_POST['service_name'] ?? '');
            $service_description = trim($_POST['service_description'] ?? '');
            $price = floatval($_POST['price'] ?? 0.00);
            $time = trim($_POST['time'] ?? '');
            $kind_of_doctor = trim($_POST['kind_of_doctor'] ?? '');
            $service_picture = $_POST['existing_picture'] ?? '';
            $status = 1;
            $created_at = date('Y-m-d H:i:s');

            // Validate required fields
            if (empty($service_name) || empty($service_description) || empty($kind_of_doctor)) {
                $error = "All fields are required.";
            } else {
            // Handle file upload for edit
            if (isset($_FILES['service_picture']) && $_FILES['service_picture']['error'] == 0) {
                $upload_dir = 'Uploads/services/';
                $file_name = time() . '_' . basename($_FILES['service_picture']['name']);
                $target_file = $upload_dir . $file_name;
                
                // Ensure upload directory exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['service_picture']['tmp_name'], $target_file)) {
                    $service_picture = $target_file;
                    // Delete old image if it exists
                    if (!empty($_POST['existing_picture']) && file_exists($_POST['existing_picture'])) {
                        unlink($_POST['existing_picture']);
                    }
                }
            }

            // Update database
            $sql = "UPDATE services SET service_name = :service_name, service_description = :service_description, 
                        service_picture = :service_picture, price = :price, time = :time, kind_of_doctor = :kind_of_doctor, 
                        status = :status, created_at = :created_at WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':service_name' => $service_name,
                ':service_description' => $service_description,
                ':service_picture' => $service_picture,
                    ':price' => $price,
                    ':time' => $time,
                    ':kind_of_doctor' => $kind_of_doctor,
                    ':status' => $status,
                    ':created_at' => $created_at
            ]);

            // Redirect to prevent form resubmission
            header("Location: index.php?page=doctor_position_management&success=service_updated");
            exit();
            }
        }

        if ($_POST['action'] == 'update_price') {
            $id = $_POST['id'];
            $price = floatval($_POST['price'] ?? 0.00);

            // Update price in database
            $sql = "UPDATE services SET price = :price WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':price' => $price
            ]);

            // Clean output buffer and set JSON header
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Price updated successfully']);
            exit();
        }
    }
}

// Get all active doctor positions
try {
    $stmt = $pdo->query("SELECT * FROM doctor_position WHERE status = 1 ORDER BY created_at DESC");
    $positions = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching doctor positions: " . $e->getMessage();
    $positions = [];
}

// Get all active services
try {
    $stmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY created_at DESC");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching services: " . $e->getMessage();
    $services = [];
}
?>

<div id="management" class="space-y-6 bg-neutral-light p-6 md:p-8 animate-fade-in">
    <h2 class="text-2xl md:text-3xl font-heading font-bold text-primary-500">Services Management</h2>
    
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
                <a href="index.php?page=doctor_position_management" class="inline-block p-3 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-t-lg hover:brightness-110 hover:scale-105 transition-all duration-200">Services</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=staff_management" class="inline-block p-3 text-secondary hover:text-primary-500 hover:bg-primary-50 hover:shadow-sm hover:scale-105 rounded-t-lg transition-all duration-200">Staff</a>
            </li>
        </ul>
    </div>

    <!-- Success/Error Message -->
    <?php if (isset($_GET['success']) || $error): ?>
    <div id="alert" class="bg-<?php echo $error ? 'red' : 'success'; ?>-50 border border-<?php echo $error ? 'red' : 'success'; ?>-200 text-<?php echo $error ? 'red' : 'success'; ?>-800 px-4 py-3 rounded-lg text-sm flex justify-between items-center shadow-sm">
        <span>
            <?php 
            if ($error) {
                echo htmlspecialchars($error);
            } elseif ($_GET['success'] == 'doctor_added') {
                echo 'Doctor position added successfully!';
            } elseif ($_GET['success'] == 'doctor_updated') {
                echo 'Doctor position updated successfully!';
            } elseif ($_GET['success'] == 'service_added') {
                echo 'Service added successfully!';
            } elseif ($_GET['success'] == 'service_updated') {
                echo 'Service updated successfully!';
            }
            ?>
        </span>
        <button type="button" onclick="document.getElementById('alert').style.display = 'none'" class="text-<?php echo $error ? 'red' : 'success'; ?>-600 hover:text-<?php echo $error ? 'red' : 'success'; ?>-800">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <?php endif; ?>

    <!-- Doctor Management Section -->
    <div id="doctor_position_management" class="space-y-6">
        <h2 class="text-xl font-medium text-neutral-dark">Doctor Position Management</h2>
        <div class="bg-white rounded-xl border border-primary-100 shadow-sm hover:shadow-md transition-all duration-200 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-neutral-dark">Doctor Positions</h3>
                <button type="button" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-3 py-1.5 rounded-lg text-sm flex items-center hover:scale-105 transition-all duration-200" onclick="openAddDoctorModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Doctor Position
                </button>
            </div>

            <!-- Doctor Table -->
            <div class="overflow-x-auto">
                <table id="doctorTable" class="min-w-full divide-y divide-primary-100 border-separate border-spacing-0 mobile-card-view">
                    <thead class="bg-neutral-light">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Position</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Description</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Created Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-primary-100">
                        <?php foreach ($positions as $position): ?>
                        <tr id="position-row-<?php echo $position['id']; ?>" class="hover:bg-primary-50 transition-colors duration-200">
                            <td class="px-4 py-2" data-label="Position">
                                <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($position['doctor_position']); ?></div>
                            </td>
                            <td class="px-4 py-2" data-label="Description">
                                <div class="text-sm text-neutral-dark truncate max-w-xs"><?php echo htmlspecialchars(substr($position['description'], 0, 50)) . (strlen($position['description']) > 50 ? '...' : ''); ?></div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap" data-label="Created Date">
                                <div class="text-sm text-neutral-dark"><?php echo date('M d, Y H:i', strtotime($position['created_at'])); ?></div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm" data-label="Actions">
                                <div class="flex space-x-1">
                                    <button type="button" class="text-primary-500 hover:text-primary-600 transition-colors duration-200" onclick="openEditDoctorModal(<?php echo $position['id']; ?>, '<?php echo addslashes($position['doctor_position']); ?>', '<?php echo addslashes($position['description']); ?>')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button type="button" class="text-accent-500 hover:text-accent-600 transition-colors duration-200" onclick="deletePosition(<?php echo $position['id']; ?>)">
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

        <!-- Doctor Modal -->
        <div id="doctorModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-xl bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-neutral-dark" id="doctorModalTitle">Add Doctor Position</h3>
                    <form id="doctorForm" method="POST" class="mt-4" onsubmit="return validateDoctorForm()">
                        <input type="hidden" name="action" value="add_doctor">
                        <input type="hidden" name="id" id="doctorId">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Doctor Position</label>
                            <input type="text" name="doctor_position" id="doctorPosition" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Description</label>
                            <textarea name="description" id="doctorDescription" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="closeDoctorModal()" class="px-4 py-2 bg-neutral-light text-neutral-dark rounded-lg hover:bg-primary-50 transition-colors duration-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg hover:scale-105 transition-all duration-200">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Management Section -->
    <div id="service_management" class="space-y-6">
        <h2 class="text-xl font-medium text-neutral-dark">Service Management</h2>
        <div class="bg-white rounded-xl border border-primary-100 shadow-sm hover:shadow-md transition-all duration-200 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-neutral-dark">Services</h3>
                <button type="button" class="bg-gradient-to-r from-primary-500 to-accent-300 text-white px-3 py-1.5 rounded-lg text-sm flex items-center hover:scale-105 transition-all duration-200" onclick="openAddServiceModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Service
                </button>
            </div>

            <!-- Service Table -->
            <div class="overflow-x-auto">
                <table id="serviceTable" class="min-w-full divide-y divide-primary-100 border-separate border-spacing-0 mobile-card-view">
                    <thead class="bg-neutral-light">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Picture</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Service Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Description</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Duration</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Price</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Kind of Doctor</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-secondary uppercase tracking-wider border-b border-primary-100">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-primary-100">
                        <?php foreach ($services as $service): ?>
                        <tr id="service-row-<?php echo $service['id']; ?>" class="hover:bg-primary-50 transition-colors duration-200">
                            <td class="px-4 py-2" data-label="Picture">
                                <div class="text-sm text-neutral-dark">
                                    <?php if ($service['service_picture']): ?>
                                        <img src="<?php echo htmlspecialchars($service['service_picture']); ?>" alt="Service Image" class="h-10 w-10 object-cover rounded-lg">
                                    <?php else: ?>
                                        <div class="h-10 w-10 rounded-lg bg-neutral-light flex items-center justify-center">
                                            <span class="text-secondary text-xs">No image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-2" data-label="Service Name">
                                <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($service['service_name']); ?></div>
                            </td>
                            <td class="px-4 py-2" data-label="Description">
                                <div class="text-sm text-neutral-dark description-cell"><?php echo htmlspecialchars($service['service_description']); ?></div>
                            </td>
                            <td class="px-4 py-2" data-label="Duration">
                                <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($service['time']); ?></div>
                            </td>
                            <td class="px-4 py-2" data-label="Price">
                                <div class="text-sm text-neutral-dark flex items-center space-x-2">
                                    <span id="price-<?php echo $service['id']; ?>">₱<?php echo number_format($service['price'], 2); ?></span>
                                    <button type="button" class="text-primary-500 hover:text-primary-600 transition-colors duration-200" onclick="openPriceModal(<?php echo $service['id']; ?>, <?php echo $service['price']; ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-2" data-label="Kind of Doctor">
                                <div class="text-sm text-neutral-dark"><?php echo htmlspecialchars($service['kind_of_doctor']); ?></div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm" data-label="Actions">
                                <div class="flex space-x-1">
                                    <button type="button" class="text-primary-500 hover:text-primary-600 transition-colors duration-200" onclick="openEditServiceModal(<?php echo $service['id']; ?>, '<?php echo addslashes($service['service_name']); ?>', '<?php echo addslashes($service['service_description']); ?>', '<?php echo addslashes($service['service_picture']); ?>', <?php echo $service['price']; ?>, '<?php echo addslashes($service['time']); ?>', '<?php echo addslashes($service['kind_of_doctor']); ?>')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button type="button" class="text-accent-500 hover:text-accent-600 transition-colors duration-200" onclick="deleteService(<?php echo $service['id']; ?>)">
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

        <!-- Service Modal -->
        <div id="serviceModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-xl bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-neutral-dark" id="serviceModalTitle">Add Service</h3>
                    <form id="serviceForm" method="POST" enctype="multipart/form-data" class="mt-4">
                        <input type="hidden" name="action" value="add_service">
                        <input type="hidden" name="id" id="serviceId">
                        <input type="hidden" name="existing_picture" id="existingPicture">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Service Name</label>
                            <input type="text" name="service_name" id="serviceName" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Description</label>
                            <textarea name="service_description" id="serviceDescription" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Picture</label>
                            <input type="file" name="service_picture" id="servicePicture" accept="image/*" class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <div id="currentImage" class="mt-2 hidden">
                                <img src="" alt="Current Image" class="h-20 w-20 object-cover rounded-lg">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Price (₱)</label>
                            <input type="number" step="10.0" name="price" id="servicePrice" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Duration</label>
                            <select name="time" id="serviceTime" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Select Duration</option>
                                <option value="30 minutes">30 minutes</option>
                                <option value="1 hour">1 hour</option>
                                <option value="2 hours">2 hours</option>
                                <option value="3 hours">3 hours</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">Kind of Doctor</label>
                            <select name="kind_of_doctor" id="serviceKindOfDoctor" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
                                <option value="">Select Doctor Position</option>
                                <?php 
                                // Get all active doctor positions
                                $stmt = $pdo->query("SELECT * FROM doctor_position WHERE status = 1 ORDER BY doctor_position ASC");
                                $doctorPositions = $stmt->fetchAll();
                                foreach ($doctorPositions as $position): 
                                    $positionName = htmlspecialchars($position['doctor_position']);
                                ?>
                                    <option value="<?php echo $positionName; ?>">
                                        <?php echo $positionName; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="closeServiceModal()" class="px-4 py-2 bg-neutral-light text-neutral-dark rounded-lg hover:bg-primary-50 transition-colors duration-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg hover:scale-105 transition-all duration-200">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Price Update Modal -->
        <div id="priceModal" class="fixed inset-0 bg-neutral-dark bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-xl bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-neutral-dark">Update Price</h3>
                    <form id="priceForm" method="POST" class="mt-4">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="id" id="priceServiceId">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-neutral-dark">New Price (₱)</label>
                            <input type="number" step="10" name="price" id="newPrice" required class="mt-1 block w-full rounded-lg border-primary-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        </div>

                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="closePriceModal()" class="px-4 py-2 bg-neutral-light text-neutral-dark rounded-lg hover:bg-primary-50 transition-colors duration-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary-500 to-accent-300 text-white rounded-lg hover:scale-105 transition-all duration-200">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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

    /* DataTable styles */
    .dataTables_wrapper {
        width: 100%;
    }
    #doctorTable, #serviceTable {
        width: 100% !important;
    }
    #doctorTable th, #doctorTable td,
    #serviceTable th, #serviceTable td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 12px;
    }
    #doctorTable tbody tr,
    #serviceTable tbody tr {
        transition: background-color 0.2s ease;
    }
    .dataTables_scrollBody {
        overflow-x: hidden !important;
    }

    /* Mobile card view */
    @media (max-width: 640px) {
        #management {
            padding: 4px;
        }
        
        /* Mobile card view for tables */
        #doctorTable.mobile-card-view thead,
        #serviceTable.mobile-card-view thead {
            display: none;
        }
        
        #doctorTable.mobile-card-view tbody tr,
        #serviceTable.mobile-card-view tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #ccfbf1;
            border-radius: 0.5rem;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            width: 100%;
            box-sizing: border-box;
        }
        
        #doctorTable.mobile-card-view tbody td,
        #serviceTable.mobile-card-view tbody td {
            display: flex;
            padding: 0.75rem;
            border: none;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
            word-break: break-word;
            white-space: normal;
        }
        
        #doctorTable.mobile-card-view tbody td:before,
        #serviceTable.mobile-card-view tbody td:before {
            content: attr(data-label);
            font-weight: 500;
            min-width: 120px;
            max-width: 40%;
            margin-right: 1rem;
            color: #475569;
            flex-shrink: 0;
        }
        
        #doctorTable.mobile-card-view tbody td .cell-content,
        #serviceTable.mobile-card-view tbody td .cell-content {
            flex: 1;
            min-width: 0;
        }
        
        /* Adjust first and last cells */
        #doctorTable.mobile-card-view tbody td:first-child,
        #serviceTable.mobile-card-view tbody td:first-child {
            padding-top: 1rem;
        }
        
        #doctorTable.mobile-card-view tbody td:last-child,
        #serviceTable.mobile-card-view tbody td:last-child {
            padding-bottom: 1rem;
        }

        /* Adjust specific columns for better mobile display */
        #serviceTable.mobile-card-view tbody td[data-label="Description"] .text-sm {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        #serviceTable.mobile-card-view tbody td[data-label="Actions"] {
            justify-content: flex-end;
        }

        /* Make sure images don't overflow */
        #serviceTable.mobile-card-view tbody td[data-label="Picture"] img,
        #serviceTable.mobile-card-view tbody td[data-label="Picture"] div {
            max-width: 100%;
            height: auto;
        }

        /* Adjust price display */
        #serviceTable.mobile-card-view tbody td[data-label="Price"] {
            flex-wrap: wrap;
        }

        #serviceTable.mobile-card-view tbody td[data-label="Price"] .flex {
            width: 100%;
        }

        /* Service table specific mobile styles */
        #serviceTable.mobile-card-view tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #ccfbf1;
            border-radius: 0.5rem;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            width: 100%;
            box-sizing: border-box;
        }

        #serviceTable.mobile-card-view tbody td {
            display: flex;
            padding: 0.75rem;
            border: none;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
            word-break: break-word;
            white-space: normal;
        }

        #serviceTable.mobile-card-view tbody td:before {
            content: attr(data-label);
            font-weight: 500;
            min-width: 120px;
            max-width: 40%;
            margin-right: 1rem;
            color: #475569;
            flex-shrink: 0;
        }

        #serviceTable.mobile-card-view tbody td .text-sm {
            flex: 1;
            min-width: 0;
        }

        /* Description cell specific styles */
        #serviceTable.mobile-card-view tbody td[data-label="Description"] .description-cell {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.4;
        }

        /* Picture cell specific styles */
        #serviceTable.mobile-card-view tbody td[data-label="Picture"] {
            justify-content: flex-start;
        }

        #serviceTable.mobile-card-view tbody td[data-label="Picture"] img,
        #serviceTable.mobile-card-view tbody td[data-label="Picture"] div {
            max-width: 100%;
            height: auto;
        }

        /* Price cell specific styles */
        #serviceTable.mobile-card-view tbody td[data-label="Price"] {
            flex-wrap: wrap;
        }

        #serviceTable.mobile-card-view tbody td[data-label="Price"] .flex {
            width: 100%;
            justify-content: space-between;
            align-items: center;
        }

        /* Actions cell specific styles */
        #serviceTable.mobile-card-view tbody td[data-label="Actions"] {
            justify-content: flex-end;
        }

        #serviceTable.mobile-card-view tbody td[data-label="Actions"] .flex {
            width: auto;
        }

        /* Adjust spacing for first and last cells */
        #serviceTable.mobile-card-view tbody td:first-child {
            padding-top: 1rem;
        }

        #serviceTable.mobile-card-view tbody td:last-child {
            padding-bottom: 1rem;
        }
    }
</style>

<script>
function openAddDoctorModal() {
    document.getElementById('doctorModalTitle').textContent = 'Add Doctor Position';
    document.getElementById('doctorForm').reset();
    document.getElementById('doctorForm').querySelector('input[name="action"]').value = 'add_doctor';
    document.getElementById('doctorId').value = '';
    document.getElementById('doctorModal').classList.remove('hidden');
}

function openEditDoctorModal(id, position, description) {
    document.getElementById('doctorModalTitle').textContent = 'Edit Doctor Position';
    document.getElementById('doctorForm').querySelector('input[name="action"]').value = 'edit_doctor';
    document.getElementById('doctorId').value = id;
    document.getElementById('doctorPosition').value = position;
    document.getElementById('doctorDescription').value = description;
    document.getElementById('doctorModal').classList.remove('hidden');
}

function closeDoctorModal() {
    document.getElementById('doctorModal').classList.add('hidden');
}

function validateDoctorForm() {
    const position = document.getElementById('doctorPosition').value.trim();
    const description = document.getElementById('doctorDescription').value.trim();
    if (!position || !description) {
        alert('Please fill in both Doctor Position and Description.');
        return false;
    }
    return true;
}

function deletePosition(id) {
    if (confirm('Are you sure you want to delete this position?')) {
        fetch(`index.php?page=doctor_position_management&action=delete_doctor&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`position-row-${id}`).remove();
                    window.location.reload(); // Reload the page after deletion
                }
            });
    }
}

/* Optional: Improved deletePosition with error handling
function deletePosition(id) {
    if (confirm('Are you sure you want to delete this position?')) {
        fetch(`index.php?page=doctor_position_management&action=delete_doctor&id=${id}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById(`position-row-${id}`).remove();
                    window.location.reload();
                } else {
                    alert(data.message || 'Error deleting position.');
                }
            })
            .catch(error => {
                alert('Error deleting position: ' + error.message);
            });
    }
}
*/

function openAddServiceModal() {
    document.getElementById('serviceModalTitle').textContent = 'Add Service';
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceForm').action.value = 'add_service';
    document.getElementById('serviceId').value = '';
    document.getElementById('existingPicture').value = '';
    document.getElementById('servicePicture').required = true;
    document.getElementById('currentImage').classList.add('hidden');
    document.getElementById('serviceModal').classList.remove('hidden');
}

function openEditServiceModal(id, name, description, picture, price, time, kind_of_doctor) {
    document.getElementById('serviceModalTitle').textContent = 'Edit Service';
    document.getElementById('serviceForm').action.value = 'edit_service';
    document.getElementById('serviceId').value = id;
    document.getElementById('serviceName').value = name;
    document.getElementById('serviceDescription').value = description;
    document.getElementById('existingPicture').value = picture;
    document.getElementById('servicePrice').value = price;
    document.getElementById('serviceTime').value = time;
    
    // Set the selected option in the Kind of Doctor dropdown
    const kindOfDoctorSelect = document.getElementById('serviceKindOfDoctor');
    const options = kindOfDoctorSelect.options;
    for (let i = 0; i < options.length; i++) {
        if (options[i].value === kind_of_doctor) {
            kindOfDoctorSelect.selectedIndex = i;
            break;
        }
    }
    
    document.getElementById('servicePicture').required = false;
    if (picture) {
        document.getElementById('currentImage').classList.remove('hidden');
        document.getElementById('currentImage').querySelector('img').src = picture;
    } else {
        document.getElementById('currentImage').classList.add('hidden');
    }
    document.getElementById('serviceModal').classList.remove('hidden');
}

function openPriceModal(id, price) {
    document.getElementById('priceServiceId').value = id;
    document.getElementById('newPrice').value = price;
    document.getElementById('priceModal').classList.remove('hidden');
}

function closeServiceModal() {
    document.getElementById('serviceModal').classList.add('hidden');
}

function closePriceModal() {
    document.getElementById('priceModal').classList.add('hidden');
}

function deleteService(id) {
    if (confirm('Are you sure you want to delete this service?')) {
        fetch(`index.php?page=doctor_position_management&action=delete_service&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`service-row-${id}`).remove();
                    window.location.reload(); // Reload the page after deletion
                }
            });
    }
}

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
            $('.paginate_button:not(.current)').addClass('bg-gray-100 text-gray-700 hover:bg-gray-200');
            $('.paginate_button.disabled').addClass('opacity-50 cursor-not-allowed');
            
            // Ensure clickable area for next/previous buttons
            $('.paginate_button.next, .paginate_button.previous').addClass('px-3');
            
            // Custom length menu styling
            $('.dataTables_length select').addClass('rounded-md border-gray-300 text-sm');
            
            // Custom search box styling
            $('.dataTables_filter input').addClass('rounded-md border-gray-300 text-sm');
        }
    };

    // Initialize DataTables with common configuration
    $('#doctorTable').DataTable({
        ...commonConfig,
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });

    $('#serviceTable').DataTable({
        ...commonConfig,
        columnDefs: [
            { orderable: false, targets: -1 }
        ]
    });

    // Handle price form submission
    $('#priceForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('index.php?page=doctor_position_management', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`price-${formData.get('id')}`).textContent = `₱${parseFloat(formData.get('price')).toFixed(2)}`;
                closePriceModal();
            }
        });
    });
});
</script>

<?php
// Flush the output buffer
ob_end_flush();
?>