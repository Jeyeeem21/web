<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection
require_once 'config/db.php';

// Process form submission for adding/editing services
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new service
        if ($_POST['action'] == 'add') {
            $service_name = $_POST['service_name'] ?? '';
            $service_description = $_POST['service_description'] ?? '';
            $service_picture = $_POST['service_picture'] ?? '';
            $price = $_POST['price'] ?? 0.00;
            $status = 1;
            $created_at = date('Y-m-d H:i:s');

            // Insert into database
            $sql = "INSERT INTO services (service_name, service_description, service_picture, price, status, created_at) 
                    VALUES (:service_name, :service_description, :service_picture, :price, :status, :created_at)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':service_name' => $service_name,
                ':service_description' => $service_description,
                ':service_picture' => $service_picture,
                ':price' => $price,
                ':status' => $status,
                ':created_at' => $created_at
            ]);

            // Redirect to prevent form resubmission
            header("Location: index.php?page=service_management&success=added");
            exit();
        }

        // Edit existing service
        if ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $service_name = $_POST['service_name'] ?? '';
            $service_description = $_POST['service_description'] ?? '';
            $service_picture = $_POST['service_picture'] ?? '';
            $price = $_POST['price'] ?? 0.00;

            // Update database
            $sql = "UPDATE services SET service_name = :service_name, service_description = :service_description, 
                    service_picture = :service_picture, price = :price WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':service_name' => $service_name,
                ':service_description' => $service_description,
                ':service_picture' => $service_picture,
                ':price' => $price
            ]);

            // Redirect to prevent form resubmission
            header("Location: index.php?page=service_management&success=updated");
            exit();
        }

        // Update price
        if ($_POST['action'] == 'update_price') {
            $id = $_POST['id'];
            $price = $_POST['price'] ?? 0.00;

            // Update price in database
            $sql = "UPDATE services SET price = :price WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':price' => $price
            ]);

            // Return success response for AJAX
            echo json_encode(['success' => true, 'message' => 'Price updated successfully']);
            exit();
        }
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    // Delete (soft delete) service
    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $sql = "UPDATE services SET status = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        // Return success response for AJAX
        echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
        exit();
    }
}

// Get all active services
$stmt = $pdo->query("SELECT * FROM services WHERE status = 1 ORDER BY created_at DESC");
$services = $stmt->fetchAll();
?>

