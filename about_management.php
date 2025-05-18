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
                $target_dir = "Uploads/";
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
                $target_dir = "Uploads/";
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
        echo json_encode(['success' => true, 'message' => 'Content deleted successfully']);
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
        echo json_encode(['success' => true, 'message' => 'Content set as active']);
        exit();
    }
}

// Get all active about content
$stmt = $pdo->query("SELECT * FROM about WHERE status = 1 ORDER BY createdDate DESC");
$aboutContents = $stmt->fetchAll();
?>

<div id="about_management" class="space-y-6">
    <h2 class="text-xl font-medium text-gray-800">About Content Management</h2>
    
    <!-- Tabs for Information Sections -->
    <div class="border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm">
            <li class="mr-2">
                <a href="index.php?page=information" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Overview</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=home_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Home</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=about_management" class="inline-block p-3 border-b-2 border-primary-600 text-primary-600">About</a>
            </li>
        </ul>
    </div>
    
    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div id="successAlert" class="bg-green-50 border border-green-200 text-green-800 px-3 py-2 rounded-md text-sm flex justify-between items-center">
        <span>
            <?php 
            if ($_GET['success'] == 'added') echo 'About content added successfully!';
            if ($_GET['success'] == 'updated') echo 'About content updated successfully!';
            ?>
        </span>
        <button type="button" onclick="document.getElementById('successAlert').style.display = 'none'" class="text-green-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Add Button -->
    <div class="flex justify-end">
        <button type="button" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm flex items-center" onclick="openAddModal()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Content
        </button>
    </div>
    
    <!-- Data Display: Table for Desktop, Cards for Mobile -->
    <div class="bg-white rounded-md border border-gray-100">
        <!-- Table View (Hidden on Mobile) -->
        <table id="aboutTable" class="min-w-full divide-y divide-gray-200 hidden sm:table">
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
                <tr id="row-<?php echo $content['id']; ?>" class="hover:bg-gray-50">
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
                            <button type="button" class="text-primary-600 hover:text-primary-800" onclick="useContent(<?php echo $content['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <button type="button" class="text-blue-600 hover:text-blue-800" onclick="openEditModal(<?php echo $content['id']; ?>, '<?php echo addslashes($content['aboutPic']); ?>', '<?php echo addslashes($content['aboutText']); ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteContent(<?php echo $content['id']; ?>)">
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

        <!-- Card View (Visible on Mobile) -->
        <div class="sm:hidden space-y-3 p-3">
            <?php foreach ($aboutContents as $content): ?>
            <div id="card-<?php echo $content['id']; ?>" class="bg-white border border-gray-200 rounded-md shadow-sm p-3 w-full">
                <div class="grid grid-cols-1 gap-3 text-xs">
                    <div>
                        <span class="text-[10px] font-medium text-gray-500 uppercase">Image</span>
                        <div>
                            <?php if (!empty($content['aboutPic'])): ?>
                            <img src="<?php echo htmlspecialchars($content['aboutPic']); ?>" alt="About Image" class="h-8 w-10 object-cover rounded border border-gray-200">
                            <?php else: ?>
                            <span class="text-gray-400 text-[10px]">No image</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-medium text-gray-500 uppercase">About Text</span>
                        <p class="text-gray-900 truncate"><?php echo htmlspecialchars(substr($content['aboutText'], 0, 50)) . (strlen($content['aboutText']) > 50 ? '...' : ''); ?></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-medium text-gray-500 uppercase">Created Date</span>
                        <p class="text-gray-900"><?php echo date('M d, Y H:i', strtotime($content['createdDate'])); ?></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-medium text-gray-500 uppercase">Actions</span>
                        <div class="flex space-x-2 mt-1">
                            <button type="button" class="text-primary-600 hover:text-primary-800" onclick="useContent(<?php echo $content['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <button type="button" class="text-blue-600 hover:text-blue-800" onclick="openEditModal(<?php echo $content['id']; ?>, '<?php echo addslashes($content['aboutPic']); ?>', '<?php echo addslashes($content['aboutText']); ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteContent(<?php echo $content['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <!-- Pagination Container for Mobile -->
            <div class="pagination-container sm:hidden flex justify-end mt-4 pb-4 pr-3">
                <!-- DataTable will inject pagination here -->
            </div>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-500 bg-opacity-25 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-4 border w-11/12 md:w-2/3 lg:w-1/2 shadow-sm rounded-md bg-white">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-medium text-gray-900">Add About Content</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeAddModal()">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="index.php?page=about_management" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label for="aboutPic" class="block text-xs font-medium text-gray-700 mb-1">About Image</label>
                    <input type="file" id="aboutPic" name="aboutPic" accept="image/*" class="w-full px-2 py-1 text-sm border rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500">
                </div>
                <div class="mb-3">
                    <label for="aboutText" class="block text-xs font-medium text-gray-700 mb-1">About Text</label>
                    <textarea id="aboutText" name="aboutText" rows="6" required class="w-full px-2 py-1 text-sm border rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1.5 rounded-md text-sm" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-500 bg-opacity-25 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-4 border w-11/12 md:w-2/3 lg:w-1/2 shadow-sm rounded-md bg-white">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-medium text-gray-900">Edit About Content</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="closeEditModal()">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form action="index.php?page=about_management" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">
                <div class="mb-3">
                    <label for="edit_aboutPic" class="block text-xs font-medium text-gray-700 mb-1">About Image</label>
                    <input type="file" id="edit_aboutPic" name="aboutPic" accept="image/*" class="w-full px-2 py-1 text-sm border rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <div id="current_about_pic" class="mt-1"></div>
                </div>
                <div class="mb-3">
                    <label for="edit_aboutText" class="block text-xs font-medium text-gray-700 mb-1">About Text</label>
                    <textarea id="edit_aboutText" name="aboutText" rows="6" required class="w-full px-2 py-1 text-sm border rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1.5 rounded-md text-sm" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable with responsive features
    const table = $('#aboutTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        order: [[2, 'desc']], // Sort by created date by default
        language: {
            search: "",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_",
            info: "_START_-_END_ of _TOTAL_",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Prev"
            }
        },
        columnDefs: [
            { responsivePriority: 1, targets: [0, 3] }, // Image and Actions columns are prioritized
            { responsivePriority: 2, targets: 2 },      // Date column is second priority
            { responsivePriority: 3, targets: 1 }       // Text column is third priority
        ],
        dom: '<"flex items-center space-x-4 mb-4"l<"ml-auto"f>>t<"pagination-container flex justify-end mt-4"p>',
        pagingType: 'simple_numbers',
        paging: true, // Explicitly enable paging
        drawCallback: function(settings) {
            console.log('drawCallback: Pagination rendering'); // Debug log
            // Customize pagination with Tailwind classes
            $('.dataTables_paginate').addClass('flex items-center space-x-1 text-sm');
            $('.paginate_button').addClass('px-2 py-1 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-100');
            $('.paginate_button.current').addClass('bg-primary-600 text-white border-primary-600 hover:bg-primary-700');
            $('.paginate_button.disabled').addClass('text-gray-400 cursor-not-allowed hover:bg-transparent');

            // Move pagination to mobile container in mobile view
            if (window.innerWidth < 640) { // Tailwind's 'sm' breakpoint
                console.log('Mobile view: Moving pagination'); // Debug log
                const $pagination = $('.dataTables_paginate');
                if ($pagination.length) {
                    $('.sm:hidden .pagination-container').html($pagination.detach());
                    $('.sm:hidden .pagination-container').addClass('flex justify-end pr-3');
                    $('.sm:hidden .pagination-container').css('display', 'flex'); // Ensure visibility
                } else {
                    console.warn('Pagination element not found');
                }
            } else {
                console.log('Desktop view: Restoring pagination'); // Debug log
                $('.pagination-container').removeClass('justify-end pr-3');
                $('.dataTables_wrapper .pagination-container').append($('.dataTables_paginate').detach());
                $('.pagination-container').css('display', ''); // Reset display
            }
        }
    });

    // Style the search input and length menu with Tailwind
    $('.dataTables_length select').addClass('px-2 py-1 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500');
    $('.dataTables_filter input').addClass('px-2 py-1 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 w-32');

    // Re-run pagination adjustment on window resize
    $(window).resize(function() {
        table.draw();
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
        aboutPicContainer.innerHTML = `<img src="${aboutPic}" alt="Current About Image" class="h-8 w-12 object-cover rounded-md border border-gray-200 mt-1">
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
                    // Remove the row from the DataTable or card without page reload
                    const table = $('#aboutTable').DataTable();
                    table.row($(`#row-${id}`)).remove().draw();
                    document.getElementById(`card-${id}`)?.remove();
                    
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
        fetch(`index.php?page=about_management&action=use&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success toast
                    showToast(data.message || 'Content set as active');
                    
                    // Refresh the table to show updated dates
                    $('#aboutTable').DataTable().ajax.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while updating content', 'error');
            });
    }
}

// Toast function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-md text-sm ${type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
<?php
// Flush the output buffer
ob_end_flush();
?>