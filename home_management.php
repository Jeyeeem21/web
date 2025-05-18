<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection
require_once 'config/db.php';

// Process form submission for adding/editing content
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new home content
        if ($_POST['action'] == 'add_home') {
            $maintext = $_POST['maintext'] ?? '';
            $secondtext = $_POST['secondtext'] ?? '';
            $thirdtext = $_POST['thirdtext'] ?? '';
            $status = 1;
            $createdDate = date('Y-m-d H:i:s');
            
            // Handle home image upload
            $homePic = '';
            if (isset($_FILES['homePic']) && $_FILES['homePic']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $homePic = $target_dir . time() . '_' . basename($_FILES["homePic"]["name"]);
                move_uploaded_file($_FILES["homePic"]["tmp_name"], $homePic);
            }
            
            // Insert into database
            $sql = "INSERT INTO home (homePic, maintext, secondtext, thirdtext, status, createdDate) 
                    VALUES (:homePic, :maintext, :secondtext, :thirdtext, :status, :createdDate)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':homePic' => $homePic,
                ':maintext' => $maintext,
                ':secondtext' => $secondtext,
                ':thirdtext' => $thirdtext,
                ':status' => $status,
                ':createdDate' => $createdDate
            ]);
            
            // Redirect to prevent form resubmission
            header("Location: index.php?page=home_management&success=added");
            exit();
        }
        
        // Add new about content
        if ($_POST['action'] == 'add_about') {
            $aboutText = $_POST['aboutText'] ?? '';
            $status = 1;
            $createdDate = date('Y-m-d H:i:s');
            
            // Handle about image upload
            $aboutPic = '';
            if (isset($_FILES['aboutPic']) && $_FILES['aboutPic']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $aboutPic = $target_dir . time() . '_' . basename($_FILES["aboutPic"]["name"]);
                move_uploaded_file($_FILES["aboutPic"]["tmp_name"], $aboutPic);
            }
            
            // Insert into database
            $sql = "INSERT INTO about (aboutPic, aboutText, status, createdDate) 
                    VALUES (:aboutPic, :aboutText, :status, :createdDate)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':aboutPic' => $aboutPic,
                ':aboutText' => $aboutText,
                ':status' => $status,
                ':createdDate' => $createdDate
            ]);
            
            // Redirect to prevent form resubmission
            header("Location: index.php?page=home_management&success=added");
            exit();
        }
        
        // Edit existing home content
        if ($_POST['action'] == 'edit_home') {
            $id = $_POST['id'];
            $maintext = $_POST['maintext'] ?? '';
            $secondtext = $_POST['secondtext'] ?? '';
            $thirdtext = $_POST['thirdtext'] ?? '';
            
            // Get existing record to check for image
            $stmt = $pdo->prepare("SELECT homePic FROM home WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $existingRecord = $stmt->fetch();
            
            // Handle home image upload
            $homePic = $existingRecord['homePic']; // Default to existing image
            if (isset($_FILES['homePic']) && $_FILES['homePic']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $homePic = $target_dir . time() . '_' . basename($_FILES["homePic"]["name"]);
                move_uploaded_file($_FILES["homePic"]["tmp_name"], $homePic);
            }
            
            // Update database
            $sql = "UPDATE home SET homePic = :homePic, maintext = :maintext, 
                    secondtext = :secondtext, thirdtext = :thirdtext 
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':homePic' => $homePic,
                ':maintext' => $maintext,
                ':secondtext' => $secondtext,
                ':thirdtext' => $thirdtext
            ]);
            
            // Redirect to prevent form resubmission
            header("Location: index.php?page=home_management&success=updated");
            exit();
        }
        
        // Edit existing about content
        if ($_POST['action'] == 'edit_about') {
            $id = $_POST['id'];
            $aboutText = $_POST['aboutText'] ?? '';
            
            // Get existing record to check for image
            $stmt = $pdo->prepare("SELECT aboutPic FROM about WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $existingRecord = $stmt->fetch();
            
            // Handle about image upload
            $aboutPic = $existingRecord['aboutPic']; // Default to existing image
            if (isset($_FILES['aboutPic']) && $_FILES['aboutPic']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $aboutPic = $target_dir . time() . '_' . basename($_FILES["aboutPic"]["name"]);
                move_uploaded_file($_FILES["aboutPic"]["tmp_name"], $aboutPic);
            }
            
            // Update database
            $sql = "UPDATE about SET aboutPic = :aboutPic, aboutText = :aboutText WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':aboutPic' => $aboutPic,
                ':aboutText' => $aboutText
            ]);
            
            // Redirect to prevent form resubmission
            header("Location: index.php?page=home_management&success=updated");
            exit();
        }
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    // Delete (soft delete) home content
    if ($_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['type']) && $_GET['type'] == 'home') {
        $id = $_GET['id'];
        $sql = "UPDATE home SET status = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        // Return success response for AJAX
        echo json_encode(['success' => true, 'message' => 'Content deleted successfully']);
        exit();
    }
    
    // Delete (soft delete) about content
    if ($_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['type']) && $_GET['type'] == 'about') {
        $id = $_GET['id'];
        $sql = "UPDATE about SET status = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        // Return success response for AJAX
        echo json_encode(['success' => true, 'message' => 'Content deleted successfully']);
        exit();
    }
    
    // Set as active (update createdDate) for home content
    if ($_GET['action'] == 'use' && isset($_GET['id']) && isset($_GET['type']) && $_GET['type'] == 'home') {
        $id = $_GET['id'];
        $createdDate = date('Y-m-d H:i:s');
        $sql = "UPDATE home SET createdDate = :createdDate WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':createdDate' => $createdDate
        ]);
        
        // Return success response for AJAX
        echo json_encode(['success' => true, 'message' => 'Content set as active']);
        exit();
    }
    
    // Set as active (update createdDate) for about content
    if ($_GET['action'] == 'use' && isset($_GET['id']) && isset($_GET['type']) && $_GET['type'] == 'about') {
        $id = $_GET['id'];
        $createdDate = date('Y-m-d H:i:s');
        $sql = "UPDATE about SET createdDate = :createdDate WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':createdDate' => $createdDate
        ]);
        
        // Return success response for AJAX
        echo json_encode(['success' => true, 'message' => 'Content set as active']);
        exit();
    }
}