<div id="service_management" class="space-y-6">
    <h2 class="text-xl font-medium text-gray-800">Service Management</h2>

    <!-- Tabs for Information Sections -->
    <div class="border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm">
            <li class="mr-2">
                <a href="index.php?page=information" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Overview</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=home_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Data</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=doctor_position_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Services</a>
            </li>
            <li class="mr-2">
                <a href="index.php?page=service_management" class="inline-block p-3 border-b-2 border-primary-600 text-primary-600">Services</a>
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
            if ($_GET['success'] == 'added') echo 'Service added successfully!';
            if ($_GET['success'] == 'updated') echo 'Service updated successfully!';
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

    <!-- Service Section -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Services</h3>
            <button type="button" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 rounded-md text-sm flex items-center" onclick="openAddModal()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Service
            </button>
        </div>

        <!-- Service Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 mobile-card-view">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Picture</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($services as $service): ?>
                    <tr id="service-row-<?php echo $service['id']; ?>" class="hover:bg-gray-50">
                        <td class="px-4 py-2" data-label="Service Name">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($service['service_name']); ?></div>
                        </td>
                        <td class="px-4 py-2" data-label="Description">
                            <div class="text-sm text-gray-900 truncate max-w-xs"><?php echo htmlspecialchars(substr($service['service_description'], 0, 50)) . (strlen($service['service_description']) > 50 ? '...' : ''); ?></div>
                        </td>
                        <td class="px-4 py-2" data-label="Picture">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($service['service_picture']); ?></div>
                        </td>
                        <td class="px-4 py-2" data-label="Price">
                            <div class="text-sm text-gray-900 flex items-center space-x-2">
                                <span id="price-<?php echo $service['id']; ?>">$<?php echo number_format($service['price'], 2); ?></span>
                                <button type="button" class="text-green-600 hover:text-green-800" onclick="openPriceModal(<?php echo $service['id']; ?>, <?php echo $service['price']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap" data-label="Created Date">
                            <div class="text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($service['created_at'])); ?></div>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm" data-label="Actions">
                            <div class="flex space-x-1">
                                <button type="button" class="text-blue-600 hover:text-blue-800" onclick="openEditModal(<?php echo $service['id']; ?>, '<?php echo addslashes($service['service_name']); ?>', '<?php echo addslashes($service['service_description']); ?>', '<?php echo addslashes($service['service_picture']); ?>', <?php echo $service['price']; ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" class="text-red-600 hover:text-red-800" onclick="deleteService(<?php echo $service['id']; ?>)">
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

    <!-- Add mobile card view styles -->
    <style>
    /* Mobile-friendly styles */
    @media (max-width: 640px) {
        .mobile-card-view thead {
            display: none;
        }
        
        .mobile-card-view tbody tr {
            display: block;
            margin-bottom: 0.25rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            padding: 0.25rem;
            background-color: white;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .mobile-card-view tbody td {
            display: flex;
            padding: 0.125rem 0;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
            min-height: 20px;
        }
        
        .mobile-card-view tbody td:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .mobile-card-view tbody td:first-child {
            padding-top: 0;
        }
        
        .mobile-card-view tbody td:before {
            content: attr(data-label);
            font-weight: 600;
            width: 35%;
            color: #4b5563;
            font-size: 0.7rem;
            line-height: 1;
        }
        
        .mobile-card-view tbody td > div {
            width: 65%;
            font-size: 0.7rem;
            color: #1f2937;
            line-height: 1;
        }

        /* Adjust specific columns */
        .mobile-card-view tbody td[data-label="Description"] > div {
            max-height: 20px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mobile-card-view tbody td[data-label="Actions"] > div {
            display: flex;
            gap: 0.25rem;
        }

        .mobile-card-view tbody td[data-label="Actions"] button svg {
            width: 0.875rem;
            height: 0.875rem;
        }
        
        /* DataTables mobile adjustments */
        .dataTables_length, 
        .dataTables_filter, 
        .dataTables_info, 
        .dataTables_paginate {
            width: 100%;
            margin-bottom: 0.25rem;
            text-align: center;
            font-size: 0.7rem;
        }

        /* Ensure consistent spacing between cards */
        .mobile-card-view tbody tr + tr {
            margin-top: 0.25rem;
        }

        /* Adjust table container */
        .overflow-x-auto {
            margin: 0 -0.25rem;
            padding: 0 0.25rem;
        }
    }

    /* Modal overlay for mobile */
    @media (max-width: 640px) {
        .fixed.inset-0 > div {
            width: 90% !important;
            max-width: none !important;
            margin: 0 auto;
        }
    }
    </style>

    <!-- Service Modal -->
    <div id="serviceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Add Service</h3>
                <form id="serviceForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" id="serviceId">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Service Name</label>
                        <input type="text" name="service_name" id="serviceName" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="service_description" id="serviceDescription" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Picture URL</label>
                        <input type="text" name="service_picture" id="servicePicture" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Price</label>
                        <input type="number" step="0.01" name="price" id="servicePrice" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Price Update Modal -->
    <div id="priceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900">Update Price</h3>
                <form id="priceForm" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="update_price">
                    <input type="hidden" name="id" id="priceServiceId">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">New Price</label>
                        <input type="number" step="0.01" name="price" id="newPrice" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closePriceModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Service';
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceForm').action.value = 'add';
    document.getElementById('serviceId').value = '';
    document.getElementById('serviceModal').classList.remove('hidden');
}

function openEditModal(id, name, description, picture, price) {
    document.getElementById('modalTitle').textContent = 'Edit Service';
    document.getElementById('serviceForm').action.value = 'edit';
    document.getElementById('serviceId').value = id;
    document.getElementById('serviceName').value = name;
    document.getElementById('serviceDescription').value = description;
    document.getElementById('servicePicture').value = picture;
    document.getElementById('servicePrice').value = price;
    document.getElementById('serviceModal').classList.remove('hidden');
}

function openPriceModal(id, price) {
    document.getElementById('priceServiceId').value = id;
    document.getElementById('newPrice').value = price;
    document.getElementById('priceModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('serviceModal').classList.add('hidden');
}

function closePriceModal() {
    document.getElementById('priceModal').classList.add('hidden');
}

function deleteService(id) {
    if (confirm('Are you sure you want to delete this service?')) {
        fetch(`index.php?page=service_management&action=delete&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`service-row-${id}`).remove();
                }
            });
    }
}

$(document).ready(function() {
    $('.mobile-card-view').DataTable({
        responsive: true,
        language: {
            search: "",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"<"mb-4 md:mb-0"l><"flex items-center"f>>rtip',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        pageLength: 10,
        columnDefs: [
            { orderable: false, targets: -1 }
        ],
        scrollX: false,
        autoWidth: false
    });

    // Handle price form submission
    $('#priceForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('index.php?page=service_management', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`price-${formData.get('id')}`).textContent = `$${parseFloat(formData.get('price')).toFixed(2)}`;
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