<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection
require_once 'config/db.php';

// Process form submission for adding/editing home content
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new home content
        if ($_POST['action'] == 'add') {
            $name = $_POST['name'];
            $maintext = $_POST['maintext'];
            $secondtext = $_POST['secondtext'];
            $thirdtext = $_POST['thirdtext'];
            $status = 1;
            $createdDate = date('Y-m-d H:i:s');
            
            // Handle logo image upload
            $logoPic = '';
            if (isset($_FILES['logoPic']) && $_FILES['logoPic']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $logoPic = $target_dir . time() . '_' . basename($_FILES["logoPic"]["name"]);
                move_uploaded_file($_FILES["logoPic"]["tmp_name"], $logoPic);
            }
            
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
            $sql = "INSERT INTO home (name, logoPic, homePic, maintext, secondtext, thirdtext, status, createdDate) 
                    VALUES (:name, :logoPic, :homePic, :maintext, :secondtext, :thirdtext, :status, :createdDate)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':logoPic' => $logoPic,
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
        
        // Edit existing home content
        if ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $maintext = $_POST['maintext'];
            $secondtext = $_POST['secondtext'];
            $thirdtext = $_POST['thirdtext'];
            
            // Get existing record to check for images
            $stmt = $pdo->prepare("SELECT logoPic, homePic FROM home WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $existingRecord = $stmt->fetch();
            
            // Handle logo image upload
            $logoPic = $existingRecord['logoPic']; // Default to existing image
            if (isset($_FILES['logoPic']) && $_FILES['logoPic']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $logoPic = $target_dir . time() . '_' . basename($_FILES["logoPic"]["name"]);
                move_uploaded_file($_FILES["logoPic"]["tmp_name"], $logoPic);
            }
            
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
            $sql = "UPDATE home SET name = :name, logoPic = :logoPic, homePic = :homePic, 
                    maintext = :maintext, secondtext = :secondtext, thirdtext = :thirdtext 
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':logoPic' => $logoPic,
                ':homePic' => $homePic,
                ':maintext' => $maintext,
                ':secondtext' => $secondtext,
                ':thirdtext' => $thirdtext
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
    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $sql = "UPDATE home SET status = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        // Return success response for AJAX
        echo json_encode(['success' => true, 'message' => 'Content deleted successfully']);
        exit();
    }
    
    // Set as active (update createdDate)
    if ($_GET['action'] == 'use' && isset($_GET['id'])) {
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
}

// Get all active home content
$stmt = $pdo->query("SELECT * FROM home WHERE status = 1 ORDER BY createdDate DESC");
$homeContents = $stmt->fetchAll();
?>

<div id="home_management">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Home Content Management</h2>
    
    <!-- Tabs for Information Sections -->
    <div class="mb-6 border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
            <li class="mr-2">
                <a href="index.php?page=information" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 transition-colors">Overview</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=home_management" class="inline-block p-4 border-b-2 border-teal-600 rounded-t-lg text-teal-600 active">Home Content</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=about_management" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 transition-colors">About Content</a>
            </li>
        </ul>
    </div>
    
    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div id="successAlert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 animate-fade-in-down">
        <span class="block sm:inline">
            <?php 
            if ($_GET['success'] == 'added') echo 'Home content added successfully!';
            if ($_GET['success'] == 'updated') echo 'Home content updated successfully!';
            ?>
        </span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="document.getElementById('successAlert').style.display = 'none'">
            <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Close</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Add Button -->
    <div class="mb-4 flex justify-end">
        <button type="button" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md flex items-center transition-colors shadow-sm" onclick="openAddModal()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Home Content
        </button>
    </div>
    
    <!-- DataTable -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
        <table id="homeTable" class="min-w-full divide-y divide-gray-200 w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Home Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Main Text</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($homeContents as $content): ?>
                <tr id="row-<?php echo $content['id']; ?>" class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($content['name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (!empty($content['logoPic'])): ?>
                        <img src="<?php echo htmlspecialchars($content['logoPic']); ?>" alt="Logo" class="h-10 w-10 object-cover rounded-md border border-gray-200">
                        <?php else: ?>
                        <span class="text-gray-400">No image</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (!empty($content['homePic'])): ?>
                        <img src="<?php echo htmlspecialchars($content['homePic']); ?>" alt="Home Image" class="h-10 w-16 object-cover rounded-md border border-gray-200">
                        <?php else: ?>
                        <span class="text-gray-400">No image</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 truncate max-w-xs"><?php echo htmlspecialchars(substr($content['maintext'], 0, 50)) . (strlen($content['maintext']) > 50 ? '...' : ''); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($content['createdDate'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <button type="button" class="text-teal-600 hover:text-teal-900 transition-colors" onclick="useContent(<?php echo $content['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <button type="button" class="text-blue-600 hover:text-blue-900 transition-colors" onclick="openEditModal(<?php echo $content['id']; ?>, '<?php echo addslashes($content['name']); ?>', '<?php echo addslashes($content['logoPic']); ?>', '<?php echo addslashes($content['homePic']); ?>', '<?php echo addslashes($content['maintext']); ?>', '<?php echo addslashes($content['secondtext']); ?>', '<?php echo addslashes($content['thirdtext']); ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-900 transition-colors" onclick="deleteContent(<?php echo $content['id']; ?>)">
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
    
    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add Home Content</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 transition-colors" onclick="closeAddModal()">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="index.php?page=home_management" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" id="name" name="name" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label for="logoPic" class="block text-sm font-medium text-gray-700 mb-1">Logo Image</label>
                        <input type="file" id="logoPic" name="logoPic" accept="image/*" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label for="homePic" class="block text-sm font-medium text-gray-700 mb-1">Home Image</label>
                        <input type="file" id="homePic" name="homePic" accept="image/*" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="maintext" class="block text-sm font-medium text-gray-700 mb-1">Main Text</label>
                    <textarea id="maintext" name="maintext" rows="3" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="mb-4">
                    <label for="secondtext" class="block text-sm font-medium text-gray-700 mb-1">Second Text</label>
                    <textarea id="secondtext" name="secondtext" rows="2" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="mb-4">
                    <label for="thirdtext" class="block text-sm font-medium text-gray-700 mb-1">Third Text</label>
                    <textarea id="thirdtext" name="thirdtext" rows="2" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-md mr-2 transition-colors" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md transition-colors shadow-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Home Content</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 transition-colors" onclick="closeEditModal()">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="index.php?page=home_management" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" id="edit_name" name="name" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label for="edit_logoPic" class="block text-sm font-medium text-gray-700 mb-1">Logo Image</label>
                        <input type="file" id="edit_logoPic" name="logoPic" accept="image/*" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <div id="current_logo" class="mt-2"></div>
                    </div>
                    <div>
                        <label for="edit_homePic" class="block text-sm font-medium text-gray-700 mb-1">Home Image</label>
                        <input type="file" id="edit_homePic" name="homePic" accept="image/*" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <div id="current_home_pic" class="mt-2"></div>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="edit_maintext" class="block text-sm font-medium text-gray-700 mb-1">Main Text</label>
                    <textarea id="edit_maintext" name="maintext" rows="3" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="mb-4">
                    <label for="edit_secondtext" class="block text-sm font-medium text-gray-700 mb-1">Second Text</label>
                    <textarea id="edit_secondtext" name="secondtext" rows="2" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="mb-4">
                    <label for="edit_thirdtext" class="block text-sm font-medium text-gray-700 mb-1">Third Text</label>
                    <textarea id="edit_thirdtext" name="thirdtext" rows="2" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-md mr-2 transition-colors" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md transition-colors shadow-sm">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable with responsive features
        $('#homeTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            order: [[4, 'desc']], // Sort by created date by default
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            columnDefs: [
                { responsivePriority: 1, targets: [0, 5] }, // Name and Actions columns are prioritized
                { responsivePriority: 2, targets: 4 },      // Date column is second priority
                { responsivePriority: 3, targets: 3 }       // Main Text column is third priority
            ]
        });
    });
    
    // Modal functions
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }
    
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }
    
    function openEditModal(id, name, logoPic, homePic, maintext, secondtext, thirdtext) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_maintext').value = maintext;
        document.getElementById('edit_secondtext').value = secondtext;
        document.getElementById('edit_thirdtext').value = thirdtext;
        
        // Show current images if they exist
        let logoContainer = document.getElementById('current_logo');
        let homePicContainer = document.getElementById('current_home_pic');
        
        if (logoPic) {
            logoContainer.innerHTML = `<img src="${logoPic}" alt="Current Logo" class="h-10 w-10 object-cover rounded-md border border-gray-200 mt-1">
                                      <p class="text-xs text-gray-500">Current logo (leave empty to keep)</p>`;
        } else {
            logoContainer.innerHTML = '<p class="text-xs text-gray-500">No current logo</p>';
        }
        
        if (homePic) {
            homePicContainer.innerHTML = `<img src="${homePic}" alt="Current Home Image" class="h-10 w-16 object-cover rounded-md border border-gray-200 mt-1">
                                         <p class="text-xs text-gray-500">Current image (leave empty to keep)</p>`;
        } else {
            homePicContainer.innerHTML = '<p class="text-xs text-gray-500">No current image</p>';
        }
        
        document.getElementById('editModal').classList.remove('hidden');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
    
    // AJAX functions
    function deleteContent(id) {
        if (confirm('Are you sure you want to delete this content?')) {
            fetch(`index.php?page=home_management&action=delete&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the row from the DataTable without page reload
                        const table = $('#homeTable').DataTable();
                        table.row($(`#row-${id}`)).remove().draw();
                        
                        // Show success toast
                        showToast(data.message || 'Content deleted successfully');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while deleting content', 'error');
                });
        }
    }
    
    function useContent(id) {
        if (confirm('Set this content as active?')) {
            fetch(`index.php?page=home_management&action=use&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success toast
                        showToast(data.message || 'Content set as active');
                        
                        // Refresh the table to show updated dates
                        $('#homeTable').DataTable().ajax.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while updating content', 'error');
                });
        }
    }
</script>
<?php
// Flush the output buffer
ob_end_flush();
?>
