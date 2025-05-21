<?php
// Include database connection
require_once 'config/db.php';

// Fetch clinic details
try {
    $stmt = $pdo->query("SELECT * FROM clinic_details LIMIT 1");
    $clinic = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clinic = []; // Fallback to empty array
    error_log("Clinic details query failed: " . $e->getMessage());
}

// Fetch active staff members
try {
    $stmt = $pdo->query("SELECT s.*, dp.name as position_name 
                         FROM staff s 
                         LEFT JOIN doctor_position dp ON s.doctor_position_id = dp.id 
                         WHERE s.status = 1 
                         ORDER BY s.role, s.name");
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff = []; // Fallback to empty array
    error_log("Staff query failed: " . $e->getMessage());
    // Fallback query without doctor_position join
    try {
        $stmt = $pdo->query("SELECT * FROM staff WHERE status = 1 ORDER BY role, name");
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fallback staff query failed: " . $e->getMessage());
    }
}

// Fetch active services and group by kind_of_doctor
try {
    $stmt = $pdo->query("SELECT kind_of_doctor, service_name 
                         FROM services 
                         WHERE status = 1 
                         ORDER BY kind_of_doctor, service_name");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Group services by kind_of_doctor
    $grouped_services = [];
    foreach ($services as $service) {
        $grouped_services[$service['kind_of_doctor']][] = $service['service_name'];
    }
} catch (PDOException $e) {
    $grouped_services = []; // Fallback to empty array
    error_log("Services query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bright Smile Dental Clinic - Information</title>
    <style>
        @media (max-width: 640px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .border-b {
                overflow-x: auto;
            }
            
            .flex-wrap {
                flex-wrap: nowrap;
                padding-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div id="information" class="space-y-6">
        <h2 class="text-xl font-medium text-gray-800">Clinic Information</h2>
        
        <!-- Tabs for Home, About, and Other Information -->
        <div class="border-b border-gray-200">
            <ul class="flex flex-wrap -mb-px text-sm">
                <li class="mr-2">
                    <a href="index.php?page=information" class="inline-block p-3 border-b-2 border-primary-600 text-primary-600">Overview</a>
                </li>
                <li class="mr-2">
                    <a href="index.php?page=home_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Data</a>
                </li>
                <li class="mr-2">
                    <a href="index.php?page=doctor_position_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Services</a>
                </li>
                <li class="mr-2">
                    <a href="index.php?page=staff_management" class="inline-block p-3 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300">Staff</a>
                </li>
            </ul>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-md border border-gray-100 p-4">
                <h3 class="text-sm font-medium text-gray-800 mb-3">Clinic Details</h3>
                <div class="space-y-3">
                    <div>
                        <h4 class="text-xs font-medium text-gray-500">Clinic Name</h4>
                        <p class="text-sm text-gray-800"><?php echo htmlspecialchars($clinic['clinic_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <h4 class="text-xs font-medium text-gray-500">Address</h4>
                        <p class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($clinic['address'] ?? 'N/A')); ?></p>
                    </div>
                    <div>
                        <h4 class="text-xs font-medium text-gray-500">Contact</h4>
                        <p class="text-sm text-gray-800">
                            Phone: <?php echo htmlspecialchars($clinic['phone'] ?? 'N/A'); ?><br>
                            Email: <?php echo htmlspecialchars($clinic['email'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <div>
                        <h4 class="text-xs font-medium text-gray-500">Hours of Operation</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <p class="text-sm text-gray-800">Monday - Friday</p>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($clinic['hours_weekdays'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-800">Saturday</p>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($clinic['hours_saturday'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-800">Sunday</p>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($clinic['hours_sunday'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-md border border-gray-100 p-4">
                <h3 class="text-sm font-medium text-gray-800 mb-3">Staff Directory</h3>
                <div class="space-y-3">
                    <?php if (empty($staff)): ?>
                        <p class="text-sm text-gray-600">No active staff members found.</p>
                    <?php else: ?>
                        <?php foreach ($staff as $member): ?>
                            <div class="flex items-center">
                                <?php if (!empty($member['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($member['photo']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" class="w-10 h-10 rounded-full mr-3">
                                <?php endif; ?>
                                <div>
                                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($member['name']); ?></p>
                                    <p class="text-xs text-gray-600">
                                        <?php 
                                        if ($member['role'] == 'doctor') {
                                            echo htmlspecialchars($member['position_name'] ?? ucfirst($member['role']));
                                        } else {
                                            echo ucfirst(htmlspecialchars($member['role']));
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-white rounded-md border border-gray-100 p-4 md:col-span-2">
                <h3 class="text-sm font-medium text-gray-800 mb-3">Services Offered</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php if (empty($grouped_services)): ?>
                        <p class="text-sm text-gray-600">No active services available.</p>
                    <?php else: ?>
                        <?php foreach ($grouped_services as $doctor_type => $services): ?>
                            <div class="p-3 border rounded-md">
                                <h4 class="text-sm font-medium text-gray-800 mb-2"><?php echo htmlspecialchars($doctor_type); ?></h4>
                                <ul class="text-xs text-gray-600 space-y-1">
                                    <?php foreach ($services as $service): ?>
                                        <li>• <?php echo htmlspecialchars($service); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>