// Get all active home content
$stmt = $pdo->query("SELECT * FROM home WHERE status = 1 ORDER BY createdDate DESC");
$homeContents = $stmt->fetchAll();

// Get all active about content
$stmt = $pdo->query("SELECT * FROM about WHERE status = 1 ORDER BY createdDate DESC");
$aboutContents = $stmt->fetchAll();
?>

<div id="home_management" class="space-y-6">
    <h2 class="text-xl font-medium text-gray-800">Content Management</h2>
    
    <!-- Tabs for Information Sections -->
    <div class="border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm">
            <li class="mr-2">
                <a href="index.php?page=information" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Overview</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=home_management" class="inline-block p-3 border-b-2 border-primary-600 text-primary-600">Data</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=doctor_position_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Services</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=staff_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Staff</a>
            </li>
        </ul>
    </div>
    
    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div id="successAlert" class="bg-green-50 border border-green-200 text-green-800 px-3 py-2 rounded-md text-sm flex justify-between items-center">
        <span>
            <?php 
            if ($_GET['success'] == 'added') echo 'Content added successfully!';
            if ($_GET['success'] == 'updated') echo 'Content updated successfully!';
            ?>
        </span>
        <button type="button" onclick="document.getElementById('successAlert').style.display = 'none'" class="text-green-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <script>
        // Auto hide success message after 3 seconds
        setTimeout(function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                successAlert.style.display = 'none';
            }
        }, 3000);
    </script>
    <?php endif; ?>
    
    <!-- Home Content Section -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Home Content</h3>
            <button type="button" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm flex items-center" onclick="openAddHomeModal()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
                Add Home Content
        </button>
    </div>
    
        <!-- Home Content Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Home Image</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Main Text</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($homeContents as $content): ?>
                    <tr id="home-row-<?php echo $content['id']; ?>" class="hover:bg-gray-50">
                    <td class="px-4 py-2 whitespace-nowrap">
                        <?php if (!empty($content['homePic'])): ?>
                        <img src="<?php echo htmlspecialchars($content['homePic']); ?>" alt="Home Image" class="h-8 w-12 object-cover rounded-md border border-gray-200">
                        <?php else: ?>
                        <span class="text-gray-400 text-xs">No image</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2">
                        <div class="text-sm text-gray-900 truncate max-w-xs"><?php echo htmlspecialchars(substr($content['maintext'], 0, 50)) . (strlen($content['maintext']) > 50 ? '...' : ''); ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($content['createdDate'])); ?></div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm">
                        <div class="flex space-x-1">
                                <button type="button" class="text-primary-600 hover:text-primary-800" onclick="useHomeContent(<?php echo $content['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                                <button type="button" class="text-blue-600 hover:text-blue-800" onclick="openEditHomeModal(<?php echo $content['id']; ?>, '<?php echo addslashes($content['maintext']); ?>', '<?php echo addslashes($content['secondtext']); ?>', '<?php echo addslashes($content['thirdtext']); ?>', '<?php echo addslashes($content['homePic']); ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                                <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteHomeContent(<?php echo $content['id']; ?>)">
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

    <!-- About Content Section -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">About Content</h3>
            <button type="button" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm flex items-center" onclick="openAddAboutModal()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add About Content
            </button>
        </div>
        
        <!-- About Content Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">About Text</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($aboutContents as $content): ?>
                    <tr id="about-row-<?php echo $content['id']; ?>" class="hover:bg-gray-50">
                        <td class="px-4 py-2 whitespace-nowrap">
                            <?php if (!empty($content['aboutPic'])): ?>
                            <img src="<?php echo htmlspecialchars($content['aboutPic']); ?>" alt="About Image" class="h-8 w-12 object-cover rounded-md border border-gray-200">
                    <?php else: ?>
                            <span class="text-gray-400 text-xs">No image</span>
                    <?php endif; ?>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm text-gray-900 truncate max-w-xs"><?php echo htmlspecialchars(substr($content['aboutText'], 0, 50)) . (strlen($content['aboutText']) > 50 ? '...' : ''); ?></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($content['createdDate'])); ?></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                            <div class="flex space-x-1">
                                <button type="button" class="text-primary-600 hover:text-primary-800" onclick="useAboutContent(<?php echo $content['id']; ?>)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                                <button type="button" class="text-blue-600 hover:text-blue-800" onclick="openEditAboutModal(<?php echo $content['id']; ?>, '<?php echo addslashes($content['aboutPic']); ?>', '<?php echo addslashes($content['aboutText']); ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                                <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteAboutContent(<?php echo $content['id']; ?>)">
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

    <!-- Home Content Modal -->
    <div id="homeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900" id="homeModalTitle">Add Home Content</h3>
                <form id="homeForm" method="POST" enctype="multipart/form-data" class="mt-4">
                    <input type="hidden" name="action" value="add_home">
                    <input type="hidden" name="id" id="homeId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Home Image</label>
                        <input type="file" name="homePic" id="homePic" accept="image/*" class="mt-1 block w-full">
    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Main Text</label>
                        <textarea name="maintext" id="homeMainText" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Second Text</label>
                        <textarea name="secondtext" id="homeSecondText" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Third Text</label>
                        <textarea name="thirdtext" id="homeThirdText" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                    </div>
                    
                <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeHomeModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Save</button>
                </div>
            </form>
            </div>
        </div>
    </div>
    
    <!-- About Content Modal -->
    <div id="aboutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900" id="aboutModalTitle">Add About Content</h3>
                <form id="aboutForm" method="POST" enctype="multipart/form-data" class="mt-4">
                    <input type="hidden" name="action" value="add_about">
                    <input type="hidden" name="id" id="aboutId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">About Image</label>
                        <input type="file" name="aboutPic" id="aboutPic" accept="image/*" class="mt-1 block w-full">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">About Text</label>
                        <textarea name="aboutText" id="aboutText" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                    </div>
                    
                <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeAboutModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Home Content Functions
    function openAddHomeModal() {
        document.getElementById('homeModalTitle').textContent = 'Add Home Content';
        document.getElementById('homeForm').reset();
        document.getElementById('homeForm').action.value = 'add_home';
        document.getElementById('homeId').value = '';
        document.getElementById('homeModal').classList.remove('hidden');
    }

    function openEditHomeModal(id, maintext, secondtext, thirdtext, homePic) {
        document.getElementById('homeModalTitle').textContent = 'Edit Home Content';
        document.getElementById('homeForm').action.value = 'edit_home';
        document.getElementById('homeId').value = id;
        document.getElementById('homeMainText').value = maintext;
        document.getElementById('homeSecondText').value = secondtext;
        document.getElementById('homeThirdText').value = thirdtext;
        document.getElementById('homeModal').classList.remove('hidden');
    }

    function closeHomeModal() {
        document.getElementById('homeModal').classList.add('hidden');
    }

    function useHomeContent(id) {
        if (confirm('Are you sure you want to set this content as active?')) {
            fetch(`index.php?page=home_management&action=use&id=${id}&type=home`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
        }
    }

    function deleteHomeContent(id) {
        if (confirm('Are you sure you want to delete this content?')) {
            fetch(`index.php?page=home_management&action=delete&id=${id}&type=home`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`home-row-${id}`).remove();
                    }
                });
        }
    }

    // About Content Functions
    function openAddAboutModal() {
        document.getElementById('aboutModalTitle').textContent = 'Add About Content';
        document.getElementById('aboutForm').reset();
        document.getElementById('aboutForm').action.value = 'add_about';
        document.getElementById('aboutId').value = '';
        document.getElementById('aboutModal').classList.remove('hidden');
    }

    function openEditAboutModal(id, aboutPic, aboutText) {
        document.getElementById('aboutModalTitle').textContent = 'Edit About Content';
        document.getElementById('aboutForm').action.value = 'edit_about';
        document.getElementById('aboutId').value = id;
        document.getElementById('aboutText').value = aboutText;
        document.getElementById('aboutModal').classList.remove('hidden');
    }

    function closeAboutModal() {
        document.getElementById('aboutModal').classList.add('hidden');
    }

    function useAboutContent(id) {
        if (confirm('Are you sure you want to set this content as active?')) {
            fetch(`index.php?page=home_management&action=use&id=${id}&type=about`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                        location.reload();
                    }
            });
    }
}

    function deleteAboutContent(id) {
        if (confirm('Are you sure you want to delete this content?')) {
            fetch(`index.php?page=home_management&action=delete&id=${id}&type=about`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                        document.getElementById(`about-row-${id}`).remove();
                    }
            });
    }
}
</script>
</div>
<?php
// Flush the output buffer
ob_end_flush();
?>