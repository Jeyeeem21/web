<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection
require_once 'config/db.php';

// Process form submission for adding/editing about content
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new about content
        if ($_POST['action'] == 'add') {
            $aboutText = $_POST['aboutText'];
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
            header("Location: index.php?page=about_management&success=added");
            exit();
        }
        
        // Edit existing about content
        if ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $aboutText = $_POST['aboutText'];
            
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
            header("Location: index.php?page=about_management&success=updated");
            exit();
        }
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    // Delete (soft delete) about content
    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $sql = "UPDATE about SET status = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        // Return success response for AJAX
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Set as active (update createdDate)
    if ($_GET['action'] == 'use' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $createdDate = date('Y-m-d H:i:s');
        $sql = "UPDATE about SET createdDate = :createdDate WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':createdDate' => $createdDate
        ]);
        
        // Return success response for AJAX
        echo json_encode(['success' => true]);
        exit();
    }
}

// Get all active about content
$stmt = $pdo->query("SELECT * FROM about WHERE status = 1 ORDER BY createdDate DESC");
$aboutContents = $stmt->fetchAll();
?>

<div id="about_management">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">About Content Management</h2>
    
    <!-- Tabs for Information Sections -->
    <div class="mb-6 border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
            <li class="mr-2">
                <a href="index.php?page=information" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300">Overview</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=home_management" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300">Home Content</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=about_management" class="inline-block p-4 border-b-2 border-teal-600 rounded-t-lg text-teal-600 active">About Content</a>
            </li>
        </ul>
    </div>
    
    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div id="successAlert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <span class="block sm:inline">
            <?php 
            if ($_GET['success'] == 'added') echo 'About content added successfully!';
            if ($_GET['success'] == 'updated') echo 'About content updated successfully!';
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
        <button type="button" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md flex items-center" onclick="openAddModal()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add About Content
        </button>
    </div>
    
    <!-- DataTable -->
    <div class="bg-white rounded-lg shadow-md p-6 overflow-hidden">
        <table id="aboutTable" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">About Text</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($aboutContents as $content): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (!empty($content['aboutPic'])): ?>
                        <img src="<?php echo htmlspecialchars($content['aboutPic']); ?>" alt="About Image" class="h-16 w-24 object-cover rounded">
                        <?php else: ?>
                        <span class="text-gray-400">No image</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 truncate max-w-xs"><?php echo htmlspecialchars(substr($content['aboutText'], 0, 100)) . (strlen($content['aboutText']) > 100 ? '...' : ''); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($content['createdDate'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <button type="button" class="text-teal-600 hover:text-teal-900" onclick="useContent(<?php echo $content['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <button type="button" class="text-blue-600 hover:text-blue-900" onclick="openEditModal(<?php echo $content['id']; ?>, '<?php echo addslashes($content['aboutPic']); ?>', '<?php echo addslashes($content['aboutText']); ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-900" onclick="deleteContent(<?php echo $content['id']; ?>)">
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
                <h3 class="text-lg font-medium text-gray-900">Add About Content</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeAddModal()">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="index.php?page=about_management" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="mb-4">
                    <label for="aboutPic" class="block text-sm font-medium text-gray-700 mb-1">About Image</label>
                    <input type="file" id="aboutPic" name="aboutPic" accept="image/*" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="mb-4">
                    <label for="aboutText" class="block text-sm font-medium text-gray-700 mb-1">About Text</label>
                    <textarea id="aboutText" name="aboutText" rows="6" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-md mr-2" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit About Content</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeEditModal()">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="index.php?page=about_management" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <div class="mb-4">
                    <label for="edit_aboutPic" class="block text-sm font-medium text-gray-700 mb-1">About Image</label>
                    <input type="file" id="edit_aboutPic" name="aboutPic" accept="image/*" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <div id="current_about_pic" class="mt-2"></div>
                </div>
                <div class="mb-4">
                    <label for="edit_aboutText" class="block text-sm font-medium text-gray-700 mb-1">About Text</label>
                    <textarea id="edit_aboutText" name="aboutText" rows="6" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-md mr-2" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-md">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DataTables CSS and JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#aboutTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            order: [[2, 'desc']] // Sort by created date by default
        });
    });
    
    // Modal functions
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }
    
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }
    
    function openEditModal(id, aboutPic, aboutText) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_aboutText').value = aboutText;
        
        // Show current image if it exists
        let aboutPicContainer = document.getElementById('current_about_pic');
        
        if (aboutPic) {
            aboutPicContainer.innerHTML = `<img src="${aboutPic}" alt="Current About Image" class="h-16 w-24 object-cover rounded mt-1">
                                          <p class="text-xs text-gray-500">Current image (leave empty to keep)</p>`;
        } else {
            aboutPicContainer.innerHTML = '<p class="text-xs text-gray-500">No current image</p>';
        }
        
        document.getElementById('editModal').classList.remove('hidden');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
    
    // AJAX functions
    function deleteContent(id) {
        if (confirm('Are you sure you want to delete this content?')) {
            fetch(`index.php?page=about_management&action=delete&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }
    
    function useContent(id) {
        if (confirm('Set this content as active?')) {
            fetch(`index.php?page=about_management&action=use&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }
</script>
<?php
// Flush the output buffer
ob_end_flush();
?>